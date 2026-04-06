<?php
if (!function_exists('env_value')) {
    function env_value(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('is_vercel_environment')) {
    function is_vercel_environment(): bool
    {
        return env_value('VERCEL') === '1';
    }
}

if (!function_exists('detect_base_url')) {
    function detect_base_url(): string
    {
        $baseUrl = env_value('BASE_URL');
        if ($baseUrl !== null) {
            return rtrim($baseUrl, '/');
        }

        return is_vercel_environment() ? '' : '/asociadosAnafinet';
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', detect_base_url());
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        if (BASE_URL === '') {
            return $path !== '' ? '/' . $path : '/';
        }

        return BASE_URL . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('app_is_secure_request')) {
    function app_is_secure_request(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string)$https) !== 'off') {
            return true;
        }

        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        return strtolower((string)$proto) === 'https';
    }
}

if (!function_exists('app_cookie_path')) {
    function app_cookie_path(): string
    {
        return BASE_URL === '' ? '/' : BASE_URL . '/';
    }
}

if (!function_exists('app_storage_root')) {
    function app_storage_root(): string
    {
        $customRoot = env_value('UPLOADS_DIR');
        if ($customRoot !== null) {
            return rtrim($customRoot, "\\/");
        }

        if (is_vercel_environment()) {
            return rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'anafinet_uploads';
        }

        return __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    }
}

if (!function_exists('app_bundled_uploads_root')) {
    function app_bundled_uploads_root(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    }
}

if (!function_exists('app_storage_path')) {
    function app_storage_path(string $bucket, string $filename = ''): string
    {
        $bucket = trim($bucket, "\\/");
        $path = app_storage_root() . DIRECTORY_SEPARATOR . $bucket;

        if ($filename === '') {
            return $path;
        }

        return $path . DIRECTORY_SEPARATOR . basename($filename);
    }
}

if (!function_exists('app_ensure_storage_directory')) {
    function app_ensure_storage_directory(string $bucket): string
    {
        $directory = app_storage_path($bucket);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }
}

if (!function_exists('app_resolve_storage_path')) {
    function app_resolve_storage_path(string $bucket, string $filename): ?string
    {
        $filename = basename($filename);
        if ($filename === '') {
            return null;
        }

        $candidates = [
            app_storage_path($bucket, $filename),
        ];

        if (is_vercel_environment()) {
            $candidates[] = app_bundled_uploads_root() . DIRECTORY_SEPARATOR . trim($bucket, "\\/") . DIRECTORY_SEPARATOR . $filename;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('uploaded_file_url')) {
    function uploaded_file_url(string $bucket, string $filename, bool $download = false): string
    {
        return base_url('media.php?' . http_build_query([
            'type' => trim($bucket, "\\/"),
            'file' => basename($filename),
            'download' => $download ? '1' : '0',
        ]));
    }
}
?>
