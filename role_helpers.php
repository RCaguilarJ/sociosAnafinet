<?php
function normalize_text_value(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $normalized = function_exists('app_ascii_transliterate') ? app_ascii_transliterate($lower) : $lower;
    $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);
    return $normalized ?? '';
}

function is_admin_role(string $role): bool
{
    $normalized = normalize_text_value($role);
    if ($normalized === '') {
        return false;
    }
    if ($normalized === 'admin' || $normalized === 'administrador') {
        return true;
    }
    return str_contains($normalized, 'admin');
}

function fetch_user_role(?PDO $pdo, ?int $userId): ?string
{
    if (!$pdo || !$userId) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (is_array($row) && isset($row['rol'])) {
            return (string)$row['rol'];
        }
    } catch (Throwable $e) {
        return null;
    }

    return null;
}

function fetch_user_status(?PDO $pdo, ?int $userId): ?string
{
    if (!$pdo || !$userId) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT estatus FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (is_array($row) && isset($row['estatus'])) {
            return (string)$row['estatus'];
        }
    } catch (Throwable $e) {
        return null;
    }

    return null;
}

function fetch_user_email(?PDO $pdo, ?int $userId): ?string
{
    if (!$pdo || !$userId) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT email FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (is_array($row) && isset($row['email'])) {
            return (string)$row['email'];
        }
    } catch (Throwable $e) {
        return null;
    }

    return null;
}

function current_user_has_master_access(?PDO $pdo, ?int $userId = null): bool
{
    $email = (string)($_SESSION['user_email'] ?? '');
    if ($email === '') {
        $resolvedUserId = $userId ?? (int)($_SESSION['user_id'] ?? 0);
        $dbEmail = fetch_user_email($pdo, $resolvedUserId);
        if ($dbEmail !== null) {
            $email = $dbEmail;
            $_SESSION['user_email'] = $dbEmail;
        }
    }

    $hasAccess = app_email_is_master($email);
    if ($hasAccess) {
        $_SESSION['master_access'] = true;
    }

    return $hasAccess;
}

function is_pending_payment_status(string $status): bool
{
    return normalize_text_value($status) === 'pendientedepago';
}

function user_has_profile_only_access(?PDO $pdo, ?int $userId, ?string $role = null, ?string $status = null): bool
{
    $role = $role ?? (string)($_SESSION['user_rol'] ?? '');
    if (is_admin_role($role)) {
        return false;
    }

    if (!empty($_SESSION['demo_mode'])) {
        return false;
    }

    if (current_user_has_master_access($pdo, $userId)) {
        return false;
    }

    if ($status === null || $status === '') {
        $status = (string)($_SESSION['user_estatus'] ?? '');
        $dbStatus = fetch_user_status($pdo, $userId);
        if ($dbStatus !== null) {
            $status = $dbStatus;
            $_SESSION['user_estatus'] = $dbStatus;
        }
    }

    if (function_exists('app_is_membership_restricted_status')) {
        return app_is_membership_restricted_status($status);
    }

    return is_pending_payment_status($status);
}

function render_membership_required_page(string $activePage, string $pageTitle = 'Función bloqueada'): void
{
    http_response_code(403);
    require_once __DIR__ . '/config.php';

    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Anafinet</title>
    </head>
    <body class="bg-slate-50 min-h-screen">
        <?php require __DIR__ . '/menu.php'; ?>
        <main class="md:ml-64 p-6 md:p-8">
            <div class="mx-auto max-w-4xl">
                <div class="rounded-[2rem] border border-amber-200 bg-amber-50 p-8 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-amber-600 shadow-sm">
                            <i class="fa-solid fa-lock text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold uppercase tracking-[0.18em] text-amber-700">Acceso restringido</p>
                            <h1 class="mt-2 text-3xl font-bold text-slate-900">Pague membresía para habilitar esta función</h1>
                            <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-700">
                                Esta sección se habilitará cuando tu pago quede registrado. Mientras tanto, puedes continuar en tu perfil y confirmar tu pago con comprobante.
                            </p>
                            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                                <a href="<?php echo BASE_URL; ?>/confirmar_pago.php" class="inline-flex items-center justify-center rounded-2xl bg-[#5282B2] px-6 py-4 font-bold text-white hover:bg-blue-700 transition">
                                    Confirmar pago
                                </a>
                                <a href="javascript:history.back()" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-4 font-bold text-slate-700 hover:bg-slate-50 transition">
                                    Volver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit();
}

function require_full_portal_access(?PDO $pdo, string $activePage = 'dashboard', string $pageTitle = 'Función bloqueada'): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }

    require_database_connection($pdo, $activePage, $pageTitle);

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $role = (string)($_SESSION['user_rol'] ?? '');

    if (user_has_profile_only_access($pdo, $userId, $role)) {
        render_membership_required_page($activePage, $pageTitle);
    }
}

function render_database_unavailable_page(string $activePage = 'dashboard', string $pageTitle = 'Servicio no disponible'): void
{
    http_response_code(503);
    $isAuthenticated = isset($_SESSION['user_id']);
    $pdo = null;

    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Anafinet</title>
    </head>
    <body class="bg-slate-50 min-h-screen">
        <?php if ($isAuthenticated): ?>
            <?php require __DIR__ . '/menu.php'; ?>
        <?php endif; ?>
        <main class="<?php echo $isAuthenticated ? 'md:ml-64 p-6 md:p-8' : 'flex min-h-screen items-center justify-center p-4'; ?>">
            <div class="mx-auto max-w-4xl">
                <div class="rounded-[2rem] border border-red-200 bg-white p-8 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600 shadow-sm">
                            <i class="fa-solid fa-database text-xl"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold uppercase tracking-[0.18em] text-red-700">Servicio temporalmente no disponible</p>
                            <h1 class="mt-2 text-3xl font-bold text-slate-900">No fue posible conectar con la base de datos</h1>
                            <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-700">
                                Esta seccion necesita conexion con la base de datos para cargar contenido o guardar cambios.
                                Cuando el servicio vuelva a estar disponible, la pagina funcionara normalmente.
                            </p>
                            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="inline-flex items-center justify-center rounded-2xl bg-[#5282B2] px-6 py-4 font-bold text-white hover:bg-blue-700 transition">
                                    Ir al dashboard
                                </a>
                                <a href="javascript:location.reload()" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-4 font-bold text-slate-700 hover:bg-slate-50 transition">
                                    Reintentar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit();
}

function require_database_connection(?PDO $pdo, string $activePage = 'dashboard', string $pageTitle = 'Servicio no disponible'): void
{
    if ($pdo instanceof PDO) {
        return;
    }

    if (!headers_sent()) {
        header('Retry-After: 300');
    }

    render_database_unavailable_page($activePage, $pageTitle);
}

function ensure_user_payment_columns(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $requiredColumns = [
        'comprobante_pago' => "ALTER TABLE usuarios ADD COLUMN comprobante_pago VARCHAR(255) NULL",
        'referencia_pago' => "ALTER TABLE usuarios ADD COLUMN referencia_pago VARCHAR(120) NULL",
        'pago_reportado_at' => "ALTER TABLE usuarios ADD COLUMN pago_reportado_at DATETIME NULL",
    ];

    $columnExistsStmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'usuarios'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );

    foreach ($requiredColumns as $column => $alterSql) {
        $columnExistsStmt->execute([$column]);
        $exists = (bool) $columnExistsStmt->fetchColumn();
        if (!$exists) {
            $pdo->exec($alterSql);
        }
    }

    $initialized = true;
}
