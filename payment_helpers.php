<?php

function app_public_base_url(): string
{
    $explicit = env_value('PUBLIC_APP_URL');
    if ($explicit !== null) {
        return rtrim($explicit, '/');
    }

    if (PHP_SAPI === 'cli') {
        return '';
    }

    $scheme = app_is_secure_request() ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return '';
    }

    return $scheme . '://' . $host . rtrim(BASE_URL, '/');
}

function app_membership_fee_amount(): float
{
    return (float)env_value('MEMBERSHIP_FEE_AMOUNT', '1500.00');
}

function app_membership_fee_currency(): string
{
    return (string)env_value('MEMBERSHIP_FEE_CURRENCY', 'MXN');
}

function app_membership_fee_label(): string
{
    return (string)env_value('MEMBERSHIP_FEE_LABEL', 'Membresia Anafinet');
}

function app_mercadopago_access_token(): string
{
    return (string)env_value('MERCADOPAGO_ACCESS_TOKEN', '');
}

function app_mercadopago_webhook_secret(): string
{
    return (string)env_value('MERCADOPAGO_WEBHOOK_SECRET', '');
}

function app_mercadopago_use_sandbox(): bool
{
    return env_value('MERCADOPAGO_USE_SANDBOX', '0') === '1';
}

function app_mercadopago_enabled(): bool
{
    return app_mercadopago_access_token() !== '' && app_public_base_url() !== '';
}

function app_mercadopago_api_url(string $path): string
{
    return 'https://api.mercadopago.com' . $path;
}

function app_is_membership_active_status(string $status): bool
{
    return normalize_text_value($status) === 'activo';
}

function app_is_membership_restricted_status(string $status): bool
{
    return !app_is_membership_active_status($status);
}

function app_ensure_membership_payment_schema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pagos_membresia (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            provider VARCHAR(32) NOT NULL,
            external_reference VARCHAR(120) NOT NULL,
            provider_order_id VARCHAR(120) NULL,
            provider_payment_id VARCHAR(120) NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(10) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'initiated',
            status_detail VARCHAR(120) NULL,
            checkout_url TEXT NULL,
            raw_payload LONGTEXT NULL,
            paid_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pagos_membresia_external_reference (external_reference),
            UNIQUE KEY uq_pagos_membresia_provider_payment (provider, provider_payment_id),
            KEY idx_pagos_membresia_user (user_id),
            KEY idx_pagos_membresia_provider_status (provider, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $initialized = true;
}

function app_membership_payment_reference(int $userId): string
{
    return 'membership_user_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4));
}

function app_http_json_request(string $method, string $url, array $headers = [], ?array $body = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extension cURL es requerida para integrar Mercado Pago.');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('No fue posible inicializar cURL.');
    }

    $normalizedHeaders = array_merge(['Accept: application/json'], $headers);
    $payload = null;
    if ($body !== null) {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('No fue posible serializar la solicitud JSON.');
        }
        $normalizedHeaders[] = 'Content-Type: application/json';
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $normalizedHeaders,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $responseBody = curl_exec($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false) {
        throw new RuntimeException('Error de red al consumir API externa: ' . $curlError);
    }

    $decoded = json_decode($responseBody, true);

    return [
        'status' => $statusCode,
        'body' => $decoded,
        'raw' => $responseBody,
    ];
}

function app_create_mercadopago_preference(PDO $pdo, array $user, string $externalReference): array
{
    app_ensure_membership_payment_schema($pdo);

    $baseUrl = app_public_base_url();
    if ($baseUrl === '') {
        throw new RuntimeException('Define PUBLIC_APP_URL para generar URLs publicas de pago.');
    }

    $payload = [
        'items' => [[
            'title' => app_membership_fee_label(),
            'quantity' => 1,
            'currency_id' => app_membership_fee_currency(),
            'unit_price' => app_membership_fee_amount(),
        ]],
        'payer' => [
            'name' => (string)($user['nombre'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
        ],
        'external_reference' => $externalReference,
        'back_urls' => [
            'success' => $baseUrl . '/confirmar_pago.php?provider=mercadopago&mp_return=success&external_reference=' . rawurlencode($externalReference),
            'failure' => $baseUrl . '/confirmar_pago.php?provider=mercadopago&mp_return=failure&external_reference=' . rawurlencode($externalReference),
            'pending' => $baseUrl . '/confirmar_pago.php?provider=mercadopago&mp_return=pending&external_reference=' . rawurlencode($externalReference),
        ],
        'auto_return' => 'approved',
        'notification_url' => $baseUrl . '/webhooks/mercadopago.php',
        'statement_descriptor' => substr(preg_replace('/[^A-Za-z0-9 ]+/', '', app_membership_fee_label()) ?: 'Anafinet', 0, 13),
        'metadata' => [
            'user_id' => (int)($user['id'] ?? 0),
            'membership' => 'access',
        ],
    ];

    $response = app_http_json_request(
        'POST',
        app_mercadopago_api_url('/checkout/preferences'),
        ['Authorization: Bearer ' . app_mercadopago_access_token()],
        $payload
    );

    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($response['body'])) {
        throw new RuntimeException('Mercado Pago no devolvio una preferencia valida.');
    }

    return $response['body'];
}

function app_insert_membership_payment_attempt(PDO $pdo, int $userId, string $provider, string $externalReference): void
{
    app_ensure_membership_payment_schema($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO pagos_membresia (user_id, provider, external_reference, amount, currency, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $provider,
        $externalReference,
        app_membership_fee_amount(),
        app_membership_fee_currency(),
        'initiated',
    ]);
}

function app_update_membership_payment_attempt(PDO $pdo, string $externalReference, array $data): void
{
    app_ensure_membership_payment_schema($pdo);

    $stmt = $pdo->prepare(
        'UPDATE pagos_membresia
         SET provider_order_id = ?, checkout_url = ?, status = ?, raw_payload = ?
         WHERE external_reference = ?'
    );
    $stmt->execute([
        $data['provider_order_id'] ?? null,
        $data['checkout_url'] ?? null,
        $data['status'] ?? 'initiated',
        isset($data['raw_payload']) ? json_encode($data['raw_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $externalReference,
    ]);
}

function app_get_membership_payment_by_external_reference(PDO $pdo, string $externalReference): ?array
{
    app_ensure_membership_payment_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM pagos_membresia WHERE external_reference = ? LIMIT 1');
    $stmt->execute([$externalReference]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function app_get_latest_membership_payment_for_user(PDO $pdo, int $userId, string $provider = 'mercadopago'): ?array
{
    app_ensure_membership_payment_schema($pdo);

    $stmt = $pdo->prepare(
        'SELECT * FROM pagos_membresia WHERE user_id = ? AND provider = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId, $provider]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function app_parse_membership_reference_user_id(string $externalReference): ?int
{
    if (preg_match('/^membership_user_(\d+)_/i', $externalReference, $matches) !== 1) {
        return null;
    }

    return (int)$matches[1];
}

function app_map_mercadopago_status_to_membership(array $payment): array
{
    $status = strtolower((string)($payment['status'] ?? ''));
    $statusDetail = (string)($payment['status_detail'] ?? '');

    if ($status === 'approved') {
        return [
            'payment_status' => 'approved',
            'user_status' => 'Activo',
            'paid_at' => !empty($payment['date_approved']) ? date('Y-m-d H:i:s', strtotime((string)$payment['date_approved'])) : date('Y-m-d H:i:s'),
            'status_detail' => $statusDetail,
        ];
    }

    if (in_array($status, ['pending', 'in_process', 'authorized'], true)) {
        return [
            'payment_status' => 'processing',
            'user_status' => 'Pago en proceso',
            'paid_at' => null,
            'status_detail' => $statusDetail,
        ];
    }

    return [
        'payment_status' => 'failed',
        'user_status' => 'Pendiente de pago',
        'paid_at' => null,
        'status_detail' => $statusDetail !== '' ? $statusDetail : $status,
    ];
}

function app_sync_mercadopago_payment(PDO $pdo, string $paymentId): ?array
{
    app_ensure_membership_payment_schema($pdo);

    $response = app_http_json_request(
        'GET',
        app_mercadopago_api_url('/v1/payments/' . rawurlencode($paymentId)),
        ['Authorization: Bearer ' . app_mercadopago_access_token()]
    );

    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($response['body'])) {
        return null;
    }

    $payment = $response['body'];
    $externalReference = (string)($payment['external_reference'] ?? '');
    if ($externalReference === '') {
        return null;
    }

    $localStatus = app_map_mercadopago_status_to_membership($payment);
    $userId = app_parse_membership_reference_user_id($externalReference);
    if ($userId === null) {
        $existing = app_get_membership_payment_by_external_reference($pdo, $externalReference);
        $userId = $existing ? (int)$existing['user_id'] : null;
    }
    if ($userId === null) {
        return null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO pagos_membresia (
            user_id, provider, external_reference, provider_order_id, provider_payment_id,
            amount, currency, status, status_detail, raw_payload, paid_at
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            provider_order_id = VALUES(provider_order_id),
            provider_payment_id = VALUES(provider_payment_id),
            amount = VALUES(amount),
            currency = VALUES(currency),
            status = VALUES(status),
            status_detail = VALUES(status_detail),
            raw_payload = VALUES(raw_payload),
            paid_at = VALUES(paid_at)'
    );

    $stmt->execute([
        $userId,
        'mercadopago',
        $externalReference,
        isset($payment['order']['id']) ? (string)$payment['order']['id'] : null,
        (string)($payment['id'] ?? ''),
        (float)($payment['transaction_amount'] ?? app_membership_fee_amount()),
        (string)($payment['currency_id'] ?? app_membership_fee_currency()),
        $localStatus['payment_status'],
        $localStatus['status_detail'],
        json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $localStatus['paid_at'],
    ]);

    $userStatus = $localStatus['user_status'];
    $userUpdate = $pdo->prepare('UPDATE usuarios SET estatus = ? WHERE id = ?');
    $userUpdate->execute([$userStatus, $userId]);

    return [
        'external_reference' => $externalReference,
        'payment_id' => (string)($payment['id'] ?? ''),
        'payment_status' => $localStatus['payment_status'],
        'user_status' => $userStatus,
        'raw' => $payment,
    ];
}

function app_validate_mercadopago_webhook(array $server, array $query): bool
{
    $secret = app_mercadopago_webhook_secret();
    if ($secret === '') {
        return false;
    }

    $xSignature = (string)($server['HTTP_X_SIGNATURE'] ?? '');
    $xRequestId = (string)($server['HTTP_X_REQUEST_ID'] ?? '');
    $dataId = '';
    if (isset($query['data.id'])) {
        $dataId = (string)$query['data.id'];
    } elseif (isset($query['data_id'])) {
        $dataId = (string)$query['data_id'];
    } elseif (isset($query['data']) && is_array($query['data']) && isset($query['data']['id'])) {
        $dataId = (string)$query['data']['id'];
    }

    if ($xSignature === '' || $xRequestId === '' || $dataId === '') {
        return false;
    }

    $parts = explode(',', $xSignature);
    $ts = null;
    $hash = null;
    foreach ($parts as $part) {
        $keyValue = explode('=', trim($part), 2);
        if (count($keyValue) !== 2) {
            continue;
        }
        if ($keyValue[0] === 'ts') {
            $ts = $keyValue[1];
        } elseif ($keyValue[0] === 'v1') {
            $hash = $keyValue[1];
        }
    }

    if ($ts === null || $hash === null) {
        return false;
    }

    $manifest = 'id:' . $dataId . ';request-id:' . $xRequestId . ';ts:' . $ts . ';';
    $expected = hash_hmac('sha256', $manifest, $secret);

    return hash_equals($expected, $hash);
}
