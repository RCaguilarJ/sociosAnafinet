<?php

function app_payment_admin_email(): string
{
    return (string)env_value('PAYMENT_ADMIN_EMAIL', 'tesoreria@anafinet.mx');
}

function app_mail_from_email(): string
{
    $configured = (string)env_value('MAIL_FROM_EMAIL', '');
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
    return (string)env_value('MAIL_FROM_NAME', 'Anafinet');
}

function app_send_html_email(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (!function_exists('mail')) {
        error_log('Email not sent: mail() no esta disponible.');
        return false;
    }

    $fromEmail = app_mail_from_email();
    $fromName = app_mail_from_name();
    $boundary = 'anafinet_' . bin2hex(random_bytes(12));
    $textBody = $textBody ?? trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . sprintf('"%s" <%s>', addcslashes($fromName, '"\\'), $fromEmail),
        'Reply-To: ' . $fromEmail,
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

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

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $message = implode("\r\n", $messageLines);
    $sent = @mail($to, $encodedSubject, $message, implode("\r\n", $headers));

    if (!$sent) {
        error_log('Email not sent to ' . $to . ' with subject ' . $subject);
    }

    return $sent;
}
