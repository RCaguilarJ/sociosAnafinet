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

function app_paypal_client_id(): string
{
    return (string)env_value('PAYPAL_CLIENT_ID', '');
}

function app_paypal_client_secret(): string
{
    return (string)env_value('PAYPAL_CLIENT_SECRET', '');
}

function app_paypal_use_sandbox(): bool
{
    return env_value('PAYPAL_USE_SANDBOX', '0') === '1';
}

function app_paypal_enabled(): bool
{
    return app_paypal_client_id() !== '' && app_paypal_client_secret() !== '' && app_public_base_url() !== '';
}

function app_paypal_api_base_url(): string
{
    return app_paypal_use_sandbox()
        ? 'https://api-m.sandbox.paypal.com'
        : 'https://api-m.paypal.com';
}

function app_mercadopago_public_key(): string
{
    return (string)env_value('MERCADOPAGO_PUBLIC_KEY', '');
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
            notification_context VARCHAR(32) NULL,
            notification_admin_sent_at DATETIME NULL,
            notification_user_sent_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pagos_membresia_external_reference (external_reference),
            UNIQUE KEY uq_pagos_membresia_provider_payment (provider, provider_payment_id),
            KEY idx_pagos_membresia_user (user_id),
            KEY idx_pagos_membresia_provider_status (provider, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $requiredColumns = [
        'notification_context' => "ALTER TABLE pagos_membresia ADD COLUMN notification_context VARCHAR(32) NULL AFTER expires_at",
        'notification_admin_sent_at' => "ALTER TABLE pagos_membresia ADD COLUMN notification_admin_sent_at DATETIME NULL AFTER notification_context",
        'notification_user_sent_at' => "ALTER TABLE pagos_membresia ADD COLUMN notification_user_sent_at DATETIME NULL AFTER notification_admin_sent_at",
    ];

    $columnExistsStmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'pagos_membresia'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );

    foreach ($requiredColumns as $column => $alterSql) {
        $columnExistsStmt->execute([$column]);
        $exists = (bool)$columnExistsStmt->fetchColumn();
        if (!$exists) {
            $pdo->exec($alterSql);
        }
    }

    $initialized = true;
}

function app_is_signup_membership_reference(string $externalReference): bool
{
    return preg_match('/^membership_signup_/i', $externalReference) === 1;
}

function app_membership_payment_context(string $externalReference): string
{
    return app_is_signup_membership_reference($externalReference) ? 'signup' : 'renewal';
}

function app_fetch_membership_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT id, nombre, email, rol, estatus FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function app_payment_provider_label(string $provider): string
{
    $provider = strtolower(trim($provider));
    if ($provider === 'mercadopago') {
        return 'Mercado Pago';
    }
    if ($provider === 'paypal') {
        return 'PayPal';
    }

    return ucfirst($provider);
}

function app_payment_money_label(float $amount, string $currency): string
{
    return number_format($amount, 2, '.', ',') . ' ' . strtoupper($currency);
}

function app_send_membership_payment_notifications(PDO $pdo, string $externalReference): void
{
    app_ensure_membership_payment_schema($pdo);

    $payment = app_get_membership_payment_by_external_reference($pdo, $externalReference);
    if (!is_array($payment) || (string)($payment['status'] ?? '') !== 'approved') {
        return;
    }

    $userId = (int)($payment['user_id'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    $user = app_fetch_membership_user($pdo, $userId);
    if (!is_array($user)) {
        return;
    }

    $context = (string)($payment['notification_context'] ?? '');
    if ($context === '') {
        $context = app_membership_payment_context($externalReference);
        $stmt = $pdo->prepare('UPDATE pagos_membresia SET notification_context = ? WHERE external_reference = ?');
        $stmt->execute([$context, $externalReference]);
    }

    $providerLabel = app_payment_provider_label((string)($payment['provider'] ?? ''));
    $amountLabel = app_payment_money_label(
        (float)($payment['amount'] ?? app_membership_fee_amount()),
        (string)($payment['currency'] ?? app_membership_fee_currency())
    );
    $paidAt = (string)($payment['paid_at'] ?? '');
    $paidAtLabel = $paidAt !== '' ? $paidAt : date('Y-m-d H:i:s');
    $portalUrl = app_public_base_url();
    $dashboardUrl = $portalUrl !== '' ? $portalUrl . '/dashboard.php' : '';
    $userName = trim((string)($user['nombre'] ?? 'Asociado'));
    $userEmail = trim((string)($user['email'] ?? ''));
    $adminEmail = app_payment_admin_email();

    if (($payment['notification_admin_sent_at'] ?? null) === null && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        if ($context === 'signup') {
            $adminSubject = 'Nuevo usuario con afiliacion pagada: ' . $userName;
            $adminHtml = '<p>Se confirmo un pago exitoso de afiliacion para un nuevo usuario.</p>'
                . '<p><strong>Nombre:</strong> ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Email:</strong> ' . htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Pasarela:</strong> ' . htmlspecialchars($providerLabel, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Monto:</strong> ' . htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Fecha de pago:</strong> ' . htmlspecialchars($paidAtLabel, ENT_QUOTES, 'UTF-8') . '</p>';
            $adminText = "Se confirmo un pago exitoso de afiliacion para un nuevo usuario.\n"
                . "Nombre: {$userName}\n"
                . "Email: {$userEmail}\n"
                . "Pasarela: {$providerLabel}\n"
                . "Monto: {$amountLabel}\n"
                . "Fecha de pago: {$paidAtLabel}\n";
        } else {
            $adminSubject = 'Renovacion pagada correctamente: ' . $userName;
            $adminHtml = '<p>Se confirmo un pago exitoso de renovacion para un usuario existente.</p>'
                . '<p><strong>Nombre:</strong> ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Email:</strong> ' . htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Pasarela:</strong> ' . htmlspecialchars($providerLabel, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Monto:</strong> ' . htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Fecha de pago:</strong> ' . htmlspecialchars($paidAtLabel, ENT_QUOTES, 'UTF-8') . '</p>';
            $adminText = "Se confirmo un pago exitoso de renovacion para un usuario existente.\n"
                . "Nombre: {$userName}\n"
                . "Email: {$userEmail}\n"
                . "Pasarela: {$providerLabel}\n"
                . "Monto: {$amountLabel}\n"
                . "Fecha de pago: {$paidAtLabel}\n";
        }

        if (app_send_html_email($adminEmail, $adminSubject, $adminHtml, $adminText)) {
            $stmt = $pdo->prepare('UPDATE pagos_membresia SET notification_admin_sent_at = NOW() WHERE external_reference = ?');
            $stmt->execute([$externalReference]);
        }
    }

    if (($payment['notification_user_sent_at'] ?? null) === null && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        if ($context === 'signup') {
            $userSubject = 'Tu pago y afiliacion en Anafinet fueron confirmados';
            $userHtml = '<p>Hola ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '.</p>'
                . '<p>Tu pago se confirmo correctamente y tu afiliacion a Anafinet quedo completada con exito.</p>'
                . '<p>Te damos la bienvenida a la afiliacion.</p>'
                . '<p><strong>Pasarela:</strong> ' . htmlspecialchars($providerLabel, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Monto:</strong> ' . htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') . '</p>'
                . ($dashboardUrl !== '' ? '<p>Puedes ingresar a tu panel aqui: <a href="' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '</a></p>' : '');
            $userText = "Hola {$userName}.\n\n"
                . "Tu pago se confirmo correctamente y tu afiliacion a Anafinet quedo completada con exito.\n"
                . "Te damos la bienvenida a la afiliacion.\n"
                . "Pasarela: {$providerLabel}\n"
                . "Monto: {$amountLabel}\n"
                . ($dashboardUrl !== '' ? "Panel: {$dashboardUrl}\n" : '');
        } else {
            $userSubject = 'Tu renovacion en Anafinet fue exitosa';
            $userHtml = '<p>Hola ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '.</p>'
                . '<p>Tu renovacion como usuario fue confirmada exitosamente.</p>'
                . '<p><strong>Pasarela:</strong> ' . htmlspecialchars($providerLabel, ENT_QUOTES, 'UTF-8') . '<br>'
                . '<strong>Monto:</strong> ' . htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8') . '</p>'
                . ($dashboardUrl !== '' ? '<p>Puedes continuar usando tu panel aqui: <a href="' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '</a></p>' : '');
            $userText = "Hola {$userName}.\n\n"
                . "Tu renovacion como usuario fue confirmada exitosamente.\n"
                . "Pasarela: {$providerLabel}\n"
                . "Monto: {$amountLabel}\n"
                . ($dashboardUrl !== '' ? "Panel: {$dashboardUrl}\n" : '');
        }

        if (app_send_html_email($userEmail, $userSubject, $userHtml, $userText)) {
            $stmt = $pdo->prepare('UPDATE pagos_membresia SET notification_user_sent_at = NOW() WHERE external_reference = ?');
            $stmt->execute([$externalReference]);
        }
    }
}

function app_membership_payment_reference(int $userId): string
{
    return 'membership_user_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4));
}

function app_membership_signup_reference(): string
{
    return 'membership_signup_' . time() . '_' . bin2hex(random_bytes(6));
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

function app_http_form_request(string $method, string $url, array $headers = [], array $formData = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extension cURL es requerida para integrar pasarelas de pago.');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('No fue posible inicializar cURL.');
    }

    $normalizedHeaders = $headers;
    $normalizedHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
    $payload = http_build_query($formData, '', '&');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $normalizedHeaders,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POSTFIELDS => $payload,
    ]);

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

function app_create_mercadopago_preference(PDO $pdo, array $user, string $externalReference, array $options = []): array
{
    app_ensure_membership_payment_schema($pdo);

    $baseUrl = app_public_base_url();
    if ($baseUrl === '') {
        throw new RuntimeException('Define PUBLIC_APP_URL para generar URLs publicas de pago.');
    }

    $backUrls = $options['back_urls'] ?? [
        'success' => $baseUrl . '/confirmar_pago.php?provider=mercadopago&mp_return=success&external_reference=' . rawurlencode($externalReference),
        'failure' => $baseUrl . '/confirmar_pago.php?provider=mercadopago&mp_return=failure&external_reference=' . rawurlencode($externalReference),
        'pending' => $baseUrl . '/confirmar_pago.php?provider=mercadopago&mp_return=pending&external_reference=' . rawurlencode($externalReference),
    ];

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
        'back_urls' => $backUrls,
        'auto_return' => (string)($options['auto_return'] ?? 'approved'),
        'notification_url' => (string)($options['notification_url'] ?? ($baseUrl . '/webhooks/mercadopago.php')),
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

function app_paypal_access_token(): string
{
    $clientId = app_paypal_client_id();
    $clientSecret = app_paypal_client_secret();
    if ($clientId === '' || $clientSecret === '') {
        throw new RuntimeException('PayPal no esta configurado en este ambiente.');
    }

    $response = app_http_form_request(
        'POST',
        app_paypal_api_base_url() . '/v1/oauth2/token',
        [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
        ],
        ['grant_type' => 'client_credentials']
    );

    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($response['body'])) {
        throw new RuntimeException('PayPal no devolvio un token valido.');
    }

    $token = (string)($response['body']['access_token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('PayPal no devolvio access_token.');
    }

    return $token;
}

function app_get_paypal_order(string $orderId): array
{
    $response = app_http_json_request(
        'GET',
        app_paypal_api_base_url() . '/v2/checkout/orders/' . rawurlencode($orderId),
        [
            'Authorization: Bearer ' . app_paypal_access_token(),
        ]
    );

    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($response['body'])) {
        throw new RuntimeException('PayPal no devolvio una orden valida.');
    }

    return $response['body'];
}

function app_capture_paypal_order(string $orderId): array
{
    $response = app_http_json_request(
        'POST',
        app_paypal_api_base_url() . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture',
        [
            'Authorization: Bearer ' . app_paypal_access_token(),
            'PayPal-Request-Id: ' . $orderId . '-' . bin2hex(random_bytes(4)),
        ],
        []
    );

    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($response['body'])) {
        throw new RuntimeException('PayPal no devolvio una captura valida para la orden.');
    }

    return $response['body'];
}

function app_resolve_paypal_order(string $orderId): array
{
    $order = app_get_paypal_order($orderId);
    $orderStatus = strtoupper((string)($order['status'] ?? ''));
    $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
    $captureStatus = strtoupper((string)($capture['status'] ?? ''));

    if ($orderStatus === 'COMPLETED' || $captureStatus === 'COMPLETED') {
        return $order;
    }

    if (in_array($orderStatus, ['CREATED', 'SAVED', 'APPROVED', 'PAYER_ACTION_REQUIRED'], true)) {
        return app_capture_paypal_order($orderId);
    }

    return $order;
}

function app_map_paypal_status_to_membership(array $order): array
{
    $orderStatus = strtoupper((string)($order['status'] ?? ''));
    $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? null;
    $captureStatus = strtoupper((string)($capture['status'] ?? ''));

    if ($orderStatus === 'COMPLETED' || $captureStatus === 'COMPLETED') {
        return [
            'payment_status' => 'approved',
            'user_status' => 'Activo',
            'paid_at' => !empty($capture['create_time']) ? date('Y-m-d H:i:s', strtotime((string)$capture['create_time'])) : date('Y-m-d H:i:s'),
            'status_detail' => $captureStatus !== '' ? $captureStatus : $orderStatus,
        ];
    }

    if (in_array($orderStatus, ['APPROVED', 'PAYER_ACTION_REQUIRED'], true) || in_array($captureStatus, ['PENDING'], true)) {
        return [
            'payment_status' => 'processing',
            'user_status' => 'Pago en proceso',
            'paid_at' => null,
            'status_detail' => $captureStatus !== '' ? $captureStatus : $orderStatus,
        ];
    }

    return [
        'payment_status' => 'failed',
        'user_status' => 'Pendiente de pago',
        'paid_at' => null,
        'status_detail' => $captureStatus !== '' ? $captureStatus : ($orderStatus !== '' ? $orderStatus : 'UNKNOWN'),
    ];
}

function app_sync_paypal_order(PDO $pdo, int $userId, string $externalReference, string $orderId): array
{
    app_ensure_membership_payment_schema($pdo);

    $capturedOrder = app_resolve_paypal_order($orderId);
    $purchaseUnit = $capturedOrder['purchase_units'][0] ?? [];
    if ($externalReference === '') {
        $externalReference = (string)($purchaseUnit['custom_id'] ?? '');
    }
    if ($externalReference === '') {
        $externalReference = app_membership_payment_reference($userId);
    }
    $capture = $purchaseUnit['payments']['captures'][0] ?? [];
    $mappedStatus = app_map_paypal_status_to_membership($capturedOrder);
    $amountValue = $capture['amount']['value'] ?? $purchaseUnit['amount']['value'] ?? app_membership_fee_amount();
    $currency = $capture['amount']['currency_code'] ?? $purchaseUnit['amount']['currency_code'] ?? app_membership_fee_currency();
    $captureId = (string)($capture['id'] ?? '');
    $notificationContext = app_membership_payment_context($externalReference);

    $stmt = $pdo->prepare(
        'INSERT INTO pagos_membresia (
            user_id, provider, external_reference, provider_order_id, provider_payment_id,
            amount, currency, status, status_detail, raw_payload, paid_at, notification_context
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            provider_order_id = VALUES(provider_order_id),
            provider_payment_id = VALUES(provider_payment_id),
            amount = VALUES(amount),
            currency = VALUES(currency),
            status = VALUES(status),
            status_detail = VALUES(status_detail),
            raw_payload = VALUES(raw_payload),
            paid_at = VALUES(paid_at),
            notification_context = COALESCE(notification_context, VALUES(notification_context))'
    );

    $stmt->execute([
        $userId,
        'paypal',
        $externalReference,
        $orderId,
        $captureId !== '' ? $captureId : null,
        (float)$amountValue,
        (string)$currency,
        $mappedStatus['payment_status'],
        $mappedStatus['status_detail'],
        json_encode($capturedOrder, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $mappedStatus['paid_at'],
        $notificationContext,
    ]);

    $userUpdate = $pdo->prepare('UPDATE usuarios SET estatus = ? WHERE id = ?');
    $userUpdate->execute([$mappedStatus['user_status'], $userId]);

    if ($mappedStatus['payment_status'] === 'approved') {
        app_send_membership_payment_notifications($pdo, $externalReference);
    }

    return [
        'external_reference' => $externalReference,
        'order_id' => $orderId,
        'payment_id' => $captureId,
        'payment_status' => $mappedStatus['payment_status'],
        'user_status' => $mappedStatus['user_status'],
        'raw' => $capturedOrder,
    ];
}

function app_insert_membership_payment_attempt(PDO $pdo, int $userId, string $provider, string $externalReference): void
{
    app_ensure_membership_payment_schema($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO pagos_membresia (user_id, provider, external_reference, amount, currency, status, notification_context)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $provider,
        $externalReference,
        app_membership_fee_amount(),
        app_membership_fee_currency(),
        'initiated',
        app_membership_payment_context($externalReference),
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
    $notificationContext = app_membership_payment_context($externalReference);

    $stmt = $pdo->prepare(
        'INSERT INTO pagos_membresia (
            user_id, provider, external_reference, provider_order_id, provider_payment_id,
            amount, currency, status, status_detail, raw_payload, paid_at, notification_context
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            provider_order_id = VALUES(provider_order_id),
            provider_payment_id = VALUES(provider_payment_id),
            amount = VALUES(amount),
            currency = VALUES(currency),
            status = VALUES(status),
            status_detail = VALUES(status_detail),
            raw_payload = VALUES(raw_payload),
            paid_at = VALUES(paid_at),
            notification_context = COALESCE(notification_context, VALUES(notification_context))'
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
        $notificationContext,
    ]);

    $userStatus = $localStatus['user_status'];
    $userUpdate = $pdo->prepare('UPDATE usuarios SET estatus = ? WHERE id = ?');
    $userUpdate->execute([$userStatus, $userId]);

    if ($localStatus['payment_status'] === 'approved') {
        app_send_membership_payment_notifications($pdo, $externalReference);
    }

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
