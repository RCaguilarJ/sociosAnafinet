<?php
require_once __DIR__ . '/config.php';

if (!function_exists('app_session_driver')) {
    function app_session_driver(): string
    {
        $driver = strtolower(trim((string)env_value('SESSION_DRIVER', 'database')));
        return in_array($driver, ['database', 'files'], true) ? $driver : 'database';
    }
}

if (!class_exists('DatabaseSessionHandler')) {
    class DatabaseSessionHandler implements SessionHandlerInterface
    {
        private $pdo;
        private $ttl;

        public function __construct($pdo, $ttl)
        {
            $this->pdo = $pdo;
            $this->ttl = (int)$ttl;
        }

        public function open($path, $name): bool
        {
            return true;
        }

        public function close(): bool
        {
            return true;
        }

        public function read($id): string
        {
            try {
                $stmt = $this->pdo->prepare('SELECT data, last_activity FROM app_sessions WHERE id = ? LIMIT 1');
                $stmt->execute([$id]);
                $row = $stmt->fetch();
            } catch (Throwable $e) {
                error_log('Session read failed for session id ' . (string)$id . ': ' . $e->getMessage());
                return '';
            }

            if (!$row) {
                return '';
            }

            $lastActivity = (int)($row['last_activity'] ?? 0);
            if (($lastActivity + $this->ttl) < time()) {
                $this->destroy($id);
                return '';
            }

            return (string)($row['data'] ?? '');
        }

        public function write($id, $data): bool
        {
            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO app_sessions (id, data, last_activity) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
                );
                $result = $stmt->execute([$id, $data, time()]);
            } catch (Throwable $e) {
                error_log('Session write failed for session id ' . (string)$id . ': ' . $e->getMessage());
                return false;
            }

            if (!$result) {
                $errorInfo = method_exists($stmt, 'errorInfo') ? $stmt->errorInfo() : [];
                error_log('Session write failed for session id ' . (string)$id . ': ' . json_encode($errorInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return $result;
        }

        public function destroy($id): bool
        {
            try {
                $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE id = ?');
                return $stmt->execute([$id]);
            } catch (Throwable $e) {
                error_log('Session destroy failed for session id ' . (string)$id . ': ' . $e->getMessage());
                return false;
            }
        }

        public function gc($max_lifetime): int
        {
            $threshold = time() - max($max_lifetime, $this->ttl);
            try {
                $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
                $stmt->execute([$threshold]);
            } catch (Throwable $e) {
                error_log('Session GC failed: ' . $e->getMessage());
                return 0;
            }

            return (int)$stmt->rowCount();
        }
    }
}

if (!function_exists('ensure_session_table')) {
    function ensure_session_table($pdo)
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                data LONGTEXT NOT NULL,
                last_activity INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $initialized = true;
    }
}

if (!function_exists('app_start_session')) {
    function app_start_session($pdo)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $ttl = max(3600, (int)env_value('SESSION_TTL', '86400'));

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_name((string)env_value('SESSION_NAME', 'anafinet_session'));
        $cookiePath = app_cookie_path();
        $cookieSecure = app_is_secure_request();
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $cookiePath,
                'domain' => '',
                'secure' => $cookieSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(
                0,
                $cookiePath . '; samesite=Lax',
                '',
                $cookieSecure,
                true
            );
        }
        ini_set('session.gc_maxlifetime', (string)$ttl);

        if (app_session_driver() === 'database' && $pdo instanceof PDO) {
            try {
                ensure_session_table($pdo);
                session_set_save_handler(new DatabaseSessionHandler($pdo, $ttl), true);
            } catch (Exception $e) {
                $GLOBALS['appSessionError'] = $e->getMessage();
                error_log('Session save handler setup failed: ' . $e->getMessage());
            } catch (Error $e) {
                $GLOBALS['appSessionError'] = $e->getMessage();
                error_log('Session save handler setup failed: ' . $e->getMessage());
            }
        }

        if (!session_start()) {
            error_log('Session start failed using driver: ' . app_session_driver());
        }
    }
}
?>
