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

$titulo = trim($_POST['titulo'] ?? '');
$categoria = trim($_POST['categoria'] ?? 'General');
$contenido = trim($_POST['contenido'] ?? '');
$usuario_id = (int)($_SESSION['user_id'] ?? 0);

if ($titulo === '' || $contenido === '' || $usuario_id <= 0) {
    header("Location: foro.php?error=campos_vacios");
    exit();
}

if ($categoria === '') {
    $categoria = 'General';
}

if (function_exists('mb_substr')) {
    $titulo = mb_substr($titulo, 0, 120, 'UTF-8');
    $categoria = mb_substr($categoria, 0, 60, 'UTF-8');
    $contenido = mb_substr($contenido, 0, 5000, 'UTF-8');
} else {
    $titulo = substr($titulo, 0, 120);
    $categoria = substr($categoria, 0, 60);
    $contenido = substr($contenido, 0, 5000);
}

try {
    $stmt = $pdo->prepare("INSERT INTO foro_temas (usuario_id, titulo, categoria, contenido) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$usuario_id, $titulo, $categoria, $contenido])) {
        header("Location: foro.php?status=tema_creado");
        exit();
    }
    header("Location: foro.php?error=db_error");
    exit();
} catch (Throwable $e) {
    header("Location: foro.php?error=db_error");
    exit();
}
