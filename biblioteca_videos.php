<?php
require_once __DIR__ . '/bootstrap.php';
require_once 'role_helpers.php';
$channelId = 'UC5_y_I_4X5s-11SMxPA1wUg';
$uploadsPlaylistId = 'UU' . substr($channelId, 2);
$channelUrl = 'https://www.youtube.com/channel/' . $channelId;
$rssUrl = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channelId;

function fetch_remote(string $url, ?array &$debug = null): ?string
{
    $debug = [
        'method' => null,
        'error' => null,
    ];

    $allowUrl = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
    if ($allowUrl) {
        $debug['method'] = 'file_get_contents';
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "User-Agent: Anafinet/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        if ($result !== false) {
            return $result;
        }
        $debug['error'] = 'file_get_contents_failed';
    }

    if (function_exists('curl_init')) {
        $debug['method'] = 'curl';
        $ch = curl_init($url);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Anafinet/1.0',
        ];

        $caFile = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
        if ($caFile && is_file($caFile)) {
            $curlOptions[CURLOPT_CAINFO] = $caFile;
        }

        curl_setopt_array($ch, $curlOptions);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        if ($result !== false && $status >= 200 && $status < 300) {
            return $result;
        }
        $debug['error'] = $curlError !== '' ? $curlError : 'curl_failed';

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocalhost = stripos($host, 'localhost') !== false || str_starts_with($host, '127.0.0.1');
        if ($isLocalhost) {
            $debug['method'] = 'curl_insecure_local';
            $ch = curl_init($url);
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
            curl_setopt_array($ch, $curlOptions);
            $result = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($result !== false && $status >= 200 && $status < 300) {
                $debug['error'] = null;
                return $result;
            }
        }

        return null;
    }

    $debug['method'] = 'none';
    $debug['error'] = 'no_http_client';
    return null;
}

function get_recent_videos(string $rssUrl, string $channelId, int $limit = 15, ?array &$debug = null): array
{
    $cacheFile = __DIR__ . '/youtube_rss_cache_' . $channelId . '.json';
    $cacheTtl = 15 * 60;
    $cached = null;

    if (is_file($cacheFile)) {
        $age = time() - filemtime($cacheFile);
        $data = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($data)) {
            $cached = $data;
            if ($age < $cacheTtl) {
                return array_slice($data, 0, $limit);
            }
        }
    }

    $xmlRaw = fetch_remote($rssUrl, $debug);
    if (!$xmlRaw) {
        return $cached ? array_slice($cached, 0, $limit) : [];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlRaw);
    if ($xml === false) {
        return $cached ? array_slice($cached, 0, $limit) : [];
    }

    $videos = [];
    $ns = $xml->getNamespaces(true);
    foreach ($xml->entry as $entry) {
        $yt = $entry->children($ns['yt'] ?? 'http://www.youtube.com/xml/schemas/2015');
        $media = $entry->children($ns['media'] ?? 'http://search.yahoo.com/mrss/');
        $group = $media->group ?? null;
        $videoId = (string)($yt->videoId ?? '');
        if ($videoId === '') {
            $idParts = explode(':', (string)$entry->id);
            $videoId = end($idParts);
        }
        if ($videoId === '') {
            continue;
        }

        $thumb = '';
        if ($group && $group->thumbnail) {
            $thumb = (string)$group->thumbnail[0]->attributes()->url;
        }

        $videos[] = [
            'id' => $videoId,
            'title' => (string)$entry->title,
            'published' => (string)$entry->published,
            'url' => 'https://www.youtube.com/watch?v=' . $videoId,
            'thumb' => $thumb,
        ];
    }

    if (!empty($videos)) {
        file_put_contents($cacheFile, json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } elseif ($cached) {
        $videos = $cached;
    }

    return array_slice($videos, 0, $limit);
}

$maxRecent = 15;
$perPage = 9;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$rssDebug = null;
$recentVideosAll = get_recent_videos($rssUrl, $channelId, $maxRecent, $rssDebug);
usort($recentVideosAll, function ($a, $b) {
    $aTime = isset($a['published']) ? strtotime((string)$a['published']) : 0;
    $bTime = isset($b['published']) ? strtotime((string)$b['published']) : 0;
    return $bTime <=> $aTime;
});
$totalRecent = count($recentVideosAll);
$totalPages = $perPage > 0 ? (int)ceil($totalRecent / $perPage) : 1;
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;
$recentVideos = $perPage > 0 ? array_slice($recentVideosAll, $offset, $perPage) : $recentVideosAll;
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

        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Canal oficial de ANAFINET</h2>
                    <p class="text-sm text-gray-500">Los videos m&aacute;s recientes, siempre actualizados.</p>
                </div>
                <a href="<?php echo htmlspecialchars($channelUrl); ?>" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">
                    <i class="fa-brands fa-youtube text-red-500"></i>
                    Ver canal completo
                </a>
            </div>

            <div class="aspect-video w-full overflow-hidden rounded-2xl border border-gray-100 bg-black">
                <iframe
                    class="w-full h-full"
                    src="https://www.youtube.com/embed/videoseries?list=<?php echo htmlspecialchars($uploadsPlaylistId); ?>"
                    title="Biblioteca de Videos ANAFINET"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                ></iframe>
            </div>

            <div class="mt-4 flex justify-end">
                <a href="<?php echo htmlspecialchars($channelUrl); ?>" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition">
                    <i class="fa-brands fa-youtube"></i>
                    Abrir en YouTube
                </a>
            </div>
        </div>
        <?php if (!empty($recentVideosAll)): ?>
            <section class="mt-10">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">&Uacute;ltimos videos</h2>
                    <a href="<?php echo htmlspecialchars($channelUrl); ?>" target="_blank" rel="noopener noreferrer"
                       class="text-sm text-blue-600 hover:text-blue-700">Ver canal completo</a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($recentVideos as $video):
                        $logUrl = 'registrar_actividad.php?tipo=video_visto&detalle=' . rawurlencode($video['title']) . '&video=' . rawurlencode($video['id']);
                    ?>
                    <a href="<?php echo htmlspecialchars($video['url']); ?>" target="_blank"
                       data-log-url="<?php echo htmlspecialchars($logUrl); ?>"
                       class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition group">
                        <div class="relative">
                            <?php if ($video['thumb']): ?>
                                <img src="<?php echo htmlspecialchars($video['thumb']); ?>" class="w-full h-48 object-cover" alt="Video thumbnail">
                            <?php else: ?>
                                <div class="w-full h-48 bg-slate-200 flex items-center justify-center text-gray-400">
                                    <i class="fa-solid fa-video text-3xl"></i>
                                </div>
                            <?php endif; ?>
                            <span class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <i class="fa-solid fa-play text-white text-4xl"></i>
                            </span>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-gray-800 text-sm line-clamp-2 mb-2"><?php echo htmlspecialchars($video['title']); ?></h3>
                            <p class="text-xs text-gray-400">
                                <?php echo $video['published'] ? date("d M, Y", strtotime($video['published'])) : ''; ?>
                            </p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex items-center justify-center gap-2">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?p=<?php echo $i; ?>"
                           class="w-9 h-9 flex items-center justify-center rounded-xl text-sm font-semibold transition <?php echo $i === $currentPage ? 'bg-blue-600 text-white shadow' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </section>
        <?php elseif ($rssDebug): ?>
            <div class="mt-10 rounded-2xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-700">
                No se pudieron cargar los videos recientes desde el feed de YouTube. En local, habilita <code>allow_url_fopen</code>
                o configura certificados SSL para cURL. Detalle: <?php echo htmlspecialchars($rssDebug['error'] ?? 'desconocido'); ?>.
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

