<?php
session_start();
// Evitar que el navegador guarde en cachÃ© informaciÃ³n sensible
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require 'db.php';
require_once 'youtube_helpers.php';

// VerificaciÃ³n de seguridad: Si no hay sesiÃ³n, regresa al login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Consultas dinÃ¡micas para los contadores (replicando Figma)
$videos_count = $pdo->query("SELECT COUNT(*) FROM contenidos WHERE tipo = 'video'")->fetchColumn();
$docs_count = $pdo->query("SELECT COUNT(*) FROM contenidos WHERE tipo = 'documento'")->fetchColumn();
$asociados_count = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado'")->fetchColumn();
$foro_count = $pdo->query("SELECT COUNT(*) FROM contenidos WHERE tipo = 'foro'")->fetchColumn();

$yt_videos_count = yt_get_video_count();
if ($yt_videos_count !== null) {
    $videos_count = $yt_videos_count;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Dashboard - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'dashboard';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-8">
        <header class="mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Bienvenido, <?php echo $_SESSION['user_name']; ?></h1>
            <p class="text-gray-500">Accede a todos los recursos y contenido exclusivo.</p>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <a href="biblioteca_videos.php" class="bg-[#5282B2] p-6 rounded-2xl text-white shadow-lg relative overflow-hidden block hover:opacity-95 transition">
                <p class="text-sm opacity-80">Videos Disponibles</p>
                <h2 class="text-3xl font-bold counter" data-target="<?php echo $videos_count; ?>"><?php echo number_format($videos_count); ?></h2>
                <i class="fa-solid fa-video absolute right-4 bottom-4 text-4xl opacity-20"></i>
            </a>
            <a href="biblioteca_archivos.php" class="bg-[#E67E22] p-6 rounded-2xl text-white shadow-lg relative overflow-hidden block hover:opacity-95 transition">
                <p class="text-sm opacity-80">Documentos</p>
                <h2 class="text-3xl font-bold counter" data-target="<?php echo $docs_count; ?>"><?php echo number_format($docs_count); ?></h2>
                <i class="fa-solid fa-file-lines absolute right-4 bottom-4 text-4xl opacity-20"></i>
            </a>
            <a href="lista_asociados.php" class="bg-[#9B59B6] p-6 rounded-2xl text-white shadow-lg relative overflow-hidden block hover:opacity-95 transition">
                <p class="text-sm opacity-80">Asociados Activos</p>
                <h2 class="text-3xl font-bold counter" data-target="<?php echo $asociados_count; ?>"><?php echo number_format($asociados_count); ?></h2>
                <i class="fa-solid fa-users absolute right-4 bottom-4 text-4xl opacity-20"></i>
            </a>
            <a href="foro.php" class="bg-[#2ECC71] p-6 rounded-2xl text-white shadow-lg relative overflow-hidden block hover:opacity-95 transition">
                <p class="text-sm opacity-80">Temas del Foro</p>
                <h2 class="text-3xl font-bold counter" data-target="<?php echo $foro_count; ?>"><?php echo number_format($foro_count); ?></h2>
                <i class="fa-solid fa-comments absolute right-4 bottom-4 text-4xl opacity-20"></i>
            </a>
        </div>

        <section class="space-y-8">
            <div>
                <h3 class="text-lg font-bold text-gray-800 mb-4">Acceso Rápido</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a href="biblioteca_videos.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-blue-500 flex items-center justify-center text-white">
                            <i class="fa-solid fa-video"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Biblioteca de Videos</span>
                    </a>
                    <a href="biblioteca_archivos.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-green-500 flex items-center justify-center text-white">
                            <i class="fa-regular fa-file-lines"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Biblioteca de Archivos</span>
                    </a>
                    <a href="#" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-purple-500 flex items-center justify-center text-white">
                            <i class="fa-regular fa-book-open"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Revista Conciencia Fiscal</span>
                    </a>
                    <a href="lista_asociados.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-orange-500 flex items-center justify-center text-white">
                            <i class="fa-solid fa-users"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Lista de Asociados</span>
                    </a>
                    <a href="links_interes.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-cyan-500 flex items-center justify-center text-white">
                            <i class="fa-solid fa-link"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Links de Interés</span>
                    </a>
                    <a href="foro.php" class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                        <span class="w-11 h-11 rounded-xl bg-pink-500 flex items-center justify-center text-white">
                            <i class="fa-regular fa-comments"></i>
                        </span>
                        <span class="font-semibold text-gray-800">Foro Fiscal</span>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <?php
                // 1. Consultar las últimas 3 actualizaciones
                $stmt_updates = $pdo->query("SELECT * FROM contenidos ORDER BY creado_at DESC LIMIT 3");
                $actualizaciones = $stmt_updates->fetchAll();
                ?>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex items-center space-x-2 mb-6">
                        <i class="fa-regular fa-bell text-blue-500"></i>
                        <h3 class="font-bold text-gray-700 uppercase text-xs tracking-wider">Actualizaciones Recientes</h3>
                    </div>

                    <div class="space-y-6">
                        <?php foreach ($actualizaciones as $item):
                            // Lógica para asignar icono y color según el tipo
                            $icon = "fa-file-lines";
                            $color = "text-blue-500";
                            $bg = "bg-blue-50";

                            if ($item['tipo'] == 'video') {
                                $icon = "fa-video"; $color = "text-orange-500"; $bg = "bg-orange-50";
                            } elseif ($item['tipo'] == 'revista') {
                                $icon = "fa-book-open"; $color = "text-purple-500"; $bg = "bg-purple-50";
                            }
                        ?>
                        <div class="flex items-start space-x-4 group cursor-pointer">
                            <div class="<?php echo $bg; ?> p-3 rounded-xl transition group-hover:scale-110">
                                <i class="fa-solid <?php echo $icon . ' ' . $color; ?> text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 group-hover:text-blue-600 transition">
                                    <?php echo htmlspecialchars($item['titulo']); ?>
                                </h4>
                                <p class="text-xs text-gray-400">
                                    <?php echo date("d M, Y", strtotime($item['fecha_publicacion'])); ?> •
                                    <span class="capitalize"><?php echo $item['tipo']; ?></span>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php
                // Consultar los próximos 3 eventos
                $stmt_eventos = $pdo->query("SELECT * FROM eventos WHERE fecha_evento >= CURDATE() ORDER BY fecha_evento ASC LIMIT 3");
                $eventos = $stmt_eventos->fetchAll();
                ?>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex items-center space-x-2 mb-6">
                        <i class="fa-regular fa-calendar text-orange-500"></i>
                        <h3 class="font-bold text-gray-700 uppercase text-xs tracking-wider">Próximos Eventos</h3>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($eventos)): ?>
                            <p class="text-gray-400 text-sm italic">No hay eventos programados próximamente.</p>
                        <?php else: ?>
                            <?php foreach ($eventos as $ev): ?>
                            <div class="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 transition cursor-default">
                                <h4 class="font-bold text-gray-800 mb-2">
                                    <?php echo htmlspecialchars($ev['titulo']); ?>
                                </h4>
                                <div class="flex flex-col space-y-1">
                                    <div class="flex items-center text-xs text-gray-500">
                                        <i class="fa-regular fa-calendar-days w-5"></i>
                                        <span><?php echo date("d \\d\\e F, Y", strtotime($ev['fecha_evento'])); ?></span>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-500">
                                        <i class="fa-solid fa-location-dot w-5"></i>
                                        <span><?php echo htmlspecialchars($ev['ubicacion']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const counters = document.querySelectorAll('.counter');
        const speed = 200; // Cuanto más alto, más lenta la animación

        counters.forEach(counter => {
            const target = Number(counter.getAttribute('data-target')) || 0;
            let current = 0;
            const inc = target / speed;

            const updateCount = () => {
                if (current < target) {
                    current = Math.ceil(current + inc);
                    if (current > target) current = target;
                    counter.innerText = current.toLocaleString();
                    setTimeout(updateCount, 15);
                } else {
                    counter.innerText = target.toLocaleString();
                }
            };
            updateCount();
        });
    });
</script>
</body>
</html>

