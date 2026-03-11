<?php
require_once 'config.php';
// Ruta base del proyecto (ajusta si se despliega en subcarpeta)
if (!defined('BASE_URL')) {
    $baseUrl = getenv('BASE_URL');
    if ($baseUrl === false || $baseUrl === '') {
        $baseUrl = '/asociadosAnafinet';
    }
    define('BASE_URL', rtrim($baseUrl, '/'));
}

// Configuración de la base de datos
$host = 'localhost';
$db   = 'anafinet_db';
$user = 'root'; // Cambia esto según tu configuración de XAMPP/Laragon
$pass = '';     // Cambia esto si tienes contraseña en MySQL
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>


