<?php

if (!function_exists('app_mail_set_last_error')) {
    function app_mail_set_last_error(string $message): void
    {
        $GLOBALS['app_mail_last_error'] = trim($message);
    }
}

if (!function_exists('app_mail_last_error')) {
    function app_mail_last_error(): string
    {
        return (string)($GLOBALS['app_mail_last_error'] ?? '');
    }
}

if (!function_exists('app_mail_html_escape')) {
    function app_mail_html_escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('app_mail_encode_header')) {
    function app_mail_encode_header(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }

        return $value;
    }
}

if (!function_exists('app_mail_transport')) {
    function app_mail_transport(): string
    {
        $transport = strtolower(trim((string)env_value('MAIL_TRANSPORT', env_value('MAIL_MAILER', 'mail'))));
        if ($transport === 'smtp') {
            return 'smtp';
        }

        return 'mail';
    }
}

if (!function_exists('app_mail_from_email')) {
    function app_mail_from_email(): string
    {
        $candidates = [
            env_value('MAIL_FROM_EMAIL'),
            env_value('MAIL_FROM_ADDRESS'),
            env_value('PAYMENT_MAIL_FROM_EMAIL'),
            env_value('SMTP_USERNAME'),
            env_value('MAIL_USERNAME'),
        ];

        foreach ($candidates as $candidate) {
            $email = trim((string)$candidate);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';
        return 'noreply@' . $host;
    }
}

if (!function_exists('app_mail_from_name')) {
    function app_mail_from_name(): string
    {
        $candidates = [
            env_value('MAIL_FROM_NAME'),
            env_value('PAYMENT_MAIL_FROM_NAME'),
        ];

        foreach ($candidates as $candidate) {
            $name = trim((string)$candidate);
            if ($name !== '') {
                return $name;
            }
        }

        return 'Anafinet';
    }
}

if (!function_exists('app_payment_mail_from_email')) {
    function app_payment_mail_from_email(): string
    {
        $email = trim((string)env_value('PAYMENT_MAIL_FROM_EMAIL', ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return app_mail_from_email();
    }
}

if (!function_exists('app_payment_mail_from_name')) {
    function app_payment_mail_from_name(): string
    {
        $name = trim((string)env_value('PAYMENT_MAIL_FROM_NAME', ''));
        if ($name !== '') {
            return $name;
        }

        return app_mail_from_name();
    }
}

if (!function_exists('app_payment_admin_email')) {
    function app_payment_admin_email(): string
    {
        $email = trim((string)env_value('PAYMENT_ADMIN_EMAIL', ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return 'tesoreria@anafinet.mx';
    }
}

if (!function_exists('app_smtp_host')) {
    function app_smtp_host(): string
    {
        return trim((string)env_value('SMTP_HOST', env_value('MAIL_HOST', '')));
    }
}

if (!function_exists('app_smtp_port')) {
    function app_smtp_port(): int
    {
        $defaultPort = app_smtp_secure() === 'ssl' ? 465 : 587;
        return max(1, (int)env_value('SMTP_PORT', env_value('MAIL_PORT', (string)$defaultPort)));
    }
}

if (!function_exists('app_smtp_secure')) {
    function app_smtp_secure(): string
    {
        $secure = strtolower(trim((string)env_value('SMTP_SECURE', env_value('MAIL_ENCRYPTION', env_value('MAIL_SCHEME', '')))));
        if ($secure === 'null' || $secure === 'none') {
            return '';
        }

        if ($secure === 'tls' || $secure === 'ssl') {
            return $secure;
        }

        return '';
    }
}

if (!function_exists('app_smtp_auth_enabled')) {
    function app_smtp_auth_enabled(): bool
    {
        $value = env_value('SMTP_AUTH');
        if ($value !== null) {
            return $value === '1';
        }

        return app_smtp_username() !== '' || app_smtp_password() !== '';
    }
}

if (!function_exists('app_smtp_username')) {
    function app_smtp_username(): string
    {
        return trim((string)env_value('SMTP_USERNAME', env_value('MAIL_USERNAME', '')));
    }
}

if (!function_exists('app_smtp_password')) {
    function app_smtp_password(): string
    {
        return (string)env_value('SMTP_PASSWORD', env_value('MAIL_PASSWORD', ''));
    }
}

if (!function_exists('app_smtp_timeout')) {
    function app_smtp_timeout(): int
    {
        return max(5, (int)env_value('SMTP_TIMEOUT', '15'));
    }
}

if (!function_exists('app_smtp_verify_peer')) {
    function app_smtp_verify_peer(): bool
    {
        return env_value('SMTP_VERIFY_PEER', '1') === '1';
    }
}

if (!function_exists('app_mail_button')) {
    function app_mail_button(string $url, string $label): string
    {
        if (trim($url) === '') {
            return '';
        }

        return '<div style="margin:24px 0;text-align:center;">'
            . '<a href="' . app_mail_html_escape($url) . '" style="display:inline-block;padding:14px 24px;border-radius:12px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;">'
            . app_mail_html_escape($label)
            . '</a>'
            . '</div>';
    }
}

if (!function_exists('app_mail_payment_summary_rows')) {
    function app_mail_payment_summary_rows(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $rows = '';
        foreach ($items as $label => $value) {
            $rows .= '<tr>'
                . '<td style="padding:10px 12px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;width:38%;">' . app_mail_html_escape((string)$label) . '</td>'
                . '<td style="padding:10px 12px;border:1px solid #e5e7eb;">' . app_mail_html_escape((string)$value) . '</td>'
                . '</tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;border-spacing:0;">' . $rows . '</table>';
    }
}

if (!function_exists('app_mail_wrap_layout')) {
    function app_mail_wrap_layout(
        string $title,
        string $heading,
        string $body,
        string $summary = '',
        string $button = '',
        string $footer = '',
        string $preheader = ''
    ): string {
        $safeTitle = app_mail_html_escape($title);
        $safeHeading = app_mail_html_escape($heading);
        $safePreheader = app_mail_html_escape($preheader);

        return '<!DOCTYPE html>'
            . '<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $safeTitle . '</title></head>'
            . '<body style="margin:0;padding:0;background:#eef2ff;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">'
            . ($safePreheader !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $safePreheader . '</div>' : '')
            . '<div style="padding:32px 16px;">'
            . '<div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:28px;padding:32px;border:1px solid #dbeafe;box-shadow:0 20px 45px rgba(15,23,42,0.08);">'
            . '<div style="margin-bottom:24px;">'
            . '<p style="margin:0 0 10px 0;font-size:12px;letter-spacing:0.16em;text-transform:uppercase;font-weight:700;color:#2563eb;">Anafinet</p>'
            . '<h1 style="margin:0;font-size:28px;line-height:1.2;color:#0f172a;">' . $safeHeading . '</h1>'
            . '</div>'
            . '<div style="font-size:16px;line-height:1.7;color:#334155;">' . $body . '</div>'
            . ($summary !== '' ? '<div style="margin-top:24px;">' . $summary . '</div>' : '')
            . ($button !== '' ? $button : '')
            . ($footer !== '' ? '<div style="margin-top:24px;font-size:14px;line-height:1.6;color:#64748b;">' . $footer . '</div>' : '')
            . '</div>'
            . '</div>'
            . '</body></html>';
    }
}

if (!function_exists('app_mail_normalize_plain_text')) {
    function app_mail_normalize_plain_text(string $html): string
    {
        $text = str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], ["\n", "\n", "\n", "\n\n", "\n", "\n"], $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('app_mail_compose_message')) {
    function app_mail_compose_message(
        string $subject,
        string $html,
        string $plainText,
        string $fromEmail,
        string $fromName,
        string $replyTo,
        array $extraHeaders = []
    ): array {
        $boundary = 'anafinet_' . bin2hex(random_bytes(12));
        $encodedSubject = app_mail_encode_header($subject);
        $encodedFromName = $fromName !== '' ? app_mail_encode_header($fromName) : '';

        $headers = [
            'MIME-Version: 1.0',
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . uniqid('mail_', true) . '@' . preg_replace('/^.*@/', '', $fromEmail) . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            $encodedFromName !== '' ? 'From: ' . $encodedFromName . ' <' . $fromEmail . '>' : 'From: ' . $fromEmail,
        ];

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        foreach ($extraHeaders as $header) {
            $header = trim((string)$header);
            if ($header !== '') {
                $headers[] = $header;
            }
        }

        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $plainText . "\r\n\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n\r\n"
            . '--' . $boundary . "--\r\n";

        return [
            'subject' => $encodedSubject,
            'headers' => $headers,
            'body' => $body,
        ];
    }
}

if (!function_exists('app_mail_log_failure')) {
    function app_mail_log_failure(string $message): void
    {
        app_mail_set_last_error($message);
        error_log('Mail error: ' . $message);
    }
}

if (!function_exists('app_send_native_mail')) {
    function app_send_native_mail(
        string $to,
        string $subject,
        string $html,
        string $plainText,
        string $fromEmail,
        string $fromName,
        string $replyTo
    ): bool {
        $message = app_mail_compose_message($subject, $html, $plainText, $fromEmail, $fromName, $replyTo, [
            'X-Mailer: PHP/' . phpversion(),
        ]);

        $sent = @mail($to, $message['subject'], $message['body'], implode("\r\n", $message['headers']));
        if ($sent) {
            app_mail_set_last_error('');
            return true;
        }

        $lastPhpError = error_get_last();
        $details = is_array($lastPhpError) ? trim((string)($lastPhpError['message'] ?? '')) : '';
        app_mail_log_failure($details !== '' ? $details : 'mail() devolvio false al intentar enviar el correo.');
        return false;
    }
}

if (!function_exists('app_smtp_read_response')) {
    /**
     * @param resource $socket
     */
    function app_smtp_read_response($socket): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }
}

if (!function_exists('app_smtp_expect_code')) {
    function app_smtp_expect_code(string $response, array $expectedCodes, string $context): bool
    {
        $code = (int)substr(trim($response), 0, 3);
        if (in_array($code, $expectedCodes, true)) {
            return true;
        }

        app_mail_log_failure($context . ': ' . trim($response));
        return false;
    }
}

if (!function_exists('app_smtp_write_command')) {
    /**
     * @param resource $socket
     */
    function app_smtp_write_command($socket, string $command): bool
    {
        return fwrite($socket, $command) !== false;
    }
}

if (!function_exists('app_send_smtp_email')) {
    function app_send_smtp_email(
        string $to,
        string $subject,
        string $html,
        string $plainText,
        string $fromEmail,
        string $fromName,
        string $replyTo
    ): bool {
        $host = app_smtp_host();
        if ($host === '') {
            app_mail_log_failure('MAIL_TRANSPORT=smtp pero SMTP_HOST/MAIL_HOST esta vacio.');
            return false;
        }

        $secure = app_smtp_secure();
        $remoteHost = $secure === 'ssl' ? 'ssl://' . $host : $host;
        $contextOptions = [
            'ssl' => [
                'verify_peer' => app_smtp_verify_peer(),
                'verify_peer_name' => app_smtp_verify_peer(),
                'allow_self_signed' => !app_smtp_verify_peer(),
            ],
        ];

        $socket = @stream_socket_client(
            $remoteHost . ':' . app_smtp_port(),
            $errorNumber,
            $errorString,
            app_smtp_timeout(),
            STREAM_CLIENT_CONNECT,
            stream_context_create($contextOptions)
        );

        if (!is_resource($socket)) {
            app_mail_log_failure('No se pudo conectar al servidor SMTP: ' . $errorString . ' (' . $errorNumber . ')');
            return false;
        }

        stream_set_timeout($socket, app_smtp_timeout());

        $response = app_smtp_read_response($socket);
        if (!app_smtp_expect_code($response, [220], 'El servidor SMTP no devolvio bienvenida valida')) {
            fclose($socket);
            return false;
        }

        $clientHost = parse_url(app_public_base_url(), PHP_URL_HOST);
        if (!is_string($clientHost) || $clientHost === '') {
            $clientHost = 'localhost';
        }

        if (!app_smtp_write_command($socket, 'EHLO ' . $clientHost . "\r\n")) {
            fclose($socket);
            app_mail_log_failure('No se pudo enviar el comando EHLO al servidor SMTP.');
            return false;
        }
        $response = app_smtp_read_response($socket);
        if (!app_smtp_expect_code($response, [250], 'El servidor SMTP rechazo EHLO')) {
            fclose($socket);
            return false;
        }

        if ($secure === 'tls') {
            if (!app_smtp_write_command($socket, "STARTTLS\r\n")) {
                fclose($socket);
                app_mail_log_failure('No se pudo solicitar STARTTLS al servidor SMTP.');
                return false;
            }
            $response = app_smtp_read_response($socket);
            if (!app_smtp_expect_code($response, [220], 'El servidor SMTP rechazo STARTTLS')) {
                fclose($socket);
                return false;
            }

            $cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                fclose($socket);
                app_mail_log_failure('No se pudo habilitar TLS sobre la conexion SMTP.');
                return false;
            }

            if (!app_smtp_write_command($socket, 'EHLO ' . $clientHost . "\r\n")) {
                fclose($socket);
                app_mail_log_failure('No se pudo reenviar EHLO despues de STARTTLS.');
                return false;
            }
            $response = app_smtp_read_response($socket);
            if (!app_smtp_expect_code($response, [250], 'El servidor SMTP rechazo EHLO despues de STARTTLS')) {
                fclose($socket);
                return false;
            }
        }

        if (app_smtp_auth_enabled()) {
            if (!app_smtp_write_command($socket, "AUTH LOGIN\r\n")) {
                fclose($socket);
                app_mail_log_failure('No se pudo iniciar autenticacion SMTP.');
                return false;
            }
            $response = app_smtp_read_response($socket);
            if (!app_smtp_expect_code($response, [334], 'El servidor SMTP rechazo AUTH LOGIN')) {
                fclose($socket);
                return false;
            }

            if (!app_smtp_write_command($socket, base64_encode(app_smtp_username()) . "\r\n")) {
                fclose($socket);
                app_mail_log_failure('No se pudo enviar el usuario SMTP.');
                return false;
            }
            $response = app_smtp_read_response($socket);
            if (!app_smtp_expect_code($response, [334], 'El servidor SMTP rechazo el usuario SMTP')) {
                fclose($socket);
                return false;
            }

            if (!app_smtp_write_command($socket, base64_encode(app_smtp_password()) . "\r\n")) {
                fclose($socket);
                app_mail_log_failure('No se pudo enviar la contrasena SMTP.');
                return false;
            }
            $response = app_smtp_read_response($socket);
            if (!app_smtp_expect_code($response, [235], 'El servidor SMTP rechazo la contrasena SMTP')) {
                fclose($socket);
                return false;
            }
        }

        if (!app_smtp_write_command($socket, 'MAIL FROM:<' . $fromEmail . ">\r\n")) {
            fclose($socket);
            app_mail_log_failure('No se pudo enviar MAIL FROM al servidor SMTP.');
            return false;
        }
        $response = app_smtp_read_response($socket);
        if (!app_smtp_expect_code($response, [250], 'El servidor SMTP rechazo MAIL FROM')) {
            fclose($socket);
            return false;
        }

        if (!app_smtp_write_command($socket, 'RCPT TO:<' . $to . ">\r\n")) {
            fclose($socket);
            app_mail_log_failure('No se pudo enviar RCPT TO al servidor SMTP.');
            return false;
        }
        $response = app_smtp_read_response($socket);
        if (!app_smtp_expect_code($response, [250, 251], 'El servidor SMTP rechazo RCPT TO')) {
            fclose($socket);
            return false;
        }

        if (!app_smtp_write_command($socket, "DATA\r\n")) {
            fclose($socket);
            app_mail_log_failure('No se pudo iniciar DATA en el servidor SMTP.');
            return false;
        }
        $response = app_smtp_read_response($socket);
        if (!app_smtp_expect_code($response, [354], 'El servidor SMTP rechazo DATA')) {
            fclose($socket);
            return false;
        }

        $message = app_mail_compose_message($subject, $html, $plainText, $fromEmail, $fromName, $replyTo, [
            'To: ' . $to,
            'Subject: ' . app_mail_encode_header($subject),
        ]);
        $payload = implode("\r\n", $message['headers']) . "\r\n\r\n" . $message['body'];
        $payload = preg_replace('/(?m)^\./', '..', $payload) ?? $payload;

        if (!app_smtp_write_command($socket, $payload . "\r\n.\r\n")) {
            fclose($socket);
            app_mail_log_failure('No se pudo transmitir el contenido del correo al servidor SMTP.');
            return false;
        }
        $response = app_smtp_read_response($socket);
        if (!app_smtp_expect_code($response, [250], 'El servidor SMTP rechazo el contenido del mensaje')) {
            fclose($socket);
            return false;
        }

        app_smtp_write_command($socket, "QUIT\r\n");
        fclose($socket);
        app_mail_set_last_error('');
        return true;
    }
}

if (!function_exists('app_send_html_email')) {
    function app_send_html_email(string $to, string $subject, string $html, ?string $plainText = null, array $options = []): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            app_mail_log_failure('El destinatario del correo no es valido.');
            return false;
        }

        $fromEmail = trim((string)($options['from_email'] ?? app_mail_from_email()));
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            app_mail_log_failure('El remitente del correo no es valido.');
            return false;
        }

        $fromName = trim((string)($options['from_name'] ?? app_mail_from_name()));
        $replyTo = trim((string)($options['reply_to'] ?? ''));
        if ($replyTo === '' || !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = $fromEmail;
        }

        $plainText = $plainText !== null && trim($plainText) !== ''
            ? trim($plainText)
            : app_mail_normalize_plain_text($html);

        if (app_mail_transport() === 'smtp') {
            return app_send_smtp_email($to, $subject, $html, $plainText, $fromEmail, $fromName, $replyTo);
        }

        return app_send_native_mail($to, $subject, $html, $plainText, $fromEmail, $fromName, $replyTo);
    }
}
