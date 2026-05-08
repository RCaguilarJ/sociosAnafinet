<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?paso=1');
    exit();
}

$paso = isset($_GET['paso']) ? (int) $_GET['paso'] : 1;
if (!in_array($paso, [1, 2], true)) {
    header('Location: index.php?paso=1');
    exit();
}

$email_com_valido = function (string $email): bool {
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $emailLower = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
    return substr($emailLower, -4) === '.com';
};

if (!isset($_SESSION['afiliacion'])) {
    $_SESSION['afiliacion'] = [];
}

$_SESSION['afiliacion']["paso$paso"] = $_POST;

if ($paso === 1) {
    $email = $_POST['email'] ?? '';
    if (!$email_com_valido($email)) {
        $_SESSION['afiliacion_error'] = 'El correo debe ser válido y terminar en .com.';
        header('Location: index.php?paso=1');
        exit();
    }
    unset($_SESSION['afiliacion_error']);
}

$siguiente = $paso + 1;
header("Location: index.php?paso=$siguiente");
exit();
