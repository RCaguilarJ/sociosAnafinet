<?php
require 'db.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
} else {
    require 'libs/dompdf/autoload.inc.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!isset($_SESSION['user_id'])) {
    exit;
}

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$logoPath = __DIR__ . '/logo_anafinet.png';
if (!file_exists($logoPath)) {
    $logoPath = __DIR__ . '/logo.avif';
}
$base64 = '';
if (file_exists($logoPath)) {
    $type = pathinfo($logoPath, PATHINFO_EXTENSION);
    $data = file_get_contents($logoPath);
    if ($data !== false) {
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
}

$nombreRaw = (string)($user['nombre'] ?? '');
if (function_exists('mb_strtoupper')) {
    $nombreRaw = mb_strtoupper($nombreRaw, 'UTF-8');
} else {
    $nombreRaw = strtoupper($nombreRaw);
}
$nombre = htmlspecialchars($nombreRaw, ENT_QUOTES, 'UTF-8');
$userId = htmlspecialchars((string)($user['id'] ?? ''));
$fecha = date("d/m/Y");

$html = "
<style>
    body { font-family: 'Helvetica', sans-serif; text-align: center; }
    .container { border: 15px solid #5282B2; padding: 40px; position: relative; }
    .logo { width: 180px; margin-bottom: 30px; }
    .title { color: #5282B2; font-size: 45px; margin: 0; }
    .subtitle { font-size: 18px; color: #666; letter-spacing: 2px; }
    .name { font-size: 35px; font-weight: bold; color: #333; margin: 40px 0; text-decoration: underline; }
    .footer { margin-top: 60px; font-size: 12px; color: #999; }
</style>
<div class='container'>
    " . ($base64 ? "<img src='{$base64}' class='logo'>" : '') . "
    <p class='subtitle'>LA ASOCIACI&Oacute;N NACIONAL DE FISCALISTAS DE INTERNET</p>
    <p>Otorga el presente</p>
    <h1 class='title'>CERTIFICADO DE ASOCIADO</h1>
    <p>A favor de:</p>
    <div class='name'>{$nombre}</div>
    <p>Por su valiosa participaci&oacute;n y cumplimiento de los requisitos de membres&iacute;a.</p>
    <div class='footer'>
        ID de Asociado: AN-{$userId} | Fecha de emisi&oacute;n: {$fecha}
    </div>
</div>";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Certificado_Anafinet_" . ($user['id'] ?? 'usuario') . ".pdf", ["Attachment" => true]);
