<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');

$demoCredentials = app_demo_credentials();
$demoEmail = (string)$demoCredentials['email'];
$demoPassword = (string)$demoCredentials['password'];
$allowDemoLogin = app_demo_login_available();

if (!($pdo instanceof PDO) && $allowDemoLogin) {
    if (hash_equals($demoEmail, $email) && hash_equals($demoPassword, $password)) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Asociado Demo';
        $_SESSION['user_rol'] = 'Asociado';
        $_SESSION['demo_mode'] = true;

        header('Location: dashboard.php');
        exit();
    }

    header('Location: index.php?error=1');
    exit();
}

if (!($pdo instanceof PDO)) {
    header('Location: index.php?error=db');
    exit();
}

try {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $storedPassword = '';
    $isValidPassword = false;

    if ($user) {
        $storedPassword = (string)($user['password'] ?? '');
        $isValidPassword = app_verify_password($password, $storedPassword);
    }

    if (!$user || !$isValidPassword) {
        header('Location: index.php?error=1');
        exit();
    }

    if (app_password_needs_upgrade($storedPassword)) {
        try {
            $rehashStmt = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
            $rehashStmt->execute([app_hash_password($password), $user['id']]);
        } catch (Throwable $e) {
            // No bloqueamos el acceso si el rehash falla.
        }
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['nombre'];
    $_SESSION['user_rol'] = $user['rol'];
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_estatus'] = $user['estatus'] ?? '';
    $_SESSION['master_access'] = app_email_is_master((string)($_SESSION['user_email'] ?? ''));

    header('Location: dashboard.php');
    exit();
} catch (Throwable $e) {
    header('Location: index.php?error=db');
    exit();
}
