<?php
ob_start();

if (!function_exists('render_dashboard_failure_page')) {
    function render_dashboard_failure_page(string $message, ?string $details = null): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error del dashboard</title>
            <style>
                body {
                    margin: 0;
                    font-family: Arial, sans-serif;
                    background: #f8fafc;
                    color: #0f172a;
                }
                .wrap {
                    max-width: 860px;
                    margin: 48px auto;
                    padding: 0 20px;
                }
                .card {
                    background: #fff;
                    border: 1px solid #fecaca;
                    border-radius: 18px;
                    padding: 24px;
                    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
                }
                h1 {
                    margin: 0 0 12px;
                    font-size: 28px;
                }
                p {
                    margin: 0 0 12px;
                    line-height: 1.6;
                }
                pre {
                    margin: 16px 0 0;
                    padding: 16px;
                    overflow: auto;
                    border-radius: 12px;
                    background: #0f172a;
                    color: #e2e8f0;
                    white-space: pre-wrap;
                    word-break: break-word;
                }
            </style>
        </head>
        <body>
            <div class="wrap">
                <div class="card">
                    <h1>El dashboard fallo en produccion</h1>
                    <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p>Corrige el error indicado abajo y vuelve a cargar la pagina.</p>
                    <?php if ($details !== null && $details !== ''): ?>
                        <pre><?php echo htmlspecialchars($details, ENT_QUOTES, 'UTF-8'); ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    $details = sprintf(
        'Fatal error: %s in %s on line %d',
        (string)($error['message'] ?? 'Error desconocido'),
        (string)($error['file'] ?? 'archivo desconocido'),
        (int)($error['line'] ?? 0)
    );

    error_log('Dashboard fatal error: ' . $details);
    render_dashboard_failure_page('Se produjo un error fatal al cargar el dashboard.', $details);
});

try {
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/role_helpers.php';

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');

    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }

    $videos_count = 0;
    $docs_count = 0;
    $asociados_count = 0;
    $foro_count = 0;
    $dashboardStatsUnavailable = false;
    $demoMode = !($pdo instanceof PDO) && app_demo_mode_enabled();
    $userStatus = $_SESSION['user_estatus'] ?? '';
    $masterAccess = current_user_has_master_access($pdo ?? null, (int)($_SESSION['user_id'] ?? 0));

    if ($pdo instanceof PDO) {
        try {
            $dbStatus = fetch_user_status($pdo, (int)($_SESSION['user_id'] ?? 0));
            if ($dbStatus !== null) {
                $userStatus = $dbStatus;
                $_SESSION['user_estatus'] = $dbStatus;
            }

            $videos_count = (int)$pdo->query("SELECT COUNT(*) FROM contenidos WHERE tipo = 'video'")->fetchColumn();
            $docs_count = (int)$pdo->query("SELECT COUNT(*) FROM contenidos WHERE tipo = 'documento'")->fetchColumn();
            $asociados_count = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado'")->fetchColumn();
            $foro_count = (int)$pdo->query("SELECT COUNT(*) FROM contenidos WHERE tipo = 'foro'")->fetchColumn();
        } catch (Throwable $e) {
            $dashboardStatsUnavailable = true;
            error_log('Dashboard stats unavailable: ' . $e->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('Dashboard bootstrap error: ' . $e->getMessage());
    render_dashboard_failure_page(
        'Se produjo una excepcion al inicializar el dashboard.',
        sprintf('%s in %s on line %d', $e->getMessage(), $e->getFile(), $e->getLine())
    );
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Dashboard - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
<?php
try {
    $activePage = 'dashboard';
    require __DIR__ . '/menu.php';
} catch (Throwable $e) {
    error_log('Dashboard menu error: ' . $e->getMessage());
    render_dashboard_failure_page(
        'El dashboard fallo al cargar el menu.',
        sprintf('%s in %s on line %d', $e->getMessage(), $e->getFile(), $e->getLine())
    );
}
?>

    <main class="md:ml-64 p-8">
        <?php if ($demoMode): ?>
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Modo demo activo: la base de datos no esta conectada en Vercel. Solo se habilito el acceso temporal con credenciales demo.
            </div>
        <?php endif; ?>

        <?php if ($dashboardStatsUnavailable): ?>
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Algunas metricas del dashboard no estuvieron disponibles. Revisa la tabla <code>contenidos</code> y los permisos del usuario de base de datos en produccion.
            </div>
        <?php endif; ?>

        <?php if (!$masterAccess && $userStatus === 'Pendiente de pago'): ?>
            <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="font-bold">Tu acceso es parcial hasta confirmar el pago.</p>
                        <p class="mt-1 text-blue-800">Puedes continuar en tu perfil y en confirmar pago. Las demas secciones mostraran acceso restringido hasta que tu membresia quede registrada.</p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/confirmar_pago.php" class="inline-flex items-center justify-center rounded-xl bg-[#5282B2] px-5 py-3 font-bold text-white hover:bg-blue-700 transition">
                        Confirmar pago
                    </a>
                </div>
            </div>
        <?php elseif (!$masterAccess && $userStatus === 'Pago reportado'): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
                Tu pago ya fue reportado. Mientras se valida, algunas secciones seguiran restringidas hasta que la membresia quede activa.
            </div>
        <?php endif; ?>

        <header class="mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Bienvenido, <?php echo htmlspecialchars((string)($_SESSION['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="text-gray-500">Accede a todos los recursos y contenido exclusivo.</p>
        </header>

        <div class="grid grid-cols-1 gap-6 mb-10 sm:grid-cols-2 lg:grid-cols-4">
            <a href="<?php echo BASE_URL; ?>/biblioteca_videos.php" class="bg-[#5282B2] p-6 rounded-2xl text-white shadow-lg relative overflow-hidden block hover:opacity-95 transition">
                <p class="text-sm opacity-80">Videos Disponibles</p>
                <h2 class="text-3xl font-bold">Canal</h2>
                <p class="text-xs opacity-80 mt-1">Ver canal completo</p>
                <i class="fa-solid fa-video absolute right-4 bottom-4 text-4xl opacity-20"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>/biblioteca_archivos.php" class="bg-[#E67E22] p-6 rounded-2xl text-white shadow-lg relative overflow-hidden block hover:opacity-95 transition">
                <p class="text-sm opacity-80">Documentos</p>
                <h2 class="text-3xl font-bold counter" data-target="<?php echo $docs_count; ?>"><?php echo number_format($docs_count); ?></h2>
                <i class="fa-solid fa-file-lines absolute right-4 bottom-4 text-4xl opacity-20"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>/lista_asociados.php" class="bg-[#9B59B6] p-6 rounded-2xl text-white shadow-lg relative overflow-hidden block hover:opacity-95 transition">
                <p class="text-sm opacity-80">Asociados Activos</p>
                <h2 class="text-3xl font-bold counter" data-target="<?php echo $asociados_count; ?>"><?php echo number_format($asociados_count); ?></h2>
                <i class="fa-solid fa-users absolute right-4 bottom-4 text-4xl opacity-20"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>/foro.php" class="bg-[#2ECC71] p-6 rounded-2xl text-white shadow-lg relative overflow-hidden block hover:opacity-95 transition">
                <p class="text-sm opacity-80">Temas del Foro</p>
                <h2 class="text-3xl font-bold counter" data-target="<?php echo $foro_count; ?>"><?php echo number_format($foro_count); ?></h2>
                <i class="fa-solid fa-comments absolute right-4 bottom-4 text-4xl opacity-20"></i>
            </a>
        </div>

        <section class="space-y-8">
            <div>
                <h3 class="text-lg font-bold text-gray-800 mb-4">Acceso Rapido</h3>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <a href="<?php echo BASE_URL; ?>/biblioteca_videos.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-blue-500 flex items-center justify-center text-white">
                            <i class="fa-solid fa-video"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Biblioteca de Videos</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/biblioteca_archivos.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-green-500 flex items-center justify-center text-white">
                            <i class="fa-regular fa-file-lines"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Biblioteca de Archivos</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/lista_asociados.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-purple-500 flex items-center justify-center text-white">
                            <i class="fa-solid fa-users"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Lista de Asociados</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/links_interes.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-orange-500 flex items-center justify-center text-white">
                            <i class="fa-solid fa-link"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Links de Interes</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/foro.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-emerald-500 flex items-center justify-center text-white">
                            <i class="fa-regular fa-comments"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Foro Fiscal</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/confirmar_pago.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-slate-500 flex items-center justify-center text-white">
                            <i class="fa-solid fa-credit-card"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Confirmar Pago</span>
                    </a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
