<?php
require_once 'config.php';
session_start(); // Localizamos la sesión actual
session_unset(); // Limpiamos todas las variables de sesión
session_destroy(); // Destruimos la sesión físicamente en el servidor

// Redirigimos al login con un mensaje de confirmación
header("Location: " . BASE_URL . "/index.php?status=logout");
exit();
?>
