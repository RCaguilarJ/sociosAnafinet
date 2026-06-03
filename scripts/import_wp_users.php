<?php

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/password_helpers.php';

const WP_USERS_IMPORT_TABLE = 'wp_users_import';

$options = parse_cli_options($argv);

if (!empty($options['help'])) {
    print_help();
    exit(0);
}

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "No hay conexion a la base de datos configurada para ejecutar la importacion.\n");
    exit(1);
}

$sqlPath = resolve_sql_path($options['path']);
if ($sqlPath === null) {
    fwrite(STDERR, "No encontre el dump. Coloca el archivo en database/wp_users (1).sql o pasa la ruta como argumento.\n");
    exit(1);
}

$sqlContents = file_get_contents($sqlPath);
if ($sqlContents === false || trim($sqlContents) === '') {
    fwrite(STDERR, "No fue posible leer el archivo SQL: {$sqlPath}\n");
    exit(1);
}

$sourceTable = detect_users_table_name($sqlContents);
if ($sourceTable === null) {
    fwrite(STDERR, "No pude detectar la tabla origen dentro del dump.\n");
    exit(1);
}

$rewrittenSql = rewrite_dump_table_name($sqlContents, $sourceTable, WP_USERS_IMPORT_TABLE);
$statements = split_sql_statements($rewrittenSql);

if ($statements === []) {
    fwrite(STDERR, "El dump no contiene sentencias SQL ejecutables.\n");
    exit(1);
}

try {
    $pdo->exec('DROP TABLE IF EXISTS `' . WP_USERS_IMPORT_TABLE . '`');

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    ensure_import_source_columns($pdo, WP_USERS_IMPORT_TABLE);

    $rows = fetch_import_rows($pdo);
    $sharedPasswordHash = null;

    if ($options['generic_password'] !== null) {
        $sharedPasswordHash = app_hash_password($options['generic_password']);
    }

    if ($options['output'] !== null) {
        $writtenRows = write_sql_output($rows, $options['output'], $sharedPasswordHash);
        $operationSummary = 'Archivo SQL generado';
        $affectedRows = $writtenRows;
    } else {
        $affectedRows = sync_rows_to_usuarios($pdo, $rows, $sharedPasswordHash);
        $operationSummary = 'Importacion aplicada a la tabla usuarios';
    }

    $importedUsers = count($rows);

    if (!$options['keep_staging']) {
        $pdo->exec('DROP TABLE IF EXISTS `' . WP_USERS_IMPORT_TABLE . '`');
    }

    echo "Archivo: {$sqlPath}\n";
    echo "Tabla detectada: {$sourceTable}\n";
    echo "Usuarios leidos desde el dump: {$importedUsers}\n";
    echo "Resultado: {$operationSummary}\n";
    echo "Filas procesadas: {$affectedRows}\n";
    if ($options['output'] !== null) {
        echo "Salida SQL: {$options['output']}\n";
    }
    if ($options['generic_password'] !== null) {
        echo "Contrasena generica aplicada: si\n";
    }
    echo "Tabla staging conservada: " . ($options['keep_staging'] ? 'si' : 'no') . "\n";
    echo "Proceso completado.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error durante la importacion: {$e->getMessage()}\n");
    exit(1);
}

function parse_cli_options(array $argv): array
{
    $options = [
        'path' => null,
        'keep_staging' => false,
        'generic_password' => null,
        'output' => null,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--keep-staging') {
            $options['keep_staging'] = true;
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }

        if (str_starts_with($arg, '--generic-password=')) {
            $value = substr($arg, strlen('--generic-password='));
            $options['generic_password'] = $value !== '' ? $value : null;
            continue;
        }

        if (str_starts_with($arg, '--output=')) {
            $value = trim(substr($arg, strlen('--output=')));
            $options['output'] = $value !== '' ? $value : null;
            continue;
        }

        if ($options['path'] === null) {
            $options['path'] = $arg;
        }
    }

    return $options;
}

function print_help(): void
{
    echo "Uso:\n";
    echo "  php .\\scripts\\import_wp_users.php [ruta-al-dump.sql] [--keep-staging]\n";
    echo "  php .\\scripts\\import_wp_users.php [ruta-al-dump.sql] --generic-password=Anafinet2026!\n";
    echo "  php .\\scripts\\import_wp_users.php [ruta-al-dump.sql] --generic-password=Anafinet2026! --output=database\\usuarios_asociados_generic_password.sql\n";
    echo "\n";
    echo "Opciones:\n";
    echo "  --generic-password=...  Reemplaza todas las contrasenas importadas por una sola contrasena temporal.\n";
    echo "  --output=...            Genera un archivo SQL compatible con la tabla usuarios en lugar de importar directo.\n";
    echo "  --keep-staging          Conserva la tabla temporal wp_users_import para revision.\n";
    echo "  --help, -h              Muestra esta ayuda.\n";
}

function resolve_sql_path(?string $providedPath): ?string
{
    $candidates = array_filter([
        $providedPath,
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'wp_users (1).sql',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'wp_users (1).sql',
    ]);

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return realpath($candidate) ?: $candidate;
        }
    }

    return null;
}

function detect_users_table_name(string $sqlContents): ?string
{
    $patterns = [
        '/CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i',
        '/INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $sqlContents, $matches) === 1) {
            return $matches[1];
        }
    }

    return null;
}

function rewrite_dump_table_name(string $sqlContents, string $sourceTable, string $targetTable): string
{
    $patterns = [
        '/`' . preg_quote($sourceTable, '/') . '`/i',
        '/\b' . preg_quote($sourceTable, '/') . '\b/i',
    ];

    return preg_replace($patterns, ['`' . $targetTable . '`', $targetTable], $sqlContents) ?? $sqlContents;
}

function split_sql_statements(string $sqlContents): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sqlContents);
    $quote = null;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sqlContents[$i];
        $next = $i + 1 < $length ? $sqlContents[$i + 1] : '';
        $prev = $i > 0 ? $sqlContents[$i - 1] : '';

        if ($inLineComment) {
            $buffer .= $char;
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            $buffer .= $char;
            if ($prev === '*' && $char === '/') {
                $inBlockComment = false;
            }
            continue;
        }

        if ($quote !== null) {
            $buffer .= $char;
            if ($char === $quote && $prev !== '\\') {
                $quote = null;
            }
            continue;
        }

        if (($char === '-' && $next === '-') || $char === '#') {
            $inLineComment = true;
            $buffer .= $char;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $inBlockComment = true;
            $buffer .= $char;
            continue;
        }

        if ($char === '\'' || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

function ensure_import_source_columns(PDO $pdo, string $tableName): void
{
    $requiredColumns = ['user_login', 'user_pass', 'user_email'];
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?"
    );

    foreach ($requiredColumns as $column) {
        $stmt->execute([$tableName, $column]);
        $exists = (int)$stmt->fetchColumn() > 0;
        if (!$exists) {
            throw new RuntimeException("La tabla importada no contiene la columna requerida: {$column}");
        }
    }
}

function fetch_import_rows(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT user_login, user_pass, user_email, display_name, user_registered
         FROM `' . WP_USERS_IMPORT_TABLE . '`
         WHERE user_email IS NOT NULL AND TRIM(user_email) <> ""
         ORDER BY ID ASC'
    );

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function sync_rows_to_usuarios(PDO $pdo, array $rows, ?string $sharedPasswordHash): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password, rol, estatus, creado_at)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            password = VALUES(password),
            rol = VALUES(rol),
            estatus = VALUES(estatus),
            creado_at = VALUES(creado_at)'
    );

    $processed = 0;
    foreach ($rows as $row) {
        $stmt->execute([
            resolve_import_name($row),
            trim((string)($row['user_email'] ?? '')),
            resolve_import_password($row, $sharedPasswordHash),
            'Asociado',
            'Activo',
            resolve_import_created_at($row),
        ]);
        $processed++;
    }

    return $processed;
}

function write_sql_output(array $rows, string $outputPath, ?string $sharedPasswordHash): int
{
    $resolvedOutputPath = resolve_output_path($outputPath);
    $directory = dirname($resolvedOutputPath);

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('No fue posible crear la carpeta de salida para el archivo SQL.');
    }

    $lines = [];
    $lines[] = '-- Archivo generado automaticamente desde scripts/import_wp_users.php';
    $lines[] = '-- Compatible con la tabla usuarios del sistema Anafinet';
    $lines[] = '-- Si se uso una contrasena generica, el valor ya quedo hasheado en este archivo.';
    $lines[] = '-- Listo para importarse en produccion.';
    $lines[] = '';
    $lines[] = 'SET NAMES utf8mb4;';
    $lines[] = 'START TRANSACTION;';
    $lines[] = '';

    foreach ($rows as $row) {
        $lines[] = sprintf(
            "INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `estatus`, `creado_at`) VALUES (%s, %s, %s, 'Asociado', 'Activo', %s)\nON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `password` = VALUES(`password`), `rol` = VALUES(`rol`), `estatus` = VALUES(`estatus`), `creado_at` = VALUES(`creado_at`);",
            sql_quote(resolve_import_name($row)),
            sql_quote(trim((string)($row['user_email'] ?? ''))),
            sql_quote(resolve_import_password($row, $sharedPasswordHash)),
            sql_quote(resolve_import_created_at($row))
        );
        $lines[] = '';
    }

    $lines[] = 'COMMIT;';

    $content = implode(PHP_EOL, $lines);
    if (file_put_contents($resolvedOutputPath, $content) === false) {
        throw new RuntimeException('No fue posible escribir el archivo SQL de salida.');
    }

    return count($rows);
}

function resolve_output_path(string $outputPath): string
{
    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $outputPath) === 1) {
        return $outputPath;
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputPath);
}

function resolve_import_name(array $row): string
{
    $displayName = trim((string)($row['display_name'] ?? ''));
    if ($displayName !== '') {
        return $displayName;
    }

    $login = trim((string)($row['user_login'] ?? ''));
    if ($login !== '') {
        return $login;
    }

    return trim((string)($row['user_email'] ?? ''));
}

function resolve_import_password(array $row, ?string $sharedPasswordHash): string
{
    if ($sharedPasswordHash !== null) {
        return $sharedPasswordHash;
    }

    return (string)($row['user_pass'] ?? '');
}

function resolve_import_created_at(array $row): string
{
    $registered = trim((string)($row['user_registered'] ?? ''));
    if ($registered === '' || $registered === '0000-00-00 00:00:00') {
        return date('Y-m-d H:i:s');
    }

    return $registered;
}

function sql_quote(?string $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return "'" . str_replace(
        ['\\', "'", "\r", "\n"],
        ['\\\\', "\\'", '\\r', '\\n'],
        $value
    ) . "'";
}
