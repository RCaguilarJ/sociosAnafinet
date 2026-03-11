<?php
session_start();
require 'db.php';
require_once 'youtube_helpers.php';

$maxResults = 50;
$pageToken = isset($_GET['pageToken']) ? trim($_GET['pageToken']) : null;
$pageToken = $pageToken !== '' ? $pageToken : null;

$page = yt_get_videos_page($maxResults, $pageToken);
$videos = $page['items'] ?? [];
$nextPageToken = $page['nextPageToken'] ?? null;
$prevPageToken = $page['prevPageToken'] ?? null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Biblioteca de Videos - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'videos';
    require 'menu.php';
    ?>

    <main class="md:ml-64 max-w-7xl mx-auto p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Biblioteca de Videos</h1>

        <?php if (empty($videos)): ?>
            <p class="text-gray-400">No hay videos disponibles en este momento.</p>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($videos as $video):
                $snippet = $video['snippet'] ?? [];
                $details = $video['contentDetails'] ?? [];
                $resource = $snippet['resourceId'] ?? [];
                $vId = $details['videoId'] ?? ($resource['videoId'] ?? '');
                $title = $snippet['title'] ?? '';
                $thumb = $snippet['thumbnails']['high']['url'] ?? '';
                $published = $snippet['publishedAt'] ?? null;
                $logUrl = 'registrar_actividad.php?tipo=video_visto&detalle=' . rawurlencode($title) . '&video=' . rawurlencode($vId);
            ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition group">
                <div class="relative">
                    <img src="<?php echo htmlspecialchars($thumb); ?>" class="w-full h-48 object-cover" alt="Video thumbnail">
                    <a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($vId); ?>" target="_blank"
                       data-log-url="<?php echo htmlspecialchars($logUrl); ?>"
                       class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                        <i class="fa-solid fa-play text-white text-4xl"></i>
                    </a>
                </div>
                <div class="p-4">
                    <h3 class="font-bold text-gray-800 text-sm line-clamp-2 mb-2"><?php echo htmlspecialchars($title); ?></h3>
                    <p class="text-xs text-gray-400">
                        <?php echo $published ? date("d M, Y", strtotime($published)) : ''; ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($prevPageToken || $nextPageToken): ?>
        <div class="mt-10 flex items-center justify-between">
            <div>
                <?php if ($prevPageToken): ?>
                    <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition"
                       href="?pageToken=<?php echo htmlspecialchars(urlencode($prevPageToken)); ?>">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Anterior
                    </a>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($nextPageToken): ?>
                    <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition"
                       href="?pageToken=<?php echo htmlspecialchars(urlencode($nextPageToken)); ?>">
                        Siguiente <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const logLinks = document.querySelectorAll('[data-log-url]');
    logLinks.forEach(link => {
        link.addEventListener('click', () => {
            const url = link.getAttribute('data-log-url');
            if (!url) return;
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url);
            } else {
                fetch(url, { method: 'POST', keepalive: true });
            }
        });
    });
});
</script>
</body>
</html>


