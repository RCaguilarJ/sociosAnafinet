<?php

require_once dirname(__DIR__) . '/db.php';

const WP_USERS_IMPORT_TABLE = 'wp_users_import';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "No hay conexion a la base de datos configurada para ejecutar la importacion.\n");
    exit(1);
}

$sqlPath = resolve_sql_path($argv[1] ?? null);
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
    $keepStaging = in_array('--keep-staging', $argv, true);

    $pdo->exec('DROP TABLE IF EXISTS `' . WP_USERS_IMPORT_TABLE . '`');

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    ensure_import_source_columns($pdo, WP_USERS_IMPORT_TABLE);

    $syncSql = "
        INSERT INTO usuarios (nombre, email, password, rol, estatus, creado_at)
        SELECT
            COALESCE(NULLIF(display_name, ''), NULLIF(user_login, ''), user_email) AS nombre,
            user_email AS email,
            user_pass AS password,
            'Asociado' AS rol,
            'Activo' AS estatus,
            CASE
                WHEN user_registered IS NULL OR user_registered IN ('0000-00-00 00:00:00', '')
                    THEN NOW()
                ELSE user_registered
            END AS creado_at
        FROM `" . WP_USERS_IMPORT_TABLE . "`
        WHERE user_email IS NOT NULL
          AND TRIM(user_email) <> ''
        ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            password = VALUES(password),
            rol = 'Asociado',
            estatus = 'Activo'
    ";

    $affectedRows = $pdo->exec($syncSql);
    $importedUsers = (int)$pdo->query('SELECT COUNT(*) FROM `' . WP_USERS_IMPORT_TABLE . '`')->fetchColumn();

    if (!$keepStaging) {
        $pdo->exec('DROP TABLE IF EXISTS `' . WP_USERS_IMPORT_TABLE . '`');
    }

    echo "Archivo: {$sqlPath}\n";
    echo "Tabla detectada: {$sourceTable}\n";
    echo "Usuarios leidos desde el dump: {$importedUsers}\n";
    echo "Filas afectadas en usuarios: " . (int)$affectedRows . "\n";
    echo "Tabla staging conservada: " . ($keepStaging ? 'si' : 'no') . "\n";
    echo "Importacion completada.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error durante la importacion: {$e->getMessage()}\n");
    exit(1);
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
