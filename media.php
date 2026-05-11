<?php
require_once __DIR__ . '/bootstrap.php';

$allowedBuckets = ['documentos', 'perfiles', 'comprobantes_pago'];
$bucket = trim((string)($_GET['type'] ?? ''), "\\/");
$filename = basename((string)($_GET['file'] ?? ''));
$download = ($_GET['download'] ?? '0') === '1';

if ($filename === '' || !in_array($bucket, $allowedBuckets, true)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Acceso no autorizado');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = (string)($_SESSION['user_rol'] ?? '');
if ($pdo instanceof PDO) {
    $dbRole = fetch_user_role($pdo, $userId);
    if ($dbRole !== null) {
        $userRole = $dbRole;
    }
}
$isAdmin = is_admin_role($userRole);

if ($bucket === 'documentos' && user_has_profile_only_access($pdo ?? null, $userId, $userRole, (string)($_SESSION['user_estatus'] ?? ''))) {
    http_response_code(403);
    exit('Acceso no autorizado');
}

if ($bucket === 'comprobantes_pago' && !$isAdmin) {
    if (!($pdo instanceof PDO)) {
        http_response_code(403);
        exit('Acceso no autorizado');
    }

    $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE id = ? AND comprobante_pago = ? LIMIT 1');
    $stmt->execute([$userId, $filename]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        exit('Acceso no autorizado');
    }
}

$path = app_resolve_storage_path($bucket, $filename);
if ($path === null) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$mime = 'application/octet-stream';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo->file($path);
    if (is_string($detected) && $detected !== '') {
        $mime = $detected;
    }
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));

if ($bucket === 'comprobantes_pago') {
    header('Cache-Control: no-store, private');
} else {
    header('Cache-Control: private, max-age=3600');
}

if ($download) {
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
}

readfile($path);
