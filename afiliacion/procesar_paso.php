<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paso = isset($_GET['paso']) ? (int)$_GET['paso'] : 1;

    // Guardamos los datos recibidos en la sesión global de afiliación
    if (!isset($_SESSION['afiliacion'])) {
        $_SESSION['afiliacion'] = [];
    }

    // Fusionamos los datos nuevos con los que ya existían
    $_SESSION['afiliacion']["paso$paso"] = $_POST;

    // Redirigimos al siguiente paso
    $siguiente = $paso + 1;
    header("Location: index.php?paso=$siguiente");
    exit();
}