<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: foro.php");
    exit();
}

$temaId = isset($_POST['tema_id']) ? (int)$_POST['tema_id'] : 0;
$contenido = trim($_POST['contenido'] ?? '');
$usuario_id = (int)($_SESSION['user_id'] ?? 0);

if ($temaId <= 0 || $contenido === '' || $usuario_id <= 0) {
    header("Location: tema_detalle.php?id={$temaId}&error=campos_vacios#respuestas");
    exit();
}

if (function_exists('mb_substr')) {
    $contenido = mb_substr($contenido, 0, 5000, 'UTF-8');
} else {
    $contenido = substr($contenido, 0, 5000);
}

try {
    $pdo->beginTransaction();

    $stmtTema = $pdo->prepare("SELECT titulo FROM foro_temas WHERE id = ? LIMIT 1");
    $stmtTema->execute([$temaId]);
    $tema = $stmtTema->fetch();
    if (!$tema) {
        $pdo->rollBack();
        header("Location: foro.php?error=tema_no_encontrado");
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO foro_respuestas (tema_id, usuario_id, respuesta) VALUES (?, ?, ?)");
    $stmt->execute([$temaId, $usuario_id, $contenido]);

    $detalle = 'Respuesta en tema: ' . (string)$tema['titulo'];
    $stmtAct = $pdo->prepare("INSERT INTO actividad_usuario (usuario_id, tipo_accion, detalle) VALUES (?, 'foro_participacion', ?)");
    $stmtAct->execute([$usuario_id, $detalle]);

    $pdo->commit();
    header("Location: tema_detalle.php?id={$temaId}&status=respuesta_creada#respuestas");
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header("Location: tema_detalle.php?id={$temaId}&error=db_error#respuestas");
    exit();
}
