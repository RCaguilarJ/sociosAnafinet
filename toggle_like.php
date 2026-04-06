<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: foro.php");
    exit();
}

$temaId = isset($_POST['tema_id']) ? (int)$_POST['tema_id'] : 0;
$usuarioId = (int)($_SESSION['user_id'] ?? 0);
$redirect = $_POST['redirect'] ?? '';

if ($temaId <= 0 || $usuarioId <= 0) {
    header("Location: foro.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id FROM foro_likes WHERE tema_id = ? AND usuario_id = ? LIMIT 1");
    $stmt->execute([$temaId, $usuarioId]);
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $del = $pdo->prepare("DELETE FROM foro_likes WHERE tema_id = ? AND usuario_id = ?");
        $del->execute([$temaId, $usuarioId]);
        $status = 'like_removed';
    } else {
        $ins = $pdo->prepare("INSERT INTO foro_likes (tema_id, usuario_id) VALUES (?, ?)");
        $ins->execute([$temaId, $usuarioId]);
        $status = 'like_added';
    }
} catch (Throwable $e) {
    $status = 'like_error';
}

$fallback = "tema_detalle.php?id={$temaId}&status={$status}";
if ($redirect !== '' && strpos($redirect, 'tema_detalle.php') !== false) {
    header("Location: {$redirect}");
    exit();
}
header("Location: {$fallback}");
exit();
