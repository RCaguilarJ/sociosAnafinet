<?php
require_once 'config.php';
require_once 'role_helpers.php';

$activePage = $activePage ?? '';
$userName = $_SESSION['user_name'] ?? 'Usuario';
$userRole = $_SESSION['user_rol'] ?? '';
$userStatus = $_SESSION['user_estatus'] ?? '';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (isset($pdo)) {
    $dbRole = fetch_user_role($pdo, $userId);
    if ($dbRole !== null) {
        $userRole = $dbRole;
    }
    $dbStatus = fetch_user_status($pdo, $userId);
    if ($dbStatus !== null) {
        $userStatus = $dbStatus;
        $_SESSION['user_estatus'] = $dbStatus;
    }
}

$menuItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => base_url('dashboard.php'), 'icon' => 'fa-house'],
    ['key' => 'perfil', 'label' => 'Mi Perfil', 'href' => base_url('perfil.php'), 'icon' => 'fa-user', 'iconStyle' => 'regular'],
    ['key' => 'videos', 'label' => 'Biblioteca de Videos', 'href' => base_url('biblioteca_videos.php'), 'icon' => 'fa-video'],
    ['key' => 'archivos', 'label' => 'Biblioteca de Archivos', 'href' => base_url('biblioteca_archivos.php'), 'icon' => 'fa-file-lines', 'iconStyle' => 'regular'],
    ['key' => 'asociados', 'label' => 'Lista de Asociados', 'href' => base_url('lista_asociados.php'), 'icon' => 'fa-users'],
    ['key' => 'links', 'label' => 'Links de Inter&eacute;s', 'href' => base_url('links_interes.php'), 'icon' => 'fa-link'],
    ['key' => 'foro', 'label' => 'Foro Fiscal', 'href' => base_url('foro.php'), 'icon' => 'fa-comments', 'iconStyle' => 'regular'],
    ['key' => 'foro_nuevo', 'label' => 'Nuevo Tema', 'href' => base_url('foro.php?nuevo=1'), 'icon' => 'fa-plus'],
];

$menuItems[] = ['key' => 'confirmar_pago', 'label' => 'Confirmar Pago', 'href' => base_url('confirmar_pago.php'), 'icon' => 'fa-credit-card'];

if (is_admin_role($userRole)) {
    $menuItems[] = ['key' => 'revisar_pagos', 'label' => 'Revisar Pagos', 'href' => base_url('revisar_pagos.php'), 'icon' => 'fa-receipt'];
    $menuItems[] = ['key' => 'subir_documentos', 'label' => 'Subir Documentos', 'href' => base_url('subir_archivo.php'), 'icon' => 'fa-cloud-arrow-up'];
    $menuItems[] = ['key' => 'links_admin', 'label' => 'Administrar Links', 'href' => base_url('links_interes_admin.php'), 'icon' => 'fa-pen-to-square'];
}

function menu_link_classes(string $key, string $activePage): string
{
    if ($key === $activePage) {
        return 'flex items-center gap-3 px-4 py-3 bg-blue-100 text-blue-700 rounded-xl font-semibold';
    }

    return 'flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-xl transition';
}
?>

<header class="md:hidden sticky top-0 z-30 bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
    <button id="menuOpen" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50" aria-controls="sideMenu" aria-label="Abrir men&uacute;">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="flex items-center gap-2">
        <img src="<?php echo htmlspecialchars(base_url('logo.avif'), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Anafinet" class="h-8 w-auto">
        <span class="font-semibold text-gray-800">Anafinet</span>
    </div>
    <div class="w-10 h-10"></div>
</header>

<div id="menuOverlay" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"></div>

<aside id="sideMenu" class="fixed top-0 left-0 h-screen w-72 max-w-[85vw] md:w-64 bg-white border-r border-gray-200 p-6 space-y-8 z-50 transform -translate-x-full md:translate-x-0 transition overflow-y-auto">
    <div class="flex items-start justify-between">
        <div class="text-center w-full">
            <img src="<?php echo htmlspecialchars(base_url('logo.avif'), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Anafinet" class="w-28 mx-auto mb-4">
            <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($userName); ?></h3>
            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($userRole); ?></p>
        </div>
        <button id="menuClose" class="md:hidden ml-2 inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50" aria-label="Cerrar men&uacute;">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="space-y-1 text-sm">
        <?php foreach ($menuItems as $item):
            $iconStyle = $item['iconStyle'] ?? 'solid';
        ?>
            <a href="<?php echo $item['href']; ?>" class="<?php echo menu_link_classes($item['key'], $activePage); ?>">
                <i class="fa-<?php echo $iconStyle; ?> <?php echo $item['icon']; ?>"></i>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
        <hr class="my-4">
        <a href="<?php echo htmlspecialchars(base_url('logout.php'), ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-50 rounded-xl transition">
            <i class="fa-solid fa-right-from-bracket"></i> <span>Cerrar Sesi&oacute;n</span>
        </a>
        <a href="<?php echo htmlspecialchars(base_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-2 px-4 py-2 text-xs text-gray-400 hover:text-gray-600 transition">
            <i class="fa-solid fa-arrow-left"></i> <span>Volver al sitio</span>
        </a>
    </nav>
</aside>

<style>
@media (min-width: 768px) {
    body { height: 100vh; overflow: hidden; }
    main { height: 100vh; overflow-y: auto; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const menu = document.getElementById('sideMenu');
    const overlay = document.getElementById('menuOverlay');
    const openBtn = document.getElementById('menuOpen');
    const closeBtn = document.getElementById('menuClose');

    const openMenu = () => {
        menu.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    };
    const closeMenu = () => {
        menu.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    };

    openBtn?.addEventListener('click', openMenu);
    closeBtn?.addEventListener('click', closeMenu);
    overlay?.addEventListener('click', closeMenu);
});
</script>
