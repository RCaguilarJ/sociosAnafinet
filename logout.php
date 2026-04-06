<?php
require_once 'config.php';
require_once __DIR__ . '/bootstrap.php';
session_unset(); // Limpiamos todas las variables de sesión
session_destroy(); // Destruimos la sesión físicamente en el servidor

// Redirigimos al login con un mensaje de confirmación
header("Location: " . BASE_URL . "/index.php?status=logout");
exit();
?>
