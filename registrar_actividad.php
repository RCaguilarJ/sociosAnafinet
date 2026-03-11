<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(204);
    exit();
}

$input = $_POST + $_GET;
$tipo = trim((string)($input['tipo'] ?? ''));
$detalle = trim((string)($input['detalle'] ?? ''));

if ($tipo === '') {
    http_response_code(204);
    exit();
}

if (strlen($detalle) > 255) {
    $detalle = substr($detalle, 0, 255);
}

try {
    $stmt = $pdo->prepare("INSERT INTO actividad_usuario (usuario_id, tipo_accion, detalle, creado_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $tipo, $detalle]);
} catch (Throwable $e) {
    // Silencioso para no bloquear la navegaci&oacute;n
}

http_response_code(204);
exit();
