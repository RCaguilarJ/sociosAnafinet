<?php
// Ruta base del proyecto (ajusta si se despliega en subcarpeta)
if (!defined('BASE_URL')) {
    $baseUrl = getenv('BASE_URL');
    if ($baseUrl === false || $baseUrl === '') {
        $baseUrl = '/asociadosAnafinet';
    }
    define('BASE_URL', rtrim($baseUrl, '/'));
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return BASE_URL . ($path !== '' ? '/' . $path : '');
    }
}
?>
