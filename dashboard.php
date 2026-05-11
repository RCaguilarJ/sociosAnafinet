<?php
require_once __DIR__ . '/bootstrap.php';
require_once 'role_helpers.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$videos_count = 0;
$docs_count = 0;
$asociados_count = 0;
$foro_count = 0;
$demoMode = !($pdo instanceof PDO) && app_demo_mode_enabled();
$userStatus = $_SESSION['user_estatus'] ?? '';

if ($pdo instanceof PDO) {
    $dbStatus = fetch_user_status($pdo, (int)($_SESSION['user_id'] ?? 0));
    if ($dbStatus !== null) {
        $userStatus = $dbStatus;
        $_SESSION['user_estatus'] = $dbStatus;
    }
    $videos_count = $pdo->query("SELECT COUNT(*) FROM contenidos WHERE tipo = 'video'")->fetchColumn();
    $docs_count = $pdo->query("SELECT COUNT(*) FROM contenidos WHERE tipo = 'documento'")->fetchColumn();
    $asociados_count = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado'")->fetchColumn();
    $foro_count = $pdo->query("SELECT COUNT(*) FROM contenidos WHERE tipo = 'foro'")->fetchColumn();
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
    $activePage = 'dashboard';
    require 'menu.php';
?>

    <main class="md:ml-64 p-8">
        <?php if ($demoMode): ?>
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Modo demo activo: la base de datos no esta conectada en Vercel. Solo se habilito el acceso temporal con credenciales demo.
            </div>
        <?php endif; ?>

        <?php if ($userStatus === 'Pendiente de pago'): ?>
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
        <?php elseif ($userStatus === 'Pago reportado'): ?>
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
                Tu pago ya fue reportado. Mientras se valida, algunas secciones seguiran restringidas hasta que la membresia quede activa.
            </div>
        <?php endif; ?>

        <header class="mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Bienvenido, <?php echo $_SESSION['user_name']; ?></h1>
            <p class="text-gray-500">Accede a todos los recursos y contenido exclusivo.</p>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
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
