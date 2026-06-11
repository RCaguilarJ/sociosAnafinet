<?php
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $haystack = (string)$haystack;
        $needle = (string)$needle;

        if ($needle === '') {
            return true;
        }

        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $haystack = (string)$haystack;
        $needle = (string)$needle;

        if ($needle === '') {
            return true;
        }

        $needleLength = strlen($needle);
        if ($needleLength > strlen($haystack)) {
            return false;
        }

        return substr($haystack, -$needleLength) === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        $haystack = (string)$haystack;
        $needle = (string)$needle;

        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('load_env_file')) {
    function load_env_file(string $path, bool $override = false): void
    {
        static $loaded = [];

        if (isset($loaded[$path]) || !is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if ($name === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if ($override || getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }

        $loaded[$path] = true;
    }
}

load_env_file(__DIR__ . DIRECTORY_SEPARATOR . '.env');
load_env_file(__DIR__ . DIRECTORY_SEPARATOR . '.env.production', true);

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

if (!function_exists('app_is_valid_email')) {
    function app_is_valid_email(string $email): bool
    {
        $email = trim($email);
        if ($email === '' || substr_count($email, '@') !== 1) {
            return false;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        [$localPart, $domainPart] = explode('@', $email, 2);
        if ($localPart === '' || $domainPart === '') {
            return false;
        }

        if (!function_exists('idn_to_ascii')) {
            return false;
        }

        $asciiDomain = idn_to_ascii($domainPart, IDNA_DEFAULT);
        if (!is_string($asciiDomain) || $asciiDomain === '') {
            return false;
        }

        return filter_var($localPart . '@' . $asciiDomain, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('app_ascii_transliterate')) {
    function app_ascii_transliterate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
            if ($transliterated !== false) {
                return $transliterated;
            }
        }

        return strtr($value, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Å' => 'A',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ñ' => 'N', 'ñ' => 'n',
            'Ç' => 'C', 'ç' => 'c',
        ]);
    }
}

if (!function_exists('is_vercel_environment')) {
    function is_vercel_environment(): bool
    {
        return env_value('VERCEL') === '1';
    }
}

if (!function_exists('app_demo_mode_enabled')) {
    function app_demo_mode_enabled(): bool
    {
        return env_value('ALLOW_DEMO_LOGIN', '0') === '1';
    }
}

if (!function_exists('app_demo_credentials')) {
    function app_demo_credentials(): array
    {
        return [
            'email' => env_value('DEMO_EMAIL', ''),
            'password' => env_value('DEMO_PASSWORD', ''),
        ];
    }
}

if (!function_exists('app_demo_login_available')) {
    function app_demo_login_available(): bool
    {
        $credentials = app_demo_credentials();

        return app_demo_mode_enabled()
            && $credentials['email'] !== ''
            && $credentials['password'] !== '';
    }
}

if (!function_exists('app_master_emails')) {
    function app_master_emails(): array
    {
        $raw = env_value('MASTER_EMAILS');
        if ($raw === null) {
            $single = env_value('MASTER_EMAIL', '');
            $raw = $single !== '' ? $single : '';
        }

        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $emails = [];

        foreach ($parts as $part) {
            $email = strtolower(trim((string)$part));
            if ($email !== '') {
                $emails[$email] = true;
            }
        }

        return array_keys($emails);
    }
}

if (!function_exists('app_email_is_master')) {
    function app_email_is_master(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        return in_array($email, app_master_emails(), true);
    }
}

if (!function_exists('detect_base_url')) {
    function detect_base_url(): string
    {
        $baseUrl = env_value('BASE_URL');
        if ($baseUrl !== null) {
            return rtrim($baseUrl, '/');
        }

        if (PHP_SAPI !== 'cli') {
            $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
            $applicationRoot = realpath(__DIR__);

            if ($documentRoot !== false && $applicationRoot !== false) {
                $documentRootPath = rtrim(str_replace('\\', '/', $documentRoot), '/');
                $applicationRootPath = rtrim(str_replace('\\', '/', $applicationRoot), '/');

                $documentRootComparison = DIRECTORY_SEPARATOR === '\\'
                    ? strtolower($documentRootPath)
                    : $documentRootPath;
                $applicationRootComparison = DIRECTORY_SEPARATOR === '\\'
                    ? strtolower($applicationRootPath)
                    : $applicationRootPath;

                if ($applicationRootComparison === $documentRootComparison) {
                    return '';
                }

                $documentRootPrefix = $documentRootComparison . '/';
                if (str_starts_with($applicationRootComparison, $documentRootPrefix)) {
                    $relativePath = substr($applicationRootPath, strlen($documentRootPath));
                    return $relativePath === '' ? '' : rtrim($relativePath, '/');
                }
            }

            $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            $host = preg_replace('/:\d+$/', '', $host);
            if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                return '/asociadosAnafinet';
            }
        }

        return '';
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', detect_base_url());
}

if (!function_exists('app_request_host')) {
    function app_request_host(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }

        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);

        return $host ?? '';
    }
}

if (!function_exists('app_is_local_request')) {
    function app_is_local_request(): bool
    {
        return in_array(app_request_host(), ['localhost', '127.0.0.1', '::1'], true);
    }
}

if (!function_exists('app_environment_label')) {
    function app_environment_label(): string
    {
        $explicit = env_value('APP_ENV_LABEL');
        if ($explicit !== null && trim($explicit) !== '') {
            return trim($explicit);
        }

        return app_is_local_request() ? 'Local' : 'Produccion';
    }
}

if (!function_exists('app_session_debug_enabled')) {
    function app_session_debug_enabled(): bool
    {
        return env_value('SESSION_DEBUG', '0') === '1';
    }
}

if (!function_exists('app_current_database_name')) {
    function app_current_database_name(?PDO $pdo = null): string
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        if (!($pdo instanceof PDO)) {
            $cached = (string)env_value('DB_NAME', '');
            return $cached;
        }

        try {
            $name = $pdo->query('SELECT DATABASE()')->fetchColumn();
            $cached = is_string($name) ? $name : (string)env_value('DB_NAME', '');
        } catch (Throwable $e) {
            $cached = (string)env_value('DB_NAME', '');
        }

        return $cached;
    }
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

if (!function_exists('app_favicon_url')) {
    function app_favicon_url(): string
    {
        return base_url('logo_anafinet_favicon.png');
    }
}

if (!function_exists('app_render_favicon_tags')) {
    function app_render_favicon_tags(): void
    {
        $favicon = htmlspecialchars(app_favicon_url(), ENT_QUOTES, 'UTF-8');
        echo '    <link rel="icon" type="image/png" href="' . $favicon . '">' . PHP_EOL;
        echo '    <link rel="shortcut icon" href="' . $favicon . '">' . PHP_EOL;
        echo '    <link rel="apple-touch-icon" href="' . $favicon . '">' . PHP_EOL;
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
