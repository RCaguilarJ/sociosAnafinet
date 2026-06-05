<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/password_helpers.php';
require_once __DIR__ . '/mail_helpers.php';
require_once __DIR__ . '/payment_helpers.php';
require_once __DIR__ . '/role_helpers.php';
require_once __DIR__ . '/session.php';

app_start_session($pdo);

if ($pdo instanceof PDO && isset($_SESSION['user_id'])) {
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    if ($currentUserId > 0) {
        app_retry_pending_membership_notifications($pdo, $currentUserId, 3);

        $currentUserRole = (string)($_SESSION['user_rol'] ?? '');
        if (is_admin_role($currentUserRole) || current_user_has_master_access($pdo, $currentUserId)) {
            app_retry_pending_membership_notifications($pdo, null, 10);
        }
    }
}
?>
