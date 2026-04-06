<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('No autorizado');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $campo = $_GET['campo'] ?? '';
    $valor = isset($_GET['valor']) ? (int)$_GET['valor'] : 0;
    $user_id = $_SESSION['user_id'];

    $campos_permitidos = ['notif_email', 'notif_boletin', 'notif_eventos', 'notif_foro'];

    if (in_array($campo, $campos_permitidos, true)) {
        $sql = "UPDATE usuarios SET {$campo} = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        header('Content-Type: application/json; charset=UTF-8');

        if ($stmt->execute([$valor, $user_id])) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error']);
        }
    } else {
        http_response_code(400);
    }
}
