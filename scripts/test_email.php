<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo puede ejecutarse desde CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

$recipient = trim((string)($argv[1] ?? ''));
if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php scripts/test_email.php destino@dominio.com\n");
    exit(1);
}

$transport = app_mail_transport();
$fromEmail = app_mail_from_email();
$fromName = app_mail_from_name();
$publicAppUrl = app_public_base_url();

$maskValue = static function (string $value): string {
    if ($value === '') {
        return '(vacio)';
    }

    if (strlen($value) <= 4) {
        return str_repeat('*', strlen($value));
    }

    return substr($value, 0, 2) . str_repeat('*', max(strlen($value) - 4, 1)) . substr($value, -2);
};

$lines = [
    'Diagnostico de correo Anafinet',
    'Destino: ' . $recipient,
    'Transporte: ' . $transport,
    'Remitente: ' . $fromName . ' <' . $fromEmail . '>',
    'PUBLIC_APP_URL: ' . ($publicAppUrl !== '' ? $publicAppUrl : '(vacio)'),
];

if ($transport === 'smtp') {
    $lines[] = 'SMTP_HOST: ' . (app_smtp_host() !== '' ? app_smtp_host() : '(vacio)');
    $lines[] = 'SMTP_PORT: ' . app_smtp_port();
    $lines[] = 'SMTP_SECURE: ' . app_smtp_secure();
    $lines[] = 'SMTP_AUTH: ' . (app_smtp_auth_enabled() ? '1' : '0');
    $lines[] = 'SMTP_USERNAME: ' . $maskValue(app_smtp_username());
    $lines[] = 'SMTP_VERIFY_PEER: ' . (app_smtp_verify_peer() ? '1' : '0');
}

fwrite(STDOUT, implode(PHP_EOL, $lines) . PHP_EOL . PHP_EOL);

$subject = 'Prueba de correo Anafinet';
$sentAt = date('Y-m-d H:i:s');
$introHtml = '<p style="margin:0 0 16px 0;">Esta es una prueba manual del sistema de correo de Anafinet.</p>'
    . '<p style="margin:0;">Si recibes este mensaje, el transporte configurado en el entorno actual esta funcionando.</p>';
$summaryHtml = app_mail_payment_summary_rows([
    'Entorno' => env_value('APP_ENV', 'production'),
    'Transporte' => strtoupper($transport),
    'Fecha de envio' => $sentAt,
    'Remitente' => $fromEmail,
]);
$html = app_mail_wrap_layout(
    'Prueba tecnica',
    'Correo de prueba enviado correctamente',
    $introHtml,
    $summaryHtml,
    '',
    '',
    'Prueba tecnica del sistema de correo Anafinet.'
);
$text = "Esta es una prueba manual del sistema de correo de Anafinet.\n"
    . "Si recibes este mensaje, el transporte configurado en el entorno actual esta funcionando.\n"
    . "Entorno: " . env_value('APP_ENV', 'production') . "\n"
    . "Transporte: " . strtoupper($transport) . "\n"
    . "Fecha de envio: {$sentAt}\n"
    . "Remitente: {$fromEmail}\n";

$sent = app_send_html_email($recipient, $subject, $html, $text);

if (!$sent) {
    fwrite(STDERR, "Resultado: fallo el envio. Revisa la configuracion de correo y el error_log del servidor.\n");
    exit(2);
}

fwrite(STDOUT, "Resultado: correo enviado correctamente.\n");
exit(0);
