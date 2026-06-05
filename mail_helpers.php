<?php

function app_payment_admin_email(): string
{
    return (string)env_value('PAYMENT_ADMIN_EMAIL', 'tesoreria@anafinet.mx');
}

function app_mail_from_email(): string
{
    $configured = (string)env_value('MAIL_FROM_EMAIL', '');
    if ($configured === '') {
        $configured = (string)env_value('MAIL_FROM_ADDRESS', '');
    }

    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
        return $configured;
    }

    $adminEmail = app_payment_admin_email();
    if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        return $adminEmail;
    }

    return 'no-reply@anafinet.mx';
}

function app_mail_from_name(): string
{
    $configured = (string)env_value('MAIL_FROM_NAME', '');
    if ($configured === '') {
        $configured = (string)env_value('MAIL_FROM_NAME', 'Anafinet');
    }

    return $configured !== '' ? $configured : 'Anafinet';
}

function app_mail_transport(): string
{
    $transport = (string)env_value('MAIL_TRANSPORT', '');
    if ($transport === '') {
        $transport = (string)env_value('MAIL_MAILER', 'mail');
    }

    $transport = strtolower(trim($transport));
    return in_array($transport, ['mail', 'smtp'], true) ? $transport : 'mail';
}

function app_smtp_host(): string
{
    $host = (string)env_value('SMTP_HOST', '');
    if ($host === '') {
        $host = (string)env_value('MAIL_HOST', '');
    }

    return trim($host);
}

function app_smtp_port(): int
{
    $portValue = (string)env_value('SMTP_PORT', '');
    if ($portValue === '') {
        $portValue = (string)env_value('MAIL_PORT', '587');
    }

    $port = (int)$portValue;
    return $port > 0 ? $port : 587;
}

function app_smtp_secure(): string
{
    $secure = (string)env_value('SMTP_SECURE', '');
    if ($secure === '') {
        $secure = (string)env_value('MAIL_ENCRYPTION', '');
    }
    if ($secure === '') {
        $secure = (string)env_value('MAIL_SCHEME', 'tls');
    }

    $secure = strtolower(trim($secure));
    if ($secure === 'null') {
        $secure = 'none';
    }

    return in_array($secure, ['none', 'tls', 'ssl'], true) ? $secure : 'tls';
}

function app_smtp_auth_enabled(): bool
{
    $auth = env_value('SMTP_AUTH');
    if ($auth !== null) {
        return $auth !== '0';
    }

    $username = app_smtp_username();
    return $username !== '';
}

function app_smtp_username(): string
{
    $username = (string)env_value('SMTP_USERNAME', '');
    if ($username === '') {
        $username = (string)env_value('MAIL_USERNAME', '');
    }

    return trim($username);
}

function app_smtp_password(): string
{
    $password = (string)env_value('SMTP_PASSWORD', '');
    if ($password === '') {
        $password = (string)env_value('MAIL_PASSWORD', '');
    }

    return $password;
}

function app_smtp_timeout(): int
{
    $timeout = (int)env_value('SMTP_TIMEOUT', '15');
    return $timeout > 0 ? $timeout : 15;
}

function app_smtp_verify_peer(): bool
{
    return env_value('SMTP_VERIFY_PEER', '1') !== '0';
}

function app_smtp_local_host(): string
{
    $configured = trim((string)env_value('SMTP_HELO', ''));
    if ($configured !== '') {
        return $configured;
    }

    $publicBaseUrl = trim((string)env_value('PUBLIC_APP_URL', ''));
    if ($publicBaseUrl !== '') {
        $host = (string)parse_url($publicBaseUrl, PHP_URL_HOST);
        if ($host !== '') {
            return $host;
        }
    }

    $serverName = trim((string)($_SERVER['SERVER_NAME'] ?? ''));
    if ($serverName !== '') {
        return $serverName;
    }

    return 'localhost';
}

function app_mail_html_to_text(string $htmlBody): string
{
    $normalized = str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody);
    return trim(strip_tags($normalized));
}

function app_mail_encode_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function app_mail_message_id_domain(string $fromEmail): string
{
    $parts = explode('@', $fromEmail, 2);
    if (isset($parts[1]) && $parts[1] !== '') {
        return $parts[1];
    }

    return app_smtp_local_host();
}

function app_mail_build_headers(string $fromEmail, string $fromName, string $boundary): array
{
    return [
        'MIME-Version: 1.0',
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . app_mail_message_id_domain($fromEmail) . '>',
        'From: ' . app_mail_encode_header($fromName) . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: Anafinet Mailer',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
}

function app_mail_build_message(string $htmlBody, string $textBody, string $boundary): string
{
    $messageLines = [
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $textBody,
        '',
        '--' . $boundary,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $htmlBody,
        '',
        '--' . $boundary . '--',
        '',
    ];

    return implode("\r\n", $messageLines);
}

function app_mail_smtp_write($socket, string $payload): void
{
    $remaining = $payload;
    while ($remaining !== '') {
        $written = fwrite($socket, $remaining);
        if ($written === false || $written === 0) {
            throw new RuntimeException('No fue posible escribir en la conexion SMTP.');
        }

        $remaining = (string)substr($remaining, $written);
    }
}

function app_mail_smtp_read($socket): array
{
    $response = '';
    $code = 0;

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (preg_match('/^(\d{3})([ -])/', $line, $matches) === 1) {
            $code = (int)$matches[1];
            if ($matches[2] === ' ') {
                break;
            }
        } else {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('El servidor SMTP no devolvio respuesta.');
    }

    return [$code, trim($response)];
}

function app_mail_smtp_expect($socket, array $expectedCodes, ?string $command = null): string
{
    if ($command !== null) {
        app_mail_smtp_write($socket, $command . "\r\n");
    }

    [$code, $response] = app_mail_smtp_read($socket);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP rechazo la operacion (' . $code . '): ' . $response);
    }

    return $response;
}

function app_mail_smtp_normalize_message(string $message): string
{
    $message = str_replace(["\r\n", "\r"], "\n", $message);
    $message = preg_replace('/^\./m', '..', $message);
    return str_replace("\n", "\r\n", $message);
}

function app_send_smtp_email(string $to, string $subject, array $headers, string $message, string $fromEmail): bool
{
    if (!function_exists('stream_socket_client')) {
        error_log('Email not sent via SMTP: stream_socket_client() no esta disponible.');
        return false;
    }

    $host = app_smtp_host();
    if ($host === '') {
        error_log('Email not sent via SMTP: SMTP_HOST no esta configurado.');
        return false;
    }

    $secure = app_smtp_secure();
    $port = app_smtp_port();
    $timeout = app_smtp_timeout();
    $verifyPeer = app_smtp_verify_peer();
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => $verifyPeer,
            'verify_peer_name' => $verifyPeer,
            'allow_self_signed' => !$verifyPeer,
        ],
    ]);

    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($socket)) {
        error_log('Email not sent via SMTP: no fue posible conectar a ' . $remote . ' (' . $errno . ') ' . $errstr);
        return false;
    }

    stream_set_timeout($socket, $timeout);

    try {
        app_mail_smtp_expect($socket, [220]);
        app_mail_smtp_expect($socket, [250], 'EHLO ' . app_smtp_local_host());

        if ($secure === 'tls') {
            app_mail_smtp_expect($socket, [220], 'STARTTLS');
            $cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                throw new RuntimeException('No fue posible habilitar TLS para la conexion SMTP.');
            }

            app_mail_smtp_expect($socket, [250], 'EHLO ' . app_smtp_local_host());
        }

        if (app_smtp_auth_enabled()) {
            $username = app_smtp_username();
            $password = app_smtp_password();

            if ($username === '' || $password === '') {
                throw new RuntimeException('SMTP_AUTH esta habilitado pero faltan SMTP_USERNAME o SMTP_PASSWORD.');
            }

            app_mail_smtp_expect($socket, [334], 'AUTH LOGIN');
            app_mail_smtp_expect($socket, [334], base64_encode($username));
            app_mail_smtp_expect($socket, [235], base64_encode($password));
        }

        app_mail_smtp_expect($socket, [250], 'MAIL FROM:<' . $fromEmail . '>');
        app_mail_smtp_expect($socket, [250, 251], 'RCPT TO:<' . $to . '>');
        app_mail_smtp_expect($socket, [354], 'DATA');

        $smtpData = implode("\r\n", array_merge(
            ['To: ' . $to, 'Subject: ' . app_mail_encode_header($subject)],
            $headers
        )) . "\r\n\r\n" . app_mail_smtp_normalize_message($message) . "\r\n.\r\n";

        app_mail_smtp_write($socket, $smtpData);
        app_mail_smtp_expect($socket, [250]);
        app_mail_smtp_expect($socket, [221], 'QUIT');

        return true;
    } catch (Throwable $e) {
        error_log('Email not sent via SMTP: ' . $e->getMessage());
        return false;
    } finally {
        fclose($socket);
    }
}

function app_mail_html_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_mail_brand_logo_url(): string
{
    $baseUrl = trim((string)env_value('PUBLIC_APP_URL', ''));
    if ($baseUrl === '') {
        return '';
    }

    return rtrim($baseUrl, '/') . '/logo_anafinet.png';
}

function app_mail_payment_summary_rows(array $rows): string
{
    if ($rows === []) {
        return '';
    }

    $html = '';
    foreach ($rows as $label => $value) {
        $label = trim((string)$label);
        $value = trim((string)$value);
        if ($label === '' || $value === '') {
            continue;
        }

        $html .= '<tr>'
            . '<td style="width:34%;padding:10px 12px;border:1px solid #DCE8F8;color:#4A78B3;font-size:13px;font-weight:700;background:#F8FBFF;">' . app_mail_html_escape($label) . ':</td>'
            . '<td style="padding:10px 12px;border:1px solid #DCE8F8;color:#1E293B;font-size:14px;font-weight:600;background:#FFFFFF;">' . app_mail_html_escape($value) . '</td>'
            . '</tr>';
    }

    if ($html === '') {
        return '';
    }

    return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border-spacing:0;">' . $html . '</table>';
}

function app_mail_button(?string $url, string $label): string
{
    $url = trim((string)$url);
    $label = trim($label);
    if ($url === '' || $label === '') {
        return '';
    }

    $escapedUrl = app_mail_html_escape($url);

    return '<table role="presentation" cellspacing="0" cellpadding="0" align="center" style="margin:28px auto 0 auto;">'
        . '<tr>'
        . '<td align="center" bgcolor="#2563EB" style="border-radius:10px;box-shadow:0 10px 24px rgba(37,99,235,0.22);">'
        . '<a href="' . $escapedUrl . '" style="display:inline-block;min-width:220px;padding:14px 28px;font-size:15px;font-weight:700;line-height:1;color:#FFFFFF;text-decoration:none;">' . app_mail_html_escape($label) . '</a>'
        . '</td>'
        . '</tr>'
        . '</table>';
}

function app_mail_wrap_layout(
    string $eyebrow,
    string $title,
    string $introHtml,
    string $summaryHtml = '',
    string $buttonHtml = '',
    string $footerHtml = '',
    string $preheader = ''
): string {
    $eyebrow = trim($eyebrow);
    $title = trim($title);
    $preheader = trim($preheader);
    $logoUrl = app_mail_brand_logo_url();
    $logoBlock = $logoUrl !== ''
        ? '<div style="text-align:center;margin:0 0 18px 0;"><img src="' . app_mail_html_escape($logoUrl) . '" alt="Anafinet" style="display:block;margin:0 auto;width:180px;max-width:100%;height:auto;border:0;"></div>'
        : '<div style="text-align:center;margin:0 0 18px 0;color:#1E3A8A;font-size:36px;font-weight:800;letter-spacing:0.02em;">Anafinet</div>';
    $summaryBlock = $summaryHtml !== ''
        ? '<div style="margin:26px auto 0 auto;max-width:420px;padding:0;">'
            . $summaryHtml
            . '</div>'
        : '';
    $buttonBlock = $buttonHtml !== ''
        ? '<div style="margin:0;text-align:center;">' . $buttonHtml . '</div>'
        : '';
    $footerBlock = $footerHtml !== ''
        ? '<div style="margin:18px auto 0 auto;max-width:420px;color:#5B6472;font-size:13px;line-height:1.7;text-align:center;">' . $footerHtml . '</div>'
        : '';

    return '<!doctype html>'
        . '<html lang="es">'
        . '<head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . app_mail_html_escape($title) . '</title>'
        . '</head>'
        . '<body style="margin:0;padding:0;background:#F5F7FB;font-family:Arial,Helvetica,sans-serif;color:#0F172A;">'
        . ($preheader !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . app_mail_html_escape($preheader) . '</div>' : '')
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F5F7FB;border-collapse:collapse;">'
        . '<tr>'
        . '<td align="center" style="padding:30px 16px 36px 16px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;border-collapse:collapse;">'
        . '<tr>'
        . '<td style="background:#FFFFFF;border-radius:24px;padding:36px 28px 30px 28px;box-shadow:0 12px 40px rgba(15,23,42,0.08);">'
        . $logoBlock
        . ($eyebrow !== '' ? '<p style="margin:0 0 10px 0;color:#4A78B3;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;text-align:center;">' . app_mail_html_escape($eyebrow) . '</p>' : '')
        . '<h1 style="margin:0;text-align:center;color:#0F172A;font-size:22px;line-height:1.25;font-weight:800;">' . app_mail_html_escape($title) . '</h1>'
        . '<div style="margin:16px auto 0 auto;max-width:420px;color:#334155;font-size:14px;line-height:1.7;text-align:left;">' . $introHtml . '</div>'
        . $summaryBlock
        . $buttonBlock
        . $footerBlock
        . '<div style="margin:28px auto 0 auto;max-width:520px;height:16px;border-radius:0 0 16px 16px;background:linear-gradient(180deg,#F6FBFF 0%,#DDEAF9 100%);"></div>'
        . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="padding:14px 8px 0 8px;color:#7A8699;font-size:11px;line-height:1.6;text-align:center;">Este correo fue generado automaticamente por Anafinet.</td>'
        . '</tr>'
        . '</table>'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '</body>'
        . '</html>';
}

function app_send_html_email(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromEmail = app_mail_from_email();
    $fromName = app_mail_from_name();
    $boundary = 'anafinet_' . bin2hex(random_bytes(12));
    $textBody = $textBody ?? app_mail_html_to_text($htmlBody);
    $headers = app_mail_build_headers($fromEmail, $fromName, $boundary);
    $message = app_mail_build_message($htmlBody, $textBody, $boundary);

    if (app_mail_transport() === 'smtp') {
        return app_send_smtp_email($to, $subject, $headers, $message, $fromEmail);
    }

    if (!function_exists('mail')) {
        error_log('Email not sent: mail() no esta disponible.');
        return false;
    }

    $encodedSubject = app_mail_encode_header($subject);
    $sent = @mail($to, $encodedSubject, $message, implode("\r\n", $headers));

    if (!$sent) {
        error_log('Email not sent to ' . $to . ' with subject ' . $subject);
    }

    return $sent;
}
