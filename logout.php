<?php
session_start(); // Localizamos la sesión actual
session_unset(); // Limpiamos todas las variables de sesión
session_destroy(); // Destruimos la sesión físicamente en el servidor

// Redirigimos al login con un mensaje de confirmación
header("Location: index.php?status=logout");
exit();
?>