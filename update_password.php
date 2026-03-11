<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $current_pass = $_POST['current'];
    $new_pass = $_POST['new'];
    $confirm_pass = $_POST['confirm'];
    $user_id = $_SESSION['user_id'];

    // 1. Obtener la contraseña actual de la BD
    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // 2. Validaciones
    if ($new_pass !== $confirm_pass) {
        header("Location: perfil.php?tab=seguridad&error=Las contraseñas no coinciden");
        exit();
    }

    // Nota: Si usas texto plano (como en nuestro inicio), usa: if($current_pass == $user['password'])
    // Lo ideal es usar password_verify si ya has encriptado las contraseñas
    if ($current_pass === $user['password']) {
        // 3. Actualizar contraseña
        $update = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $update->execute([$new_pass, $user_id]);
        
        header("Location: perfil.php?tab=seguridad&success=Contraseña actualizada correctamente");
    } else {
        header("Location: perfil.php?tab=seguridad&error=La contraseña actual es incorrecta");
    }
    exit();
}