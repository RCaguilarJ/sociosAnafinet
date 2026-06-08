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
    $normalized = normalize_text_value($status);
    return in_array($normalized, ['activo', 'afiliado', 'aprobado', 'confirmado', 'pagado'], true);
}

function app_membership_duration_days(): int
{
    return max(30, (int)env_value('MEMBERSHIP_DURATION_DAYS', '365'));
}

function app_membership_warning_days(): int
{
    return max(1, (int)env_value('MEMBERSHIP_RENEWAL_WARNING_DAYS', '30'));
}

function app_membership_renewal_pending_status(): string
{
    return 'Renovacion pendiente';
}

function app_is_membership_restricted_status(string $status): bool
{
    $normalized = normalize_text_value($status);
    if ($normalized === '') {
        return true;
    }

    if (app_is_membership_active_status($status)) {
        return false;
    }

    return in_array($normalized, ['pendientedepago', 'pagoreportado', 'pagoenproceso', 'inactivo', 'suspendido', 'bloqueado', 'renovacionpendiente', 'vencido'], true);
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

function app_ensure_membership_cycle_schema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $requiredColumns = [
        'membership_started_at' => "ALTER TABLE usuarios ADD COLUMN membership_started_at DATETIME NULL AFTER pago_reportado_at",
        'membership_expires_at' => "ALTER TABLE usuarios ADD COLUMN membership_expires_at DATETIME NULL AFTER membership_started_at",
    ];

    $columnExistsStmt = $pdo->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'usuarios'
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

    app_ensure_membership_payment_schema($pdo);

    $initialized = true;
}

function app_get_user_membership_dates(PDO $pdo, int $userId): ?array
{
    app_ensure_membership_cycle_schema($pdo);

    $stmt = $pdo->prepare('SELECT membership_started_at, membership_expires_at FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function app_apply_membership_cycle(PDO $pdo, int $userId, ?string $paidAt = null): ?array
{
    app_ensure_membership_cycle_schema($pdo);

    $dates = app_get_user_membership_dates($pdo, $userId);
    if (!is_array($dates)) {
        return null;
    }

    $effectiveTs = $paidAt !== null && trim($paidAt) !== '' ? strtotime($paidAt) : false;
    if ($effectiveTs === false) {
        $effectiveTs = time();
    }

    $baseTs = $effectiveTs;
    $currentExpiry = trim((string)($dates['membership_expires_at'] ?? ''));
    if ($currentExpiry !== '') {
        $currentExpiryTs = strtotime($currentExpiry);
        if ($currentExpiryTs !== false && $currentExpiryTs > $baseTs) {
            $baseTs = $currentExpiryTs;
        }
    }

    $startAt = date('Y-m-d H:i:s', $effectiveTs);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . app_membership_duration_days() . ' days', $baseTs));

    $stmt = $pdo->prepare('UPDATE usuarios SET membership_started_at = ?, membership_expires_at = ?, estatus = ? WHERE id = ?');
    $stmt->execute([$startAt, $expiresAt, 'Activo', $userId]);

    return [
        'membership_started_at' => $startAt,
        'membership_expires_at' => $expiresAt,
    ];
}

function app_apply_membership_cycle_for_reference(PDO $pdo, int $userId, string $externalReference, ?string $paidAt = null): ?array
{
    $cycle = app_apply_membership_cycle($pdo, $userId, $paidAt);
    if (!is_array($cycle)) {
        return null;
    }

    if (trim($externalReference) !== '') {
        $stmt = $pdo->prepare('UPDATE pagos_membresia SET expires_at = ? WHERE external_reference = ?');
        $stmt->execute([$cycle['membership_expires_at'], $externalReference]);
    }

    return $cycle;
}

function app_backfill_membership_cycle_from_payments(PDO $pdo, int $userId): void
{
    app_ensure_membership_cycle_schema($pdo);

    $dates = app_get_user_membership_dates($pdo, $userId);
    if (!is_array($dates)) {
        return;
    }

    $expiresAt = trim((string)($dates['membership_expires_at'] ?? ''));
    if ($expiresAt !== '') {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT external_reference, paid_at, created_at
         FROM pagos_membresia
         WHERE user_id = ? AND status = 'approved'
         ORDER BY paid_at DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $payment = $stmt->fetch();

    if (!is_array($payment)) {
        return;
    }

    $baseDate = trim((string)($payment['paid_at'] ?? ''));
    if ($baseDate === '') {
        $baseDate = trim((string)($payment['created_at'] ?? ''));
    }

    app_apply_membership_cycle_for_reference($pdo, $userId, (string)($payment['external_reference'] ?? ''), $baseDate !== '' ? $baseDate : null);
}

function app_sync_membership_lifecycle(PDO $pdo, ?int $userId = null, int $limit = 150): void
{
    app_ensure_membership_cycle_schema($pdo);

    $limit = max(1, min($limit, 300));
    if ($userId !== null && $userId > 0) {
        $stmt = $pdo->prepare(
            "SELECT id, estatus, membership_expires_at
             FROM usuarios
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$userId]);
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
    $renewalStatus = app_membership_renewal_pending_status();

    foreach ($users as $user) {
        $targetUserId = (int)($user['id'] ?? 0);
        if ($targetUserId <= 0) {
            continue;
        }

        app_backfill_membership_cycle_from_payments($pdo, $targetUserId);
        $dates = app_get_user_membership_dates($pdo, $targetUserId);
        if (!is_array($dates)) {
            continue;
        }

        $expiresAt = trim((string)($dates['membership_expires_at'] ?? ''));
        if ($expiresAt === '') {
            continue;
        }

        $expiresTs = strtotime($expiresAt);
        if ($expiresTs === false) {
            continue;
        }

        $status = (string)($user['estatus'] ?? '');
        $normalized = normalize_text_value($status);
        $hasRenewalInProgress = in_array($normalized, ['pagoreportado', 'pagoenproceso'], true);

        if ($expiresTs < $now && app_is_membership_active_status($status) && !$hasRenewalInProgress) {
            $update = $pdo->prepare('UPDATE usuarios SET estatus = ? WHERE id = ?');
            $update->execute([$renewalStatus, $targetUserId]);
        }
    }
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

function app_manual_payment_mail_options(): array
{
    $fromEmail = app_payment_mail_from_email();
    $replyTo = app_payment_admin_email();
    if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = $fromEmail;
    }

    return [
        'from_email' => $fromEmail,
        'from_name' => app_payment_mail_from_name(),
        'reply_to' => $replyTo,
    ];
}

function app_send_manual_payment_received_notifications(PDO $pdo, int $userId, string $reference, string $proofUrl = ''): void
{
    $user = app_fetch_membership_user($pdo, $userId);
    if (!is_array($user)) {
        error_log('Manual payment received notification skipped: user not found for ID ' . $userId);
        return;
    }

    $userName = trim((string)($user['nombre'] ?? 'Asociado'));
    $userEmail = trim((string)($user['email'] ?? ''));
    $adminEmail = app_payment_admin_email();
    $portalUrl = app_public_base_url();
    $paymentPageUrl = $portalUrl !== '' ? $portalUrl . '/confirmar_pago.php' : '';
    $adminPageUrl = $portalUrl !== '' ? $portalUrl . '/revisar_pagos.php' : '';
    $amountLabel = app_payment_money_label(app_membership_fee_amount(), app_membership_fee_currency());
    $reportedAtLabel = date('Y-m-d H:i:s');
    $mailOptions = app_manual_payment_mail_options();

    if (filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $userSubject = 'Recibimos tu comprobante de pago en Anafinet';
        $userIntroHtml = '<p style="margin:0 0 16px 0;">Hola ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '.</p>'
            . '<p style="margin:0 0 16px 0;">Recibimos correctamente tu comprobante de pago y ya quedo registrado en Anafinet.</p>'
            . '<p style="margin:0;">Tesoreria revisara el monto, la cuenta de deposito y la evidencia adjunta. En cuanto quede aprobado, tu acceso se activara.</p>';
        $userSummaryHtml = app_mail_payment_summary_rows([
            'Estatus' => 'Comprobante recibido',
            'Concepto' => 'Afiliacion Anafinet',
            'Monto esperado' => $amountLabel,
            'Referencia' => $reference,
            'Revision' => 'Validacion manual por tesoreria',
            'Fecha de registro' => $reportedAtLabel,
        ]);
        $userButtonHtml = app_mail_button($paymentPageUrl, 'Ver mi confirmacion');
        $userFooterHtml = '<p style="margin:0 0 12px 0;">Tu acceso total se liberara cuando el pago sea validado manualmente.</p>'
            . ($paymentPageUrl !== '' ? '<p style="margin:0;">Si necesitas revisar tu estatus, entra aqui:<br><a href="' . htmlspecialchars($paymentPageUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563EB;">' . htmlspecialchars($paymentPageUrl, ENT_QUOTES, 'UTF-8') . '</a></p>' : '');
        $userHtml = app_mail_wrap_layout(
            'Comprobante recibido',
            'Tu pago esta en revision',
            $userIntroHtml,
            $userSummaryHtml,
            $userButtonHtml,
            $userFooterHtml,
            'Recibimos tu comprobante y ya esta en revision por tesoreria.'
        );
        $userText = "Hola {$userName}.\n\n"
            . "Recibimos correctamente tu comprobante de pago y ya quedo registrado en Anafinet.\n"
            . "Tesoreria revisara el monto, la cuenta de deposito y la evidencia adjunta. En cuanto quede aprobado, tu acceso se activara.\n"
            . "Estatus: Comprobante recibido\n"
            . "Concepto: Afiliacion Anafinet\n"
            . "Monto esperado: {$amountLabel}\n"
            . "Referencia: {$reference}\n"
            . "Revision: Validacion manual por tesoreria\n"
            . "Fecha de registro: {$reportedAtLabel}\n"
            . ($paymentPageUrl !== '' ? "Seguimiento: {$paymentPageUrl}\n" : '');
        if (!app_send_html_email($userEmail, $userSubject, $userHtml, $userText, $mailOptions)) {
            error_log('Manual payment received notification failed for user ' . $userId . ' (' . $userEmail . ')');
        }
    }

    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $adminSubject = 'Nuevo comprobante manual por revisar: ' . $userName;
        $adminIntroHtml = '<p style="margin:0 0 16px 0;">Se registro un nuevo comprobante de pago manual para revision.</p>'
            . '<p style="margin:0;">Valida el monto, la cuenta de deposito y el archivo adjunto antes de aprobar el acceso del asociado.</p>';
        $adminSummaryHtml = app_mail_payment_summary_rows([
            'Nombre' => $userName,
            'Email' => $userEmail,
            'Referencia' => $reference,
            'Monto esperado' => $amountLabel,
            'Fecha de registro' => $reportedAtLabel,
        ]);
        $adminButtonHtml = app_mail_button($adminPageUrl, 'Revisar pago en el panel');
        $adminFooterParts = [];
        if ($proofUrl !== '') {
            $adminFooterParts[] = '<p style="margin:0 0 10px 0;">Comprobante adjunto:<br><a href="' . htmlspecialchars($proofUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563EB;">' . htmlspecialchars($proofUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }
        if ($adminPageUrl !== '') {
            $adminFooterParts[] = '<p style="margin:0;">Panel administrativo:<br><a href="' . htmlspecialchars($adminPageUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563EB;">' . htmlspecialchars($adminPageUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';
        }
        $adminHtml = app_mail_wrap_layout(
            'Revision manual',
            'Nuevo comprobante por validar',
            $adminIntroHtml,
            $adminSummaryHtml,
            $adminButtonHtml,
            implode('', $adminFooterParts),
            'Se registro un nuevo comprobante de pago manual para revision.'
        );
        $adminText = "Se registro un nuevo comprobante de pago manual para revision.\n"
            . "Nombre: {$userName}\n"
            . "Email: {$userEmail}\n"
            . "Referencia: {$reference}\n"
            . "Monto esperado: {$amountLabel}\n"
            . "Fecha de registro: {$reportedAtLabel}\n"
            . ($proofUrl !== '' ? "Comprobante: {$proofUrl}\n" : '')
            . ($adminPageUrl !== '' ? "Panel: {$adminPageUrl}\n" : '');
        if (!app_send_html_email($adminEmail, $adminSubject, $adminHtml, $adminText, $mailOptions)) {
            error_log('Manual payment received admin notification failed for user ' . $userId . ' to ' . $adminEmail);
        }
    }
}

function app_send_manual_payment_approved_notification(PDO $pdo, int $userId): bool
{
    $user = app_fetch_membership_user($pdo, $userId);
    if (!is_array($user)) {
        error_log('Manual payment approved notification skipped: user not found for ID ' . $userId);
        return false;
    }

    $userEmail = trim((string)($user['email'] ?? ''));
    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('Manual payment approved notification skipped: invalid email for user ' . $userId . ' (' . $userEmail . ')');
        return false;
    }

    $userName = trim((string)($user['nombre'] ?? 'Asociado'));
    $portalUrl = app_public_base_url();
    $dashboardUrl = $portalUrl !== '' ? $portalUrl . '/dashboard.php' : '';
    $approvedAtLabel = date('Y-m-d H:i:s');
    $amountLabel = app_payment_money_label(app_membership_fee_amount(), app_membership_fee_currency());
    $mailOptions = app_manual_payment_mail_options();

    $subject = 'Tu comprobante fue aprobado y tu acceso ya esta activo';
    $introHtml = '<p style="margin:0 0 16px 0;">Hola ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '.</p>'
        . '<p style="margin:0 0 16px 0;">Tu comprobante fue validado correctamente por tesoreria.</p>'
        . '<p style="margin:0;">Tu afiliacion ya esta activa y puedes entrar a tu panel para usar el portal completo.</p>';
    $summaryHtml = app_mail_payment_summary_rows([
        'Estatus' => 'Pago aprobado manualmente',
        'Concepto' => 'Afiliacion Anafinet',
        'Monto validado' => $amountLabel,
        'Acceso' => 'Portal completo habilitado',
        'Fecha de aprobacion' => $approvedAtLabel,
    ]);
    $buttonHtml = app_mail_button($dashboardUrl, 'Entrar a mi panel');
    $footerHtml = $dashboardUrl !== ''
        ? '<p style="margin:0;">Si el boton no funciona, copia y pega este enlace en tu navegador:<br><a href="' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563EB;">' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
        : '';
    $html = app_mail_wrap_layout(
        'Pago aprobado',
        'Tu afiliacion ya esta activa',
        $introHtml,
        $summaryHtml,
        $buttonHtml,
        $footerHtml,
        'Tu comprobante fue aprobado y tu acceso al portal ya esta activo.'
    );
    $text = "Hola {$userName}.\n\n"
        . "Tu comprobante fue validado correctamente por tesoreria.\n"
        . "Tu afiliacion ya esta activa y puedes entrar a tu panel para usar el portal completo.\n"
        . "Estatus: Pago aprobado manualmente\n"
        . "Concepto: Afiliacion Anafinet\n"
        . "Monto validado: {$amountLabel}\n"
        . "Acceso: Portal completo habilitado\n"
        . "Fecha de aprobacion: {$approvedAtLabel}\n"
        . ($dashboardUrl !== '' ? "Panel: {$dashboardUrl}\n" : '');
    $sent = app_send_html_email($userEmail, $subject, $html, $text, $mailOptions);
    if (!$sent) {
        error_log('Manual payment approved notification failed for user ' . $userId . ' (' . $userEmail . ')');
    }

    return $sent;
}

function app_send_manual_payment_activation_notification_if_needed(PDO $pdo, int $userId, string $previousStatus, string $newStatus): bool
{
    if (app_is_membership_active_status($previousStatus) || !app_is_membership_active_status($newStatus)) {
        return true;
    }

    return app_send_manual_payment_approved_notification($pdo, $userId);
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
            $adminHtml = app_mail_wrap_layout(
                'Pago confirmado',
                'Nuevo usuario pagado',
                '<p style="margin:0 0 16px 0;">Se confirmo un pago exitoso de afiliacion para un nuevo usuario.</p>'
                    . '<p style="margin:0;">El asociado ya puede ser identificado en el portal como usuario activo.</p>',
                app_mail_payment_summary_rows([
                    'Nombre' => $userName,
                    'Email' => $userEmail,
                    'Pasarela' => $providerLabel,
                    'Monto' => $amountLabel,
                    'Fecha de pago' => $paidAtLabel,
                ]),
                '',
                '',
                'Se confirmo un pago exitoso de afiliacion para un nuevo usuario.'
            );
            $adminText = "Se confirmo un pago exitoso de afiliacion para un nuevo usuario.\n"
                . "Nombre: {$userName}\n"
                . "Email: {$userEmail}\n"
                . "Pasarela: {$providerLabel}\n"
                . "Monto: {$amountLabel}\n"
                . "Fecha de pago: {$paidAtLabel}\n";
        } else {
            $adminSubject = 'Renovacion pagada correctamente: ' . $userName;
            $adminHtml = app_mail_wrap_layout(
                'Pago confirmado',
                'Renovacion aplicada',
                '<p style="margin:0 0 16px 0;">Se confirmo un pago exitoso de renovacion para un usuario existente.</p>'
                    . '<p style="margin:0;">El asociado conserva su acceso activo al portal.</p>',
                app_mail_payment_summary_rows([
                    'Nombre' => $userName,
                    'Email' => $userEmail,
                    'Pasarela' => $providerLabel,
                    'Monto' => $amountLabel,
                    'Fecha de pago' => $paidAtLabel,
                ]),
                '',
                '',
                'Se confirmo un pago exitoso de renovacion para un usuario existente.'
            );
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
        } else {
            error_log('Membership payment admin notification failed for reference ' . $externalReference . ' to ' . $adminEmail);
        }
    }

    if (($payment['notification_user_sent_at'] ?? null) === null && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        if ($context === 'signup') {
            $userSubject = 'Tu pago y afiliacion en Anafinet fueron confirmados';
            $userIntroHtml = '<p style="margin:0 0 16px 0;">Hola ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '.</p>'
                . '<p style="margin:0 0 16px 0;">Tu pago se confirmo correctamente y tu afiliacion a Anafinet quedo completada con exito.</p>'
                . '<p style="margin:0;">Ya puedes entrar a tu panel y comenzar a usar los beneficios de tu membresia.</p>';
            $userSummaryHtml = app_mail_payment_summary_rows([
                'Estatus' => 'Pago confirmado',
                'Concepto' => 'Afiliacion Anafinet',
                'Pasarela' => $providerLabel,
                'Monto' => $amountLabel,
                'Fecha de pago' => $paidAtLabel,
            ]);
            $userButtonHtml = app_mail_button($dashboardUrl, 'Entrar a mi panel');
            $userFooterHtml = '<p style="margin:0 0 12px 0;">Te damos la bienvenida a la afiliacion.</p>'
                . ($dashboardUrl !== '' ? '<p style="margin:0;">Si el boton no funciona, copia y pega este enlace en tu navegador:<br><a href="' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563EB;">' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '</a></p>' : '');
            $userHtml = app_mail_wrap_layout(
                'Confirmacion de pago',
                'Tu afiliacion ya esta activa',
                $userIntroHtml,
                $userSummaryHtml,
                $userButtonHtml,
                $userFooterHtml,
                'Tu pago fue confirmado y tu afiliacion a Anafinet ya esta activa.'
            );
            $userText = "Hola {$userName}.\n\n"
                . "Tu pago se confirmo correctamente y tu afiliacion a Anafinet quedo completada con exito.\n"
                . "Tu afiliacion ya esta activa.\n"
                . "Estatus: Pago confirmado\n"
                . "Concepto: Afiliacion Anafinet\n"
                . "Pasarela: {$providerLabel}\n"
                . "Monto: {$amountLabel}\n"
                . "Fecha de pago: {$paidAtLabel}\n"
                . "Te damos la bienvenida a la afiliacion.\n"
                . ($dashboardUrl !== '' ? "Panel: {$dashboardUrl}\n" : '');
        } else {
            $userSubject = 'Tu renovacion en Anafinet fue exitosa';
            $userIntroHtml = '<p style="margin:0 0 16px 0;">Hola ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '.</p>'
                . '<p style="margin:0 0 16px 0;">Tu renovacion como usuario fue confirmada exitosamente.</p>'
                . '<p style="margin:0;">Tu acceso permanece activo y puedes seguir usando tu panel con normalidad.</p>';
            $userSummaryHtml = app_mail_payment_summary_rows([
                'Estatus' => 'Renovacion confirmada',
                'Concepto' => 'Renovacion de membresia',
                'Pasarela' => $providerLabel,
                'Monto' => $amountLabel,
                'Fecha de pago' => $paidAtLabel,
            ]);
            $userButtonHtml = app_mail_button($dashboardUrl, 'Ir a mi panel');
            $userFooterHtml = $dashboardUrl !== ''
                ? '<p style="margin:0;">Si el boton no funciona, copia y pega este enlace en tu navegador:<br><a href="' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563EB;">' . htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
                : '';
            $userHtml = app_mail_wrap_layout(
                'Confirmacion de renovacion',
                'Tu renovacion fue aplicada',
                $userIntroHtml,
                $userSummaryHtml,
                $userButtonHtml,
                $userFooterHtml,
                'Tu renovacion en Anafinet fue confirmada exitosamente.'
            );
            $userText = "Hola {$userName}.\n\n"
                . "Tu renovacion como usuario fue confirmada exitosamente.\n"
                . "Estatus: Renovacion confirmada\n"
                . "Concepto: Renovacion de membresia\n"
                . "Pasarela: {$providerLabel}\n"
                . "Monto: {$amountLabel}\n"
                . "Fecha de pago: {$paidAtLabel}\n"
                . ($dashboardUrl !== '' ? "Panel: {$dashboardUrl}\n" : '');
        }

        if (app_send_html_email($userEmail, $userSubject, $userHtml, $userText)) {
            $stmt = $pdo->prepare('UPDATE pagos_membresia SET notification_user_sent_at = NOW() WHERE external_reference = ?');
            $stmt->execute([$externalReference]);
        } else {
            error_log('Membership payment user notification failed for reference ' . $externalReference . ' to ' . $userEmail);
        }
    }
}

function app_retry_pending_membership_notifications(PDO $pdo, ?int $userId = null, int $limit = 5): void
{
    app_ensure_membership_payment_schema($pdo);

    $limit = max(1, min($limit, 25));
    $sql = 'SELECT external_reference
            FROM pagos_membresia
            WHERE status = ? 
              AND (notification_user_sent_at IS NULL OR notification_admin_sent_at IS NULL)';
    $params = ['approved'];

    if ($userId !== null && $userId > 0) {
        $sql .= ' AND user_id = ?';
        $params[] = $userId;
    }

    $sql .= ' ORDER BY paid_at DESC, id DESC LIMIT ' . $limit;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $references = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($references as $externalReference) {
            if (!is_string($externalReference) || trim($externalReference) === '') {
                continue;
            }

            try {
                app_send_membership_payment_notifications($pdo, $externalReference);
            } catch (Throwable $e) {
                error_log('Retry membership payment notification failed for reference ' . $externalReference . ': ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('Unable to query pending membership notifications: ' . $e->getMessage());
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

    if ($mappedStatus['payment_status'] === 'approved') {
        app_apply_membership_cycle_for_reference($pdo, $userId, $externalReference, $mappedStatus['paid_at']);
    } else {
        $userUpdate = $pdo->prepare('UPDATE usuarios SET estatus = ? WHERE id = ?');
        $userUpdate->execute([$mappedStatus['user_status'], $userId]);
    }

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

    if ($localStatus['payment_status'] === 'approved') {
        app_apply_membership_cycle_for_reference($pdo, $userId, $externalReference, $localStatus['paid_at']);
    } else {
        $userStatus = $localStatus['user_status'];
        $userUpdate = $pdo->prepare('UPDATE usuarios SET estatus = ? WHERE id = ?');
        $userUpdate->execute([$userStatus, $userId]);
    }

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
