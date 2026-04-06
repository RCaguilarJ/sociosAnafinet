<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paso = isset($_GET['paso']) ? (int)$_GET['paso'] : 1;

    $email_com_valido = function (string $email): bool {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $emailLower = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
        return substr($emailLower, -4) === '.com';
    };

    // Guardamos los datos recibidos en la sesión global de afiliación
    if (!isset($_SESSION['afiliacion'])) {
        $_SESSION['afiliacion'] = [];
    }

    // Fusionamos los datos nuevos con los que ya existían
    $_SESSION['afiliacion']["paso$paso"] = $_POST;

    if ($paso === 2) {
        $email = $_POST['email'] ?? '';
        if (!$email_com_valido($email)) {
            $_SESSION['afiliacion_error'] = 'El correo debe ser válido y terminar en .com.';
            header("Location: index.php?paso=2");
            exit();
        }
        unset($_SESSION['afiliacion_error']);
    }

    // Redirigimos al siguiente paso
    $siguiente = $paso + 1;
    header("Location: index.php?paso=$siguiente");
    exit();
}
