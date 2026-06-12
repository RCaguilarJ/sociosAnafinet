<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/password_helpers.php';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "No hay conexion a la base de datos.\n");
    exit(1);
}

$statements = [
    "CREATE TABLE IF NOT EXISTS usuarios (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password VARCHAR(255) NOT NULL,
        rfc VARCHAR(20) NULL,
        curp VARCHAR(30) NULL,
        telefono VARCHAR(30) NULL,
        calle VARCHAR(150) NULL,
        numero VARCHAR(30) NULL,
        colonia VARCHAR(120) NULL,
        cp VARCHAR(15) NULL,
        ciudad VARCHAR(120) NULL,
        estado VARCHAR(120) NULL,
        empresa VARCHAR(150) NULL,
        puesto VARCHAR(150) NULL,
        especialidad VARCHAR(150) NULL,
        biografia TEXT NULL,
        cedula_profesional VARCHAR(80) NULL,
        foto_perfil VARCHAR(255) NULL,
        rol VARCHAR(50) NOT NULL DEFAULT 'Asociado',
        rol_solicitado VARCHAR(50) NULL,
        estatus VARCHAR(50) NOT NULL DEFAULT 'Activo',
        notif_email TINYINT(1) NOT NULL DEFAULT 1,
        notif_boletin TINYINT(1) NOT NULL DEFAULT 1,
        notif_eventos TINYINT(1) NOT NULL DEFAULT 1,
        notif_foro TINYINT(1) NOT NULL DEFAULT 1,
        comprobante_pago VARCHAR(255) NULL,
        referencia_pago VARCHAR(120) NULL,
        pago_reportado_at DATETIME NULL,
        membership_started_at DATETIME NULL,
        membership_expires_at DATETIME NULL,
        creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_usuarios_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS contenidos (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        url_recurso VARCHAR(255) NULL,
        fecha_publicacion DATE NULL,
        tema VARCHAR(100) NULL,
        creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_contenidos_tipo (tipo),
        KEY idx_contenidos_tema (tema)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS foro_temas (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        titulo VARCHAR(180) NOT NULL,
        categoria VARCHAR(100) NULL,
        contenido LONGTEXT NOT NULL,
        creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_foro_temas_usuario (usuario_id),
        KEY idx_foro_temas_categoria (categoria),
        CONSTRAINT fk_foro_temas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS foro_respuestas (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        tema_id INT NOT NULL,
        usuario_id INT NOT NULL,
        respuesta LONGTEXT NOT NULL,
        creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_foro_respuestas_tema (tema_id),
        KEY idx_foro_respuestas_usuario (usuario_id),
        CONSTRAINT fk_foro_respuestas_tema FOREIGN KEY (tema_id) REFERENCES foro_temas(id) ON DELETE CASCADE,
        CONSTRAINT fk_foro_respuestas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS foro_likes (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        tema_id INT NOT NULL,
        usuario_id INT NOT NULL,
        creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_foro_likes_tema_usuario (tema_id, usuario_id),
        KEY idx_foro_likes_usuario (usuario_id),
        CONSTRAINT fk_foro_likes_tema FOREIGN KEY (tema_id) REFERENCES foro_temas(id) ON DELETE CASCADE,
        CONSTRAINT fk_foro_likes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS links_interes (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(180) NOT NULL,
        descripcion TEXT NULL,
        url VARCHAR(255) NOT NULL,
        categoria VARCHAR(120) NULL,
        icono VARCHAR(80) NULL,
        creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_links_interes_categoria (categoria)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS actividad_usuario (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo_accion VARCHAR(80) NOT NULL,
        detalle VARCHAR(255) NULL,
        creado_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_actividad_usuario_usuario (usuario_id),
        KEY idx_actividad_usuario_tipo (tipo_accion),
        CONSTRAINT fk_actividad_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS app_notifications (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(60) NOT NULL,
        title VARCHAR(190) NOT NULL,
        message TEXT NOT NULL,
        url VARCHAR(255) NULL,
        dedupe_key VARCHAR(191) NULL,
        meta_json LONGTEXT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        read_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_app_notifications_user (user_id),
        KEY idx_app_notifications_user_read (user_id, is_read, created_at),
        UNIQUE KEY uq_app_notifications_user_dedupe (user_id, dedupe_key),
        CONSTRAINT fk_app_notifications_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

try {
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $adminEmail = env_value('MASTER_EMAIL', 'master@anafinet.com') ?: 'master@anafinet.com';
    $adminPassword = 'Admin123!';
    $adminHash = app_hash_password($adminPassword);

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nombre, email, password, rol, rol_solicitado, estatus, empresa, puesto, especialidad)
         VALUES (?, ?, ?, 'Administrador', 'Administrador', 'Activo', ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            password = VALUES(password),
            rol = 'Administrador',
            rol_solicitado = 'Administrador',
            estatus = 'Activo'"
    );
    $stmt->execute([
        'Administrador Local',
        $adminEmail,
        $adminHash,
        'Anafinet',
        'Administrador',
        'Fiscal',
    ]);

    $seedLinks = [
        ['SAT', 'Portal SAT', 'https://www.sat.gob.mx/', 'Instituciones Fiscales', 'fa-building-columns'],
        ['DOF', 'Diario Oficial de la Federacion', 'https://www.dof.gob.mx/', 'Legislacion y Normatividad', 'fa-scale-balanced'],
        ['IMSS', 'Portal IMSS', 'https://www.imss.gob.mx/', 'Instituciones Fiscales', 'fa-building-columns'],
    ];

    $linkStmt = $pdo->prepare(
        "INSERT INTO links_interes (titulo, descripcion, url, categoria, icono)
         SELECT ?, ?, ?, ?, ?
         WHERE NOT EXISTS (
            SELECT 1 FROM links_interes WHERE titulo = ? AND url = ?
         )"
    );

    foreach ($seedLinks as [$titulo, $descripcion, $url, $categoria, $icono]) {
        $linkStmt->execute([$titulo, $descripcion, $url, $categoria, $icono, $titulo, $url]);
    }

    echo "APP_SCHEMA_OK\n";
    echo "Admin email: {$adminEmail}\n";
    echo "Admin password: {$adminPassword}\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
