<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // 1. Buscamos al usuario por su email
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 2. Verificación (Para este caso específico con tus credenciales)
    $isValidPassword = false;
    if ($user) {
        $storedPassword = (string)($user['password'] ?? '');
        $passwordInfo = password_get_info($storedPassword);
        if (!empty($passwordInfo['algo'])) {
            $isValidPassword = password_verify($password, $storedPassword);
        } else {
            $isValidPassword = hash_equals($storedPassword, $password);
        }
    }

    if ($user && $isValidPassword) {
        session_regenerate_id(true);
        // Creamos la sesión con los datos del diseño de Figma
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_rol'] = $user['rol'];

        // Redirigir al Dashboard
        header("Location: dashboard.php");
        exit();
    } else {
        // Si falla, regresamos al login con un error
        header("Location: index.php?error=1");
        exit();
    }
}
