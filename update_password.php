<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    if (!($pdo instanceof PDO)) {
        header("Location: perfil.php?tab=seguridad&error=El servicio no esta disponible en este momento");
        exit();
    }

    $current_pass = $_POST['current'];
    $new_pass = $_POST['new'];
    $confirm_pass = $_POST['confirm'];
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($new_pass !== $confirm_pass) {
        header("Location: perfil.php?tab=seguridad&error=Las contraseñas no coinciden");
        exit();
    }

    if ($user && app_verify_password($current_pass, (string)$user['password'])) {
        $update = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $update->execute([app_hash_password($new_pass), $user_id]);

        header("Location: perfil.php?tab=seguridad&success=Contraseña actualizada correctamente");
    } else {
        header("Location: perfil.php?tab=seguridad&error=La contraseña actual es incorrecta");
    }
    exit();
}
