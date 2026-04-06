<?php
require_once __DIR__ . '/config.php';

$allowedBuckets = ['documentos', 'perfiles'];
$bucket = trim((string)($_GET['type'] ?? ''), "\\/");
$filename = basename((string)($_GET['file'] ?? ''));
$download = ($_GET['download'] ?? '0') === '1';

if ($filename === '' || !in_array($bucket, $allowedBuckets, true)) {
    http_response_code(404);
    exit('Archivo no encontrado');
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

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: public, max-age=3600');

if ($download) {
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
}

readfile($path);
?>
