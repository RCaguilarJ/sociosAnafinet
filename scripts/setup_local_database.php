<?php

declare(strict_types=1);

try {
    $pdo = new PDO(
        'mysql:host=localhost;port=3306;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );

    $statements = [
        "CREATE DATABASE IF NOT EXISTS anafinet_anafinet_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
        "CREATE USER IF NOT EXISTS 'anafinet_foro'@'localhost' IDENTIFIED BY 'anafinet2026'",
        "ALTER USER 'anafinet_foro'@'localhost' IDENTIFIED BY 'anafinet2026'",
        "GRANT ALL PRIVILEGES ON anafinet_anafinet_db.* TO 'anafinet_foro'@'localhost'",
        "FLUSH PRIVILEGES",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    echo "DB_SETUP_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
