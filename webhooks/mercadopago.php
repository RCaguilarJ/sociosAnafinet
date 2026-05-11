<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/role_helpers.php';
require_once dirname(__DIR__) . '/payment_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if (!$pdo instanceof PDO) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'database_unavailable']);
    exit();
}

if (!app_validate_mercadopago_webhook($_SERVER, $_GET)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'invalid_signature']);
    exit();
}

$eventType = strtolower((string) ($_GET['type'] ?? $_GET['topic'] ?? ''));
$dataId = '';
if (isset($_GET['data_id'])) {
    $dataId = (string) $_GET['data_id'];
} elseif (isset($_GET['data.id'])) {
    $dataId = (string) $_GET['data.id'];
} elseif (isset($_GET['data']) && is_array($_GET['data']) && isset($_GET['data']['id'])) {
    $dataId = (string) $_GET['data']['id'];
}

if (($eventType === 'payment' || $eventType === 'payments') && $dataId !== '') {
    try {
        $synced = app_sync_mercadopago_payment($pdo, $dataId);
        echo json_encode([
            'ok' => true,
            'synced' => $synced !== null,
            'payment_id' => $dataId,
        ]);
        exit();
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'sync_failed']);
        exit();
    }
}

echo json_encode(['ok' => true, 'ignored' => true]);
