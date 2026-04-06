<?php
require_once __DIR__ . '/config.php';

$charset = env_value('DB_CHARSET', 'utf8mb4');
$host = env_value('DB_HOST', 'localhost');
$port = env_value('DB_PORT', '3306');
$db = env_value('DB_NAME', 'anafinet_db');
$user = env_value('DB_USER', 'root');
$pass = env_value('DB_PASSWORD', '');

$databaseUrl = env_value('DATABASE_URL');
if ($databaseUrl !== null) {
    $parsed = parse_url($databaseUrl);
    if ($parsed === false || !isset($parsed['scheme'], $parsed['host'], $parsed['path'])) {
        throw new RuntimeException('La variable DATABASE_URL no tiene un formato valido.');
    }

    $host = (string)$parsed['host'];
    $port = isset($parsed['port']) ? (string)$parsed['port'] : $port;
    $db = ltrim((string)$parsed['path'], '/');
    $user = isset($parsed['user']) ? rawurldecode((string)$parsed['user']) : $user;
    $pass = isset($parsed['pass']) ? rawurldecode((string)$parsed['pass']) : $pass;

    parse_str((string)($parsed['query'] ?? ''), $queryParams);
    if (!empty($queryParams['charset'])) {
        $charset = (string)$queryParams['charset'];
    }
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    throw new PDOException(
        'No se pudo conectar a la base de datos. Revisa DATABASE_URL o las variables DB_HOST, DB_PORT, DB_NAME, DB_USER y DB_PASSWORD.',
        (int)$e->getCode(),
        $e
    );
}
?>
