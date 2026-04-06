<?php
require_once __DIR__ . '/config.php';

if (!class_exists('DatabaseSessionHandler')) {
    class DatabaseSessionHandler implements SessionHandlerInterface
    {
        private PDO $pdo;
        private int $ttl;

        public function __construct(PDO $pdo, int $ttl)
        {
            $this->pdo = $pdo;
            $this->ttl = $ttl;
        }

        public function open(string $path, string $name): bool
        {
            return true;
        }

        public function close(): bool
        {
            return true;
        }

        public function read(string $id): string
        {
            $stmt = $this->pdo->prepare('SELECT data, last_activity FROM app_sessions WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch();

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

        public function write(string $id, string $data): bool
        {
            $stmt = $this->pdo->prepare(
                'INSERT INTO app_sessions (id, data, last_activity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
            );

            return $stmt->execute([$id, $data, time()]);
        }

        public function destroy(string $id): bool
        {
            $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE id = ?');
            return $stmt->execute([$id]);
        }

        public function gc(int $max_lifetime): int|false
        {
            $threshold = time() - max($max_lifetime, $this->ttl);
            $stmt = $this->pdo->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
            $stmt->execute([$threshold]);

            return $stmt->rowCount();
        }
    }
}

if (!function_exists('ensure_session_table')) {
    function ensure_session_table(PDO $pdo): void
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
    function app_start_session(PDO $pdo): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $ttl = max(3600, (int)env_value('SESSION_TTL', '86400'));

        ensure_session_table($pdo);
        session_set_save_handler(new DatabaseSessionHandler($pdo, $ttl), true);
        session_name((string)env_value('SESSION_NAME', 'anafinet_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => app_cookie_path(),
            'domain' => '',
            'secure' => app_is_secure_request(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.gc_maxlifetime', (string)$ttl);
        session_start();
    }
}
?>
