<?php
require_once 'role_helpers.php';

$activePage = $activePage ?? '';
$userName = $_SESSION['user_name'] ?? 'Usuario';
$userRole = $_SESSION['user_rol'] ?? '';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (isset($pdo)) {
    $dbRole = fetch_user_role($pdo, $userId);
    if ($dbRole !== null) {
        $userRole = $dbRole;
    }
}

$menuItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-house'],
    ['key' => 'perfil', 'label' => 'Mi Perfil', 'href' => 'perfil.php', 'icon' => 'fa-user', 'iconStyle' => 'regular'],
    ['key' => 'videos', 'label' => 'Biblioteca de Videos', 'href' => 'biblioteca_videos.php', 'icon' => 'fa-video'],
    ['key' => 'archivos', 'label' => 'Biblioteca de Archivos', 'href' => 'biblioteca_archivos.php', 'icon' => 'fa-file-lines', 'iconStyle' => 'regular'],
    ['key' => 'revista', 'label' => 'Revista Conciencia Fiscal', 'href' => '#', 'icon' => 'fa-book-open', 'iconStyle' => 'regular'],
    ['key' => 'asociados', 'label' => 'Lista de Asociados', 'href' => 'lista_asociados.php', 'icon' => 'fa-users'],
    ['key' => 'links', 'label' => 'Links de Inter&eacute;s', 'href' => 'links_interes.php', 'icon' => 'fa-link'],
    ['key' => 'foro', 'label' => 'Foro Fiscal', 'href' => 'foro.php', 'icon' => 'fa-comments', 'iconStyle' => 'regular'],
];

if (is_admin_role($userRole)) {
    $menuItems[] = ['key' => 'subir_documentos', 'label' => 'Subir Documentos', 'href' => 'subir_archivo.php', 'icon' => 'fa-cloud-arrow-up'];
    $menuItems[] = ['key' => 'links_admin', 'label' => 'Administrar Links', 'href' => 'links_interes_admin.php', 'icon' => 'fa-pen-to-square'];
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
        <img src="logo.avif" alt="Logo Anafinet" class="h-8 w-auto">
        <span class="font-semibold text-gray-800">Anafinet</span>
    </div>
    <div class="w-10 h-10"></div>
</header>

<div id="menuOverlay" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden"></div>

<aside id="sideMenu" class="fixed top-0 left-0 h-screen w-72 max-w-[85vw] md:w-64 bg-white border-r border-gray-200 p-6 space-y-8 z-50 transform -translate-x-full md:translate-x-0 transition overflow-y-auto">
    <div class="flex items-start justify-between">
        <div class="text-center w-full">
            <img src="logo.avif" alt="Logo Anafinet" class="w-28 mx-auto mb-4">
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
        <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-red-500 hover:bg-red-50 rounded-xl transition">
            <i class="fa-solid fa-right-from-bracket"></i> <span>Cerrar Sesi&oacute;n</span>
        </a>
        <a href="index.php" class="flex items-center gap-2 px-4 py-2 text-xs text-gray-400 hover:text-gray-600 transition">
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
