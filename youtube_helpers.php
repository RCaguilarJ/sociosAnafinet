<?php
// YouTube helper utilities
$YT_API_KEY = 'AIzaSyDnak_gLjZfUNS8ETh4I9GbiBUp0C4De20';
// Puedes pegar aquí la URL del canal, el handle (@anafinet1) o el ID (UC...).
$YT_CHANNEL_SOURCE = 'https://www.youtube.com/@anafinet1/videos';
// Si quieres forzar un ID específico, colócalo aquí (si no, déjalo vacío).
$YT_CHANNEL_ID = '';

function yt_fetch_cached_json(string $cacheFile, int $ttl, string $url): ?array
{
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $err = curl_errno($ch);

    if ($err || !$response) {
        return null;
    }

    $data = json_decode($response, true);
    if (is_array($data)) {
        file_put_contents($cacheFile, $response);
        return $data;
    }

    return null;
}

function yt_extract_channel_id_from_input(string $input): ?string
{
    if (preg_match('~(UC[A-Za-z0-9_-]{22})~', $input, $matches)) {
        return $matches[1];
    }

    return null;
}

function yt_extract_handle_from_input(string $input): ?string
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }

    if ($input[0] === '@') {
        return ltrim($input, '@');
    }

    if (preg_match('~/@([A-Za-z0-9_.-]+)~', $input, $matches)) {
        return $matches[1];
    }

    return $input;
}

function yt_get_channel_id(): ?string
{
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                global $YT_API_KEY, $YT_CHANNEL_ID, $YT_CHANNEL_SOURCE;

    if (!empty($YT_CHANNEL_ID)) {
        return $YT_CHANNEL_ID;
    }

    $fromInput = yt_extract_channel_id_from_input($YT_CHANNEL_SOURCE);
    if ($fromInput) {
        return $fromInput;
    }

    $handle = yt_extract_handle_from_input($YT_CHANNEL_SOURCE);
    if (!$handle) {
        return null;
    }

    $cacheKey = preg_replace('/[^A-Za-z0-9_-]/', '', $handle);
    $cacheFile = __DIR__ . "/youtube_channel_cache_{$cacheKey}.json";
    $ttl = 86400;
    $url = "https://www.googleapis.com/youtube/v3/channels?part=id&forHandle={$handle}&key={$YT_API_KEY}";
    $data = yt_fetch_cached_json($cacheFile, $ttl, $url);

    if (isset($data['items'][0]['id'])) {
        return (string)$data['items'][0]['id'];
    }

    return null;
}

function yt_get_uploads_playlist_id(): ?string
{
    global $YT_API_KEY;
    $channelId = yt_get_channel_id();
    if (!$channelId) {
        return null;
    }

    $cacheFile = __DIR__ . "/youtube_uploads_cache_{$channelId}.json";
    $ttl = 86400;
    $url = "https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id={$channelId}&key={$YT_API_KEY}";
    $data = yt_fetch_cached_json($cacheFile, $ttl, $url);

    if (isset($data['items'][0]['contentDetails']['relatedPlaylists']['uploads'])) {
        return (string)$data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
    }

    return null;
}

function yt_get_video_count(): ?int
{
    global $YT_API_KEY;
    $channelId = yt_get_channel_id();
    if (!$channelId) {
        return null;
    }

    $cacheFile = __DIR__ . "/youtube_stats_cache_{$channelId}.json";
    $ttl = 3600;
    $url = "https://www.googleapis.com/youtube/v3/channels?part=statistics&id={$channelId}&key={$YT_API_KEY}";
    $data = yt_fetch_cached_json($cacheFile, $ttl, $url);

    if (isset($data['items'][0]['statistics']['videoCount'])) {
        return (int)$data['items'][0]['statistics']['videoCount'];
    }

    return null;
}

function yt_get_videos_page(int $maxResults = 50, ?string $pageToken = null): array
{
    global $YT_API_KEY;
    $playlistId = yt_get_uploads_playlist_id();
    if (!$playlistId) {
        return [
            'items' => [],
            'nextPageToken' => null,
            'prevPageToken' => null,
            'pageInfo' => null,
        ];
    }

    $safeToken = $pageToken ? preg_replace('/[^A-Za-z0-9_-]/', '', $pageToken) : 'first';
    $cacheFile = __DIR__ . "/youtube_playlist_cache_{$playlistId}_{$safeToken}_{$maxResults}.json";
    $ttl = 3600;
    $params = [
        'key' => $YT_API_KEY,
        'playlistId' => $playlistId,
        'part' => 'snippet,contentDetails',
        'maxResults' => $maxResults,
    ];
    if (!empty($pageToken)) {
        $params['pageToken'] = $pageToken;
    }
    $url = 'https://www.googleapis.com/youtube/v3/playlistItems?' . http_build_query($params);
    $data = yt_fetch_cached_json($cacheFile, $ttl, $url);

    return [
        'items' => is_array($data['items'] ?? null) ? $data['items'] : [],
        'nextPageToken' => $data['nextPageToken'] ?? null,
        'prevPageToken' => $data['prevPageToken'] ?? null,
        'pageInfo' => $data['pageInfo'] ?? null,
    ];
}

function yt_get_latest_videos(int $maxResults = 12): array
{
    $page = yt_get_videos_page($maxResults, null);
    return $page['items'] ?? [];
}
?>
