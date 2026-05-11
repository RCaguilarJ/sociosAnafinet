<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

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

            header("Location: dashboard.php");
            exit();
        }

        header("Location: index.php?error=1");
        exit();
    }

    // 1. Buscamos al usuario por su email
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 2. Verificación (Para este caso específico con tus credenciales)
    $isValidPassword = false;
    if ($user) {
        $storedPassword = (string)($user['password'] ?? '');
        $isValidPassword = app_verify_password($password, $storedPassword);
    }

    if ($user && $isValidPassword) {
        if (app_password_needs_upgrade($storedPassword)) {
            try {
                $rehashStmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $rehashStmt->execute([app_hash_password($password), $user['id']]);
            } catch (Throwable $e) {
                // Si el rehash falla no bloqueamos el acceso del usuario.
            }
        }

        session_regenerate_id(true);
        // Creamos la sesión con los datos del diseño de Figma
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_rol'] = $user['rol'];
        $_SESSION['user_estatus'] = $user['estatus'] ?? '';

        // Redirigir al Dashboard
        header("Location: dashboard.php");
        exit();
    } else {
        // Si falla, regresamos al login con un error
        header("Location: index.php?error=1");
        exit();
    }
}
