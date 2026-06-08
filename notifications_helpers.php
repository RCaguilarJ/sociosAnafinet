<?php

if (!function_exists('app_ensure_notification_schema')) {
    function app_ensure_notification_schema(PDO $pdo)
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS app_notifications (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type VARCHAR(60) NOT NULL,
                title VARCHAR(190) NOT NULL,
                message TEXT NOT NULL,
                url VARCHAR(255) NULL,
                dedupe_key VARCHAR(191) NULL,
                meta_json LONGTEXT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                read_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_app_notifications_user (user_id),
                KEY idx_app_notifications_user_read (user_id, is_read, created_at),
                UNIQUE KEY uq_app_notifications_user_dedupe (user_id, dedupe_key),
                CONSTRAINT fk_app_notifications_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $initialized = true;
    }
}

if (!function_exists('app_create_notification')) {
    function app_create_notification(PDO $pdo, $userId, $type, $title, $message, $url = '', $dedupeKey = null, array $meta = [])
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return;
        }

        app_ensure_notification_schema($pdo);

        $stmt = $pdo->prepare(
            'INSERT INTO app_notifications (user_id, type, title, message, url, dedupe_key, meta_json)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                message = VALUES(message),
                url = VALUES(url),
                meta_json = VALUES(meta_json)'
        );

        $stmt->execute([
            $userId,
            (string)$type,
            (string)$title,
            (string)$message,
            $url !== '' ? (string)$url : null,
            $dedupeKey !== null && $dedupeKey !== '' ? (string)$dedupeKey : null,
            !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}

if (!function_exists('app_get_unread_notifications_count')) {
    function app_get_unread_notifications_count(PDO $pdo, $userId)
    {
        app_ensure_notification_schema($pdo);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM app_notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([(int)$userId]);

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('app_get_recent_notifications')) {
    function app_get_recent_notifications(PDO $pdo, $userId, $limit = 6)
    {
        app_ensure_notification_schema($pdo);

        $limit = max(1, min((int)$limit, 20));
        $stmt = $pdo->prepare(
            'SELECT id, type, title, message, url, is_read, created_at
             FROM app_notifications
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([(int)$userId]);

        return $stmt->fetchAll();
    }
}

if (!function_exists('app_get_all_notifications')) {
    function app_get_all_notifications(PDO $pdo, $userId, $limit = 100)
    {
        app_ensure_notification_schema($pdo);

        $limit = max(1, min((int)$limit, 200));
        $stmt = $pdo->prepare(
            'SELECT id, type, title, message, url, is_read, created_at, read_at
             FROM app_notifications
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([(int)$userId]);

        return $stmt->fetchAll();
    }
}

if (!function_exists('app_mark_all_notifications_read')) {
    function app_mark_all_notifications_read(PDO $pdo, $userId)
    {
        app_ensure_notification_schema($pdo);

        $stmt = $pdo->prepare('UPDATE app_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0');
        $stmt->execute([(int)$userId]);
    }
}

if (!function_exists('app_mark_notification_read')) {
    function app_mark_notification_read(PDO $pdo, $userId, $notificationId)
    {
        app_ensure_notification_schema($pdo);

        $stmt = $pdo->prepare('UPDATE app_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)$notificationId, (int)$userId]);
    }
}

if (!function_exists('app_mark_notifications_by_url')) {
    function app_mark_notifications_by_url(PDO $pdo, $userId, $url)
    {
        if (trim((string)$url) === '') {
            return;
        }

        app_ensure_notification_schema($pdo);

        $stmt = $pdo->prepare('UPDATE app_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND url = ? AND is_read = 0');
        $stmt->execute([(int)$userId, (string)$url]);
    }
}

if (!function_exists('app_notification_icon_class')) {
    function app_notification_icon_class($type)
    {
        $type = strtolower(trim((string)$type));

        if (strpos($type, 'membership') === 0) {
            return 'fa-solid fa-credit-card text-amber-500';
        }
        if (strpos($type, 'forum') === 0) {
            return 'fa-regular fa-comments text-blue-500';
        }

        return 'fa-regular fa-bell text-slate-500';
    }
}

if (!function_exists('app_notify_forum_topic_created')) {
    function app_notify_forum_topic_created(PDO $pdo, $topicId, $authorId, $topicTitle, $category = '')
    {
        app_ensure_notification_schema($pdo);

        $stmt = $pdo->prepare(
            "SELECT id
             FROM usuarios
             WHERE id <> ?
               AND COALESCE(notif_foro, 1) = 1"
        );
        $stmt->execute([(int)$authorId]);
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $url = base_url('tema_detalle.php?id=' . (int)$topicId);
        $categoryLabel = trim((string)$category) !== '' ? ' en ' . trim((string)$category) : '';

        foreach ($recipients as $recipientId) {
            $recipientId = (int)$recipientId;
            if ($recipientId <= 0) {
                continue;
            }

            app_create_notification(
                $pdo,
                $recipientId,
                'forum_topic_created',
                'Nuevo tema en el foro',
                'Se publico "' . trim((string)$topicTitle) . '"' . $categoryLabel . '.',
                $url,
                'forum-topic-' . (int)$topicId
            );
        }
    }
}

if (!function_exists('app_notify_forum_reply_created')) {
    function app_notify_forum_reply_created(PDO $pdo, $topicId, $replyId, $actorUserId, $topicTitle)
    {
        app_ensure_notification_schema($pdo);

        $stmt = $pdo->prepare(
            "SELECT DISTINCT u.id
             FROM usuarios u
             INNER JOIN (
                SELECT usuario_id FROM foro_temas WHERE id = ?
                UNION
                SELECT usuario_id FROM foro_respuestas WHERE tema_id = ?
             ) forum_users ON forum_users.usuario_id = u.id
             WHERE u.id <> ?
               AND COALESCE(u.notif_foro, 1) = 1"
        );
        $stmt->execute([(int)$topicId, (int)$topicId, (int)$actorUserId]);
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $url = base_url('tema_detalle.php?id=' . (int)$topicId . '#respuestas');

        foreach ($recipients as $recipientId) {
            $recipientId = (int)$recipientId;
            if ($recipientId <= 0) {
                continue;
            }

            app_create_notification(
                $pdo,
                $recipientId,
                'forum_reply_created',
                'Nueva respuesta en el foro',
                'Hay una nueva respuesta en "' . trim((string)$topicTitle) . '".',
                $url,
                'forum-reply-' . (int)$replyId . '-' . $recipientId
            );
        }
    }
}

if (!function_exists('app_seed_membership_notifications')) {
    function app_seed_membership_notifications(PDO $pdo, $userId = null, $limit = 150)
    {
        if (!function_exists('app_ensure_membership_cycle_schema')) {
            return;
        }

        app_ensure_membership_cycle_schema($pdo);
        app_ensure_notification_schema($pdo);

        $warningDays = function_exists('app_membership_warning_days') ? app_membership_warning_days() : 30;
        $limit = max(1, min((int)$limit, 300));

        if ($userId !== null && (int)$userId > 0) {
            $stmt = $pdo->prepare(
                "SELECT id, estatus, membership_expires_at
                 FROM usuarios
                 WHERE id = ?
                 LIMIT 1"
            );
            $stmt->execute([(int)$userId]);
        } else {
            $stmt = $pdo->query(
                "SELECT id, estatus, membership_expires_at
                 FROM usuarios
                 WHERE rol = 'Asociado'
                 ORDER BY id DESC
                 LIMIT " . $limit
            );
        }

        $users = $stmt->fetchAll();
        $now = time();

        foreach ($users as $user) {
            $expiresAt = trim((string)($user['membership_expires_at'] ?? ''));
            if ($expiresAt === '') {
                continue;
            }

            $expiresTs = strtotime($expiresAt);
            if ($expiresTs === false) {
                continue;
            }

            $targetUserId = (int)($user['id'] ?? 0);
            if ($targetUserId <= 0) {
                continue;
            }

            $url = base_url('confirmar_pago.php');
            $dateLabel = date('d/m/Y', $expiresTs);
            $status = (string)($user['estatus'] ?? '');

            if ($expiresTs < $now) {
                app_create_notification(
                    $pdo,
                    $targetUserId,
                    'membership_expired',
                    'Tu membresia vencio',
                    'Tu vigencia termino el ' . $dateLabel . '. Necesitas renovar el pago para recuperar el acceso completo.',
                    $url,
                    'membership-expired-' . $targetUserId . '-' . date('Ymd', $expiresTs),
                    ['status' => $status, 'expires_at' => $expiresAt]
                );
                continue;
            }

            $daysRemaining = (int)ceil(($expiresTs - $now) / 86400);
            if ($daysRemaining <= $warningDays) {
                app_create_notification(
                    $pdo,
                    $targetUserId,
                    'membership_expiring',
                    'Tu membresia esta por vencer',
                    'Tu vigencia vence el ' . $dateLabel . '. Renueva tu pago para evitar restricciones en el acceso.',
                    $url,
                    'membership-expiring-' . $targetUserId . '-' . date('Ymd', $expiresTs),
                    ['status' => $status, 'expires_at' => $expiresAt, 'days_remaining' => $daysRemaining]
                );
            }
        }
    }
}
