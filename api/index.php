<?php
$projectRoot = dirname(__DIR__);

$requestedPath = (string)($_GET['__vc_path'] ?? '');
if ($requestedPath === '') {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $requestPath = parse_url($requestUri, PHP_URL_PATH);
    $requestedPath = is_string($requestPath) ? $requestPath : '/';
}

$requestedPath = trim(str_replace('\\', '/', $requestedPath));
$requestedPath = ltrim($requestedPath, '/');

if ($requestedPath === '') {
    $requestedPath = 'index.php';
}

if ($requestedPath === 'afiliacion') {
    $requestedPath = 'afiliacion/index.php';
}

if (pathinfo($requestedPath, PATHINFO_EXTENSION) === '') {
    $requestedPath .= '.php';
}

$targetPath = realpath($projectRoot . DIRECTORY_SEPARATOR . $requestedPath);

if ($targetPath === false || !str_starts_with($targetPath, $projectRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Not Found');
}

if (!is_file($targetPath) || strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)) !== 'php') {
    http_response_code(404);
    exit('Not Found');
}

require $targetPath;
?>
