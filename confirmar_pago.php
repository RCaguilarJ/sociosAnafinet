<?php
// ============================================================
//  CREDENCIALES — reemplaza estos valores cuando los tengas
// ============================================================
//
//  En tu archivo .env (raíz del proyecto) deben quedar así:
//
//  MERCADOPAGO_ACCESS_TOKEN=APP_USR-XXXXXXXXXXXXXXXX
//  MERCADOPAGO_PUBLIC_KEY=APP_USR-XXXXXXXXXXXXXXXX
//  MERCADOPAGO_WEBHOOK_SECRET=tu_clave_webhook
//  MERCADOPAGO_USE_SANDBOX=0
//
//  PAYPAL_CLIENT_ID=AXXXXXXXXXXXXXXXXXXXXXXXXXXX
//  PAYPAL_CLIENT_SECRET=EXXXXXXXXXXXXXXXXXXXXXXXXX
//  PAYPAL_USE_SANDBOX=0
//
// ============================================================

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/role_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];
$userStatus = (string) ($_SESSION['user_estatus'] ?? '');
$mensaje = '';
$mensajeTipo = 'info';
$registroNuevo = isset($_GET['registro']) && $_GET['registro'] === '1';
$schemaError = '';
$mercadoPagoEnabled = app_mercadopago_enabled();
$paypalEnabled = app_paypal_enabled();
$paypalClientId = app_paypal_client_id();
$latestMercadoPagoPayment = null;
$latestPayPalPayment = null;
$paypalExternalReference = trim((string) ($_SESSION['membership_paypal_external_reference'] ?? ''));

if (isset($_SESSION['payment_flash_message'], $_SESSION['payment_flash_type'])) {
    $mensaje = (string) $_SESSION['payment_flash_message'];
    $mensajeTipo = (string) $_SESSION['payment_flash_type'];
    unset($_SESSION['payment_flash_message'], $_SESSION['payment_flash_type']);
}

$paymentData = [
    'comprobante_pago' => '',
    'referencia_pago' => '',
    'pago_reportado_at' => '',
];

if ($pdo instanceof PDO) {
    try {
        ensure_user_payment_columns($pdo);
        app_ensure_membership_payment_schema($pdo);

        $stmt = $pdo->prepare(
            'SELECT estatus, comprobante_pago, referencia_pago, pago_reportado_at
             FROM usuarios WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (is_array($row)) {
            $userStatus = (string) ($row['estatus'] ?? $userStatus);
            $paymentData['comprobante_pago'] = (string) ($row['comprobante_pago'] ?? '');
            $paymentData['referencia_pago'] = (string) ($row['referencia_pago'] ?? '');
            $paymentData['pago_reportado_at'] = (string) ($row['pago_reportado_at'] ?? '');
            $_SESSION['user_estatus'] = $userStatus;
        }

        $latestMercadoPagoPayment = app_get_latest_membership_payment_for_user($pdo, $userId, 'mercadopago');
        $latestPayPalPayment = app_get_latest_membership_payment_for_user($pdo, $userId, 'paypal');

        if ($paypalEnabled && !app_is_membership_active_status($userStatus)) {
            if ($paypalExternalReference === '') {
                $paypalExternalReference = app_membership_payment_reference($userId);
                $_SESSION['membership_paypal_external_reference'] = $paypalExternalReference;
            }
            if (app_get_membership_payment_by_external_reference($pdo, $paypalExternalReference) === null) {
                app_insert_membership_payment_attempt($pdo, $userId, 'paypal', $paypalExternalReference);
            }
        }
    } catch (Throwable $e) {
        $schemaError = 'No fue posible preparar el modulo de confirmacion de pago en este momento.';
    }
}

// ── Retorno desde Mercado Pago ────────────────────────────────
if ($schemaError === '' && $pdo instanceof PDO && isset($_GET['provider']) && $_GET['provider'] === 'mercadopago') {
    $mercadoPagoPaymentId = trim((string) ($_GET['payment_id'] ?? $_GET['collection_id'] ?? ''));
    $externalReference = trim((string) ($_GET['external_reference'] ?? ''));
    $returnState = trim((string) ($_GET['mp_return'] ?? ''));
    try {
        $syncedPayment = null;
        if ($mercadoPagoPaymentId !== '' && $mercadoPagoEnabled) {
            $syncedPayment = app_sync_mercadopago_payment($pdo, $mercadoPagoPaymentId);
            $latestMercadoPagoPayment = app_get_latest_membership_payment_for_user($pdo, $userId, 'mercadopago');
            $userStatus = (string) ($_SESSION['user_estatus'] ?? $userStatus);
        } elseif ($externalReference !== '') {
            $latestMercadoPagoPayment = app_get_membership_payment_by_external_reference($pdo, $externalReference);
        }
        $paymentStatus = (string) ($syncedPayment['payment_status'] ?? $latestMercadoPagoPayment['status'] ?? '');
        if ($paymentStatus === 'approved') {
            $mensaje = 'Tu pago en Mercado Pago fue aprobado y la membresia ya quedo activa.';
            $mensajeTipo = 'success';
            $userStatus = (string) ($syncedPayment['user_status'] ?? 'Activo');
            $_SESSION['user_estatus'] = $userStatus;
        } elseif ($paymentStatus === 'processing' || $returnState === 'pending') {
            $mensaje = 'Tu pago esta en proceso. Algunas secciones seguiran restringidas mientras se confirma.';
            $mensajeTipo = 'info';
            $userStatus = (string) ($syncedPayment['user_status'] ?? 'Pago en proceso');
            $_SESSION['user_estatus'] = $userStatus;
        } elseif ($returnState === 'failure' || $paymentStatus === 'failed') {
            $mensaje = 'El pago no pudo confirmarse. Puedes intentarlo de nuevo o reportarlo manualmente.';
            $mensajeTipo = 'error';
        }
    } catch (Throwable $e) {
        $mensaje = 'No fue posible sincronizar el estado de tu pago. Intenta recargar en unos minutos.';
        $mensajeTipo = 'error';
    }
}

// ── Retorno desde PayPal ──────────────────────────────────────
if ($schemaError === '' && $pdo instanceof PDO && isset($_GET['provider']) && $_GET['provider'] === 'paypal') {
    $paypalOrderId = trim((string) ($_GET['order_id'] ?? ''));
    $externalReference = trim((string) ($_GET['external_reference'] ?? $paypalExternalReference));
    try {
        if ($paypalOrderId === '' || $externalReference === '' || !$paypalEnabled) {
            throw new RuntimeException('paypal_invalid_return');
        }
        $syncedPayment = app_sync_paypal_order($pdo, $userId, $externalReference, $paypalOrderId);
        $latestPayPalPayment = app_get_latest_membership_payment_for_user($pdo, $userId, 'paypal');
        $userStatus = (string) ($syncedPayment['user_status'] ?? $userStatus);
        $_SESSION['user_estatus'] = $userStatus;
        unset($_SESSION['membership_paypal_external_reference']);
        $paypalExternalReference = '';
        if ($syncedPayment['payment_status'] === 'approved') {
            $mensaje = 'Tu pago en PayPal fue aprobado y la membresia ya quedo activa.';
            $mensajeTipo = 'success';
        } elseif ($syncedPayment['payment_status'] === 'processing') {
            $mensaje = 'Tu pago en PayPal esta en proceso. Algunas secciones seguiran restringidas.';
            $mensajeTipo = 'info';
        } else {
            $mensaje = 'El pago con PayPal no pudo confirmarse. Puedes intentarlo de nuevo.';
            $mensajeTipo = 'error';
        }
    } catch (Throwable $e) {
        $mensaje = 'No fue posible sincronizar el pago de PayPal. Intenta nuevamente en unos minutos.';
        $mensajeTipo = 'error';
    }
}

// ── POST: reporte manual con comprobante ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO && $schemaError === '') {
    $referenciaPago = trim((string) ($_POST['referencia_pago'] ?? ''));
    $archivo = $_FILES['comprobante_pago'] ?? null;
    $archivoActual = $paymentData['comprobante_pago'];
    $nuevoNombre = $archivoActual;
    $seSubioNuevoArchivo = false;
    $permitidos = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024;

    if ($referenciaPago === '') {
        $mensaje = 'Captura una referencia, folio o nota breve para identificar tu pago.';
        $mensajeTipo = 'error';
    } elseif (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($archivoActual === '') {
            $mensaje = 'Adjunta tu comprobante de pago para continuar.';
            $mensajeTipo = 'error';
        }
    } elseif (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $mensaje = 'Ocurrio un error al subir el comprobante.';
        $mensajeTipo = 'error';
    } else {
        $extension = strtolower(pathinfo((string) ($archivo['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $permitidos, true)) {
            $mensaje = 'Solo se permiten archivos PDF, JPG, PNG o WebP.';
            $mensajeTipo = 'error';
        } elseif (($archivo['size'] ?? 0) > $maxSize) {
            $mensaje = 'El comprobante excede el tamano permitido de 5MB.';
            $mensajeTipo = 'error';
        } else {
            $mimeOk = true;
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file((string) $archivo['tmp_name']);
                if (!in_array($mime, $allowedMimes, true)) { $mimeOk = false; }
            }
            if (!$mimeOk) {
                $mensaje = 'El archivo cargado no tiene un formato valido.';
                $mensajeTipo = 'error';
            } else {
                $uploadsDir = app_ensure_storage_directory('comprobantes_pago');
                if (!is_writable($uploadsDir)) {
                    $mensaje = 'La carpeta de comprobantes no tiene permisos de escritura.';
                    $mensajeTipo = 'error';
                } else {
                    $token = bin2hex(random_bytes(4));
                    $nuevoNombre = 'comprobante_' . $userId . '_' . time() . '_' . $token . '.' . $extension;
                    $destino = app_storage_path('comprobantes_pago', $nuevoNombre);
                    if (move_uploaded_file((string) $archivo['tmp_name'], $destino)) {
                        $seSubioNuevoArchivo = true;
                    } else {
                        $mensaje = 'No se pudo guardar el comprobante en el servidor.';
                        $mensajeTipo = 'error';
                    }
                }
            }
        }
    }

    if ($mensaje === '') {
        try {
            $stmt = $pdo->prepare(
                'UPDATE usuarios SET estatus = ?, referencia_pago = ?, comprobante_pago = ?, pago_reportado_at = NOW() WHERE id = ?'
            );
            $stmt->execute(['Pago reportado', $referenciaPago, $nuevoNombre, $userId]);
            if ($archivoActual !== '' && $archivoActual !== $nuevoNombre) {
                $oldPath = app_storage_path('comprobantes_pago', $archivoActual);
                if (is_file($oldPath)) { @unlink($oldPath); }
            }
            $userStatus = 'Pago reportado';
            $_SESSION['user_estatus'] = $userStatus;
            $paymentData['comprobante_pago'] = $nuevoNombre;
            $paymentData['referencia_pago'] = $referenciaPago;
            $paymentData['pago_reportado_at'] = date('Y-m-d H:i:s');
            $mensaje = 'Tu pago fue reportado correctamente. Cuando el equipo lo valide, tu membresia quedara habilitada.';
            $mensajeTipo = 'success';
        } catch (Throwable $e) {
            if ($seSubioNuevoArchivo) {
                $newPath = app_storage_path('comprobantes_pago', $nuevoNombre);
                if (is_file($newPath)) { @unlink($newPath); }
            }
            $mensaje = 'No se pudo registrar la confirmacion de pago en este momento.';
            $mensajeTipo = 'error';
        }
    }
}

if ($pdo instanceof PDO && $schemaError === '') {
    try {
        $latestMercadoPagoPayment = app_get_latest_membership_payment_for_user($pdo, $userId, 'mercadopago');
        $latestPayPalPayment = app_get_latest_membership_payment_for_user($pdo, $userId, 'paypal');
    } catch (Throwable $e) {
        $latestMercadoPagoPayment = null;
        $latestPayPalPayment = null;
    }
}

// ── Variables para la vista ───────────────────────────────────
$comprobanteUrl = $paymentData['comprobante_pago'] !== ''
    ? uploaded_file_url('comprobantes_pago', $paymentData['comprobante_pago'], true) : '';
$membresiaImporte = number_format(app_membership_fee_amount(), 2);
$membresiaMoneda = app_membership_fee_currency();
$mercadoPagoStatus = is_array($latestMercadoPagoPayment) ? (string) ($latestMercadoPagoPayment['status'] ?? '') : '';
$mercadoPagoCheckoutUrl = is_array($latestMercadoPagoPayment) ? (string) ($latestMercadoPagoPayment['checkout_url'] ?? '') : '';
$mercadoPagoStatusNormalized = strtolower($mercadoPagoStatus);
$mercadoPagoStatusLabel = 'Sin intento registrado';
$mercadoPagoStatusClasses = 'bg-slate-100 text-slate-600';
if ($mercadoPagoStatusNormalized === 'approved') {
    $mercadoPagoStatusLabel = 'Pago aprobado';
    $mercadoPagoStatusClasses = 'bg-green-100 text-green-600';
} elseif (in_array($mercadoPagoStatusNormalized, ['pending', 'in_process', 'authorized', 'processing', 'checkout_created'], true)) {
    $mercadoPagoStatusLabel = 'Pago en revision';
    $mercadoPagoStatusClasses = 'bg-blue-100 text-blue-600';
} elseif ($mercadoPagoStatusNormalized !== '') {
    $mercadoPagoStatusLabel = 'Pago no confirmado';
    $mercadoPagoStatusClasses = 'bg-red-100 text-red-600';
}
$payPalStatus = is_array($latestPayPalPayment) ? (string) ($latestPayPalPayment['status'] ?? '') : '';
$payPalStatusNormalized = strtolower($payPalStatus);
$payPalStatusLabel = 'Sin intento registrado';
if ($payPalStatusNormalized === 'approved') { $payPalStatusLabel = 'Pago aprobado'; }
elseif (in_array($payPalStatusNormalized, ['pending', 'processing'], true)) { $payPalStatusLabel = 'Pago en revision'; }
elseif ($payPalStatusNormalized !== '') { $payPalStatusLabel = 'Pago no confirmado'; }
$comprobanteStatusLabel = $paymentData['comprobante_pago'] !== ''
    ? 'Comprobante cargado: ' . basename($paymentData['comprobante_pago'])
    : 'PDF, JPG, PNG o WebP hasta 5MB';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Confirmar Pago - Anafinet</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        /* ── Pay Card ── */
        .pay-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        }

        .pay-card .pay-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .pay-card .pay-header .label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
        }

        .pay-card .pay-header .total {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a2e;
            margin-top: 4px;
        }

        .pay-card .pay-header .total .currency {
            font-size: 16px;
            font-weight: 500;
            color: #64748b;
        }

        .pay-card .pay-header .concepto {
            font-size: 13px;
            color: #64748b;
            margin-top: 2px;
        }

        /* ── Status badge inline ── */
        .pay-header .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        /* ── Tabs ── */
        .method-tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
        }

        .tab {
            flex: 1;
            padding: 13px 12px;
            cursor: pointer;
            border: none;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            border-bottom: 3px solid transparent;
            transition: all 0.18s;
            font-family: inherit;
        }

        .tab:first-child { border-right: 1px solid #e2e8f0; }

        .tab.active {
            background: #fff;
            color: #009EE3;
            border-bottom: 3px solid #009EE3;
        }

        .tab.active.pp { color: #003087; border-bottom-color: #003087; }

        .tab:disabled { opacity: 0.5; cursor: not-allowed; }

        .tab svg { width: 24px; height: 16px; flex-shrink: 0; }

        /* ── Panels ── */
        .panel { display: none; }
        .panel.active { display: block; }

        .panel .form-body { padding: 1.5rem; }

        /* ── Fields ── */
        .field { margin-bottom: 14px; }

        .field label {
            display: block;
            font-size: 11px;
            color: #64748b;
            margin-bottom: 5px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 10px 13px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
            color: #1a1a2e;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            font-family: inherit;
        }

        .field input:focus,
        .field select:focus {
            border-color: #009EE3;
            box-shadow: 0 0 0 3px rgba(0,158,227,0.12);
        }

        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* ── Card icons ── */
        .card-icons {
            display: flex;
            gap: 6px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .card-icon {
            height: 26px;
            padding: 0 8px;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.03em;
        }

        /* ── Buttons ── */
        .btn-pay {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: none;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
            transition: opacity 0.15s, transform 0.1s;
            font-family: inherit;
            letter-spacing: 0.02em;
        }

        .btn-pay:hover { opacity: 0.9; }
        .btn-pay:active { transform: scale(0.98); }
        .btn-pay:disabled { opacity: 0.6; cursor: not-allowed; }

        .btn-mp { background: #009EE3; color: #fff; }
        .btn-pp { background: #003087; color: #fff; }

        /* ── Secure note ── */
        .secure-note {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 12px;
            justify-content: center;
        }

        .secure-note svg { width: 13px; height: 13px; flex-shrink: 0; }

        /* ── MSI badge ── */
        .msi-badge {
            display: inline-block;
            background: #ecfdf5;
            color: #059669;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 6px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            vertical-align: middle;
        }

        /* ── PayPal info panel ── */
        .pp-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }

        .pp-logo { margin-bottom: 10px; }

        .pp-info p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
        }

        /* ── Divider ── */
        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 14px 0;
        }

        .divider span {
            font-size: 11px;
            color: #94a3b8;
            white-space: nowrap;
            font-weight: 500;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        /* ── "Próximamente" badge ── */
        .badge-pronto {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            background: #fef9c3;
            color: #854d0e;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'confirmar_pago';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-6 md:p-8">
        <div class="mx-auto w-full max-w-6xl space-y-6">

            <?php if ($registroNuevo): ?>
            <div class="rounded-3xl border border-blue-200 bg-blue-50 px-6 py-5 text-blue-900">
                <p class="text-sm font-bold uppercase tracking-wide">Registro completado</p>
                <h1 class="mt-2 text-2xl font-bold">Tu solicitud como afiliado esta en proceso.</h1>
                <p class="mt-2 text-sm text-blue-800">Como siguiente paso, confirma tu pago para cerrar el proceso administrativo y habilitar el acceso completo.</p>
            </div>
            <?php endif; ?>

            <?php if ($schemaError !== ''): ?>
            <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900">
                <?php echo htmlspecialchars($schemaError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>

            <?php if ($mensaje !== ''): ?>
            <div class="rounded-2xl px-5 py-4 text-sm
                <?php echo $mensajeTipo === 'success'
                    ? 'border border-emerald-200 bg-emerald-50 text-emerald-900'
                    : ($mensajeTipo === 'error'
                        ? 'border border-red-200 bg-red-50 text-red-900'
                        : 'border border-blue-200 bg-blue-50 text-blue-900'); ?>">
                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_360px]">
                <div class="space-y-6">

                    <!-- ═══════════════════════════════════════════════
                         CARD PRINCIPAL: PAGO EN LÍNEA
                    ═══════════════════════════════════════════════ -->
                    <div class="pay-card">

                        <!-- Header total + status -->
                        <div class="pay-header">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="label">Total a pagar</div>
                                    <div class="total">$<?php echo $membresiaImporte; ?> <span class="currency"><?php echo htmlspecialchars($membresiaMoneda, ENT_QUOTES, 'UTF-8'); ?></span></div>
                                    <div class="concepto"><?php echo htmlspecialchars(app_membership_fee_label(), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <span class="status-badge <?php echo $mercadoPagoStatusClasses; ?>">
                                    <?php echo htmlspecialchars($mercadoPagoStatusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Tabs de método de pago -->
                        <div class="method-tabs">
                            <button class="tab active" id="tab-mp" onclick="switchTab('mp')">
                                <svg viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="50" height="32" rx="5" fill="#009EE3"/>
                                    <path d="M8 22l4-10 3 5.5 2.5-3.5 4 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                    <circle cx="34" cy="16" r="5" fill="white"/>
                                    <path d="M42 11v10" stroke="white" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                Mercado Pago
                            </button>
                            <button class="tab pp" id="tab-pp" onclick="switchTab('pp')"
                                <?php echo !($paypalEnabled && $paypalClientId !== '') ? 'disabled' : ''; ?>>
                                <svg viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="50" height="32" rx="5" fill="#003087"/>
                                    <path d="M14 7h8c4 0 6.5 2 6 5.5C27.5 17 25 19 21.5 19h-3.5l-1.5 6H12L14 7z" fill="#009CDE"/>
                                    <path d="M22 9h7c3.5 0 5.5 1.5 5 5C33.5 18 31 20 27.5 20H25l-1.5 5h-4l2.5-16z" fill="white" opacity="0.4"/>
                                </svg>
                                PayPal
                                <?php if (!($paypalEnabled && $paypalClientId !== '')): ?>
                                <span class="badge-pronto" style="font-size:8px;padding:2px 6px;">Próximamente</span>
                                <?php endif; ?>
                            </button>
                        </div>

                        <!-- Panel Mercado Pago -->
                        <div id="panel-mp" class="panel active">
                            <div class="form-body">

                                <div class="card-icons">
                                    <div class="card-icon" style="background:#1A1F71;color:white;">VISA</div>
                                    <div class="card-icon" style="background:#EB001B;color:white;">MC</div>
                                    <div class="card-icon" style="background:#2E77BC;color:white;">AMEX</div>
                                    <div class="card-icon" style="background:#FF5F00;color:white;">OXXO</div>
                                    <div class="card-icon" style="background:#722F8A;color:white;">SPEI</div>
                                </div>

                                <div class="field">
                                    <label>Número de tarjeta</label>
                                    <input type="text" id="cardnum" placeholder="0000 0000 0000 0000" maxlength="19"
                                           oninput="formatCard(this)" autocomplete="cc-number">
                                </div>

                                <div class="field">
                                    <label>Nombre en la tarjeta</label>
                                    <input type="text" placeholder="Como aparece en la tarjeta" autocomplete="cc-name">
                                </div>

                                <div class="row2">
                                    <div class="field">
                                        <label>Vencimiento</label>
                                        <input type="text" placeholder="MM / AA" maxlength="7"
                                               oninput="formatExpiry(this)" autocomplete="cc-exp">
                                    </div>
                                    <div class="field">
                                        <label>CVV</label>
                                        <input type="text" placeholder="•••" maxlength="4" autocomplete="cc-csc">
                                    </div>
                                </div>

                                <div class="field">
                                    <label>
                                        Meses sin intereses
                                        <span class="msi-badge">disponible</span>
                                    </label>
                                    <select>
                                        <option value="1">Pago único</option>
                                        <option value="3">3 meses sin intereses</option>
                                        <option value="6">6 meses sin intereses</option>
                                        <option value="12">12 meses sin intereses</option>
                                    </select>
                                </div>

                                <?php if ($mercadoPagoEnabled): ?>
                                <form action="<?php echo BASE_URL; ?>/mercadopago_create_payment.php" method="POST">
                                    <button type="submit" class="btn-pay btn-mp">
                                        Pagar con Mercado Pago
                                    </button>
                                </form>
                                <?php if ($mercadoPagoCheckoutUrl !== '' && $mercadoPagoStatusNormalized !== 'approved'): ?>
                                <a href="<?php echo htmlspecialchars($mercadoPagoCheckoutUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                   class="mt-3 inline-flex items-center justify-center gap-2 w-full text-sm font-semibold text-blue-700 hover:text-blue-900">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Reanudar ultimo checkout
                                </a>
                                <?php endif; ?>
                                <?php else: ?>
                                <button type="button" class="btn-pay btn-mp" disabled>
                                    Pagar con Mercado Pago
                                </button>
                                <?php endif; ?>

                                <div class="secure-note">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                    Pago 100% seguro · Cifrado SSL · Protegido por Mercado Pago
                                </div>
                            </div>
                        </div>

                        <!-- Panel PayPal -->
                        <div id="panel-pp" class="panel">
                            <div class="form-body">

                                <div class="pp-info">
                                    <div class="pp-logo">
                                        <svg width="100" height="26" viewBox="0 0 100 26" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 2h10c5 0 8 2.5 7 7C28.5 14 25 17 21 17h-3.5l-1.5 7H11L12 2z" fill="#009CDE"/>
                                            <path d="M17 4h8c4 0 6 2 5.5 5.5C30 14 27 16.5 23.5 16.5H21l-1.5 4.5h-4l1.5-17z" fill="#003087" opacity="0.6"/>
                                            <text x="38" y="19" font-size="15" font-weight="800" fill="#003087" font-family="Arial, sans-serif">Pay</text>
                                            <text x="64" y="19" font-size="15" font-weight="800" fill="#009CDE" font-family="Arial, sans-serif">Pal</text>
                                        </svg>
                                    </div>
                                    <p>Serás redirigido a PayPal para completar tu pago de forma segura. No necesitas ingresar datos de tarjeta en este sitio.</p>
                                </div>

                                <?php if ($paypalEnabled && $paypalClientId !== ''): ?>
                                <div id="paypal-member-button-container"></div>
                                <?php else: ?>
                                <div class="divider">
                                    <span>o ingresa tu correo de PayPal</span>
                                </div>
                                <div class="field">
                                    <label>Correo de tu cuenta PayPal</label>
                                    <input type="email" placeholder="correo@ejemplo.com" autocomplete="email">
                                </div>
                                <button type="button" class="btn-pay btn-pp" disabled>
                                    Continuar con PayPal
                                </button>
                                <?php endif; ?>

                                <div class="secure-note">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                    Protegido por PayPal · Garantía de compra del comprador
                                </div>
                            </div>
                        </div>

                    </div><!-- /.pay-card -->

                    <!-- ═══════════════════════════════════════════════
                         CARD: TRANSFERENCIA MANUAL
                    ═══════════════════════════════════════════════ -->
                    <div class="rounded-[2rem] border border-slate-200 bg-[#EFF6FB] p-6 shadow-sm md:p-8">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#5282B2] shadow-sm">
                                <i class="fa-solid fa-building-columns text-lg"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.18em] text-slate-400">Respaldo manual</p>
                                <h2 class="text-2xl font-bold text-slate-900">Transferencia electronica</h2>
                            </div>
                        </div>
                        <p class="mt-5 text-sm leading-relaxed text-slate-600">
                            Si pagaste fuera del checkout, reporta tu pago con una referencia y adjunta tu comprobante. Algunas secciones seguiran restringidas hasta que el equipo valide tu membresia.
                        </p>

                        <!-- Datos bancarios — actualiza estos valores -->
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Banco</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">Banamex</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Cuenta</p>
                                <p class="mt-2 text-lg font-semibold text-slate-900">1234567890</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:col-span-2">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">CLABE</p>
                                <p class="mt-2 break-all text-lg font-semibold text-slate-900">002180012345678901</p>
                            </div>
                        </div>

                        <form action="" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Referencia o folio de pago</label>
                                <input type="text" name="referencia_pago" required maxlength="120"
                                    value="<?php echo htmlspecialchars($paymentData['referencia_pago'], ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Ej. SPEI 548219 / Pago membresia mayo"
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Comprobante de pago</label>
                                <div class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                                    <input id="comprobante_pago" type="file" name="comprobante_pago" accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden">
                                    <label for="comprobante_pago" class="block cursor-pointer rounded-[2rem] border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center transition hover:border-blue-400 hover:bg-blue-50">
                                        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-[#5282B2] shadow-sm">
                                            <i class="fa-solid fa-cloud-arrow-up text-2xl"></i>
                                        </span>
                                        <span class="mt-4 block text-lg font-semibold text-slate-900">Sube tu comprobante</span>
                                        <span class="mt-2 block text-sm leading-7 text-slate-500">Haz clic para elegir un archivo o reemplazar el que ya tienes cargado.</span>
                                        <span class="mt-4 inline-flex rounded-full bg-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            PDF, JPG, PNG o WebP · Maximo 5MB
                                        </span>
                                    </label>
                                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Archivo seleccionado</p>
                                                <p id="comprobante_file_name" class="mt-2 truncate text-sm font-semibold text-slate-900">
                                                    <?php echo htmlspecialchars($comprobanteStatusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                </p>
                                            </div>
                                            <?php if ($comprobanteUrl !== ''): ?>
                                            <a href="<?php echo $comprobanteUrl; ?>"
                                               class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-blue-700 shadow-sm hover:text-blue-900">
                                                <i class="fa-solid fa-paperclip"></i> Ver comprobante actual
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                                        <?php foreach ([
                                            ['Formato', 'Legible y completo'],
                                            ['Referencia', 'Debe coincidir con tu folio'],
                                            ['Revision', 'Se valida antes del acceso total'],
                                        ] as [$lbl, $val]): ?>
                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400"><?php echo $lbl; ?></p>
                                            <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo $val; ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-2xl bg-[#5282B2] px-6 py-4 font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">
                                Guardar confirmacion manual
                            </button>
                        </form>
                    </div>

                </div><!-- /.columna principal -->

                <!-- ════════════════════════════════
                     ASIDE / SIDEBAR
                ════════════════════════════════ -->
                <aside class="space-y-4">
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-slate-400">Acceso</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900">Acceso disponible</p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">Tu perfil, el dashboard y este modulo seguiran disponibles. Las secciones restringidas se habilitaran cuando tu membresia quede activa.</p>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-slate-400">Estatus</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900">
                            <?php echo htmlspecialchars($userStatus !== '' ? $userStatus : 'Pendiente de pago', ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">El acceso completo solo se habilita con estatus Activo.</p>
                    </div>

                    <?php if ($paymentData['pago_reportado_at'] !== ''): ?>
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-slate-400">Ultimo reporte manual</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">
                            <?php echo htmlspecialchars($paymentData['pago_reportado_at'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">Tu comprobante ya quedo registrado en el sistema.</p>
                    </div>
                    <?php endif; ?>

                    <?php if (is_array($latestMercadoPagoPayment)): ?>
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-slate-400">Mercado Pago</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">
                            <?php echo htmlspecialchars((string) ($latestMercadoPagoPayment['status'] ?? 'Sin estatus'), ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">
                            Referencia: <?php echo htmlspecialchars((string) ($latestMercadoPagoPayment['external_reference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <?php if (is_array($latestPayPalPayment)): ?>
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-slate-400">PayPal</p>
                        <p class="mt-3 text-lg font-semibold text-slate-900">
                            <?php echo htmlspecialchars((string) ($latestPayPalPayment['status'] ?? 'Sin estatus'), ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">
                            Referencia: <?php echo htmlspecialchars((string) ($latestPayPalPayment['external_reference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <a href="<?php echo BASE_URL; ?>/dashboard.php"
                       class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-4 font-bold text-slate-700 transition hover:bg-slate-50">
                        Ir al dashboard
                    </a>
                </aside>
            </section>
        </div>
    </main>

    <?php if ($paypalEnabled && $paypalClientId !== ''): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode($paypalClientId); ?>&currency=<?php echo urlencode($membresiaMoneda); ?>"></script>
    <?php endif; ?>

    <script>
    // ── Cambio de pestañas ──────────────────────────────────────
    function switchTab(method) {
        var tabs = document.querySelectorAll('.tab');
        var panels = document.querySelectorAll('.panel');
        tabs.forEach(function (t) { t.classList.remove('active'); });
        panels.forEach(function (p) { p.classList.remove('active'); });
        var tab = document.getElementById('tab-' + method);
        var panel = document.getElementById('panel-' + method);
        if (tab && panel) {
            tab.classList.add('active');
            panel.classList.add('active');
            if (method === 'pp' && window.paypal && typeof window.paypal.Buttons === 'function') {
                var ppContainer = document.getElementById('paypal-member-button-container');
                if (ppContainer && !ppContainer.hasAttribute('data-rendered')) {
                    window.paypal.Buttons({
                        style: { layout: 'vertical', shape: 'rect', label: 'paypal' },
                        createOrder: function (data, actions) {
                            return actions.order.create({
                                purchase_units: [{
                                    description: <?php echo json_encode(app_membership_fee_label(), JSON_UNESCAPED_UNICODE); ?>,
                                    custom_id: <?php echo json_encode($paypalExternalReference, JSON_UNESCAPED_UNICODE); ?>,
                                    amount: {
                                        currency_code: <?php echo json_encode($membresiaMoneda, JSON_UNESCAPED_UNICODE); ?>,
                                        value: <?php echo json_encode(number_format(app_membership_fee_amount(), 2, '.', ''), JSON_UNESCAPED_UNICODE); ?>
                                    }
                                }]
                            });
                        },
                        onApprove: function (data) {
                            var query = new URLSearchParams({
                                provider: 'paypal',
                                order_id: data.orderID,
                                external_reference: <?php echo json_encode($paypalExternalReference, JSON_UNESCAPED_UNICODE); ?>
                            });
                            window.location.href = <?php echo json_encode(BASE_URL . '/confirmar_pago.php', JSON_UNESCAPED_UNICODE); ?> + '?' + query.toString();
                        }
                    }).render('#paypal-member-button-container');
                    ppContainer.setAttribute('data-rendered', 'true');
                }
            }
        }
    }

    // ── Formato número de tarjeta ───────────────────────────────
    function formatCard(el) {
        var v = el.value.replace(/\D/g, '').substring(0, 16);
        el.value = v.replace(/(.{4})/g, '$1 ').trim();
    }

    // ── Formato fecha de vencimiento ────────────────────────────
    function formatExpiry(el) {
        var v = el.value.replace(/\D/g, '').substring(0, 4);
        if (v.length >= 3) v = v.substring(0,2) + ' / ' + v.substring(2);
        el.value = v;
    }

    (function () {
        // Actualiza nombre del archivo seleccionado
        var input = document.getElementById('comprobante_pago');
        var fileNameNode = document.getElementById('comprobante_file_name');
        if (input && fileNameNode) {
            input.addEventListener('change', function () {
                fileNameNode.textContent = input.files && input.files.length > 0
                    ? input.files[0].name
                    : <?php echo json_encode($comprobanteStatusLabel, JSON_UNESCAPED_UNICODE); ?>;
            });
        }

        // PayPal se inicializa bajo demanda desde switchTab
        // Si MP está desactivado y PP está activo, inicia PP automáticamente
        var mpEnabled = <?php echo $mercadoPagoEnabled ? 'true' : 'false'; ?>;
        var ppEnabled = <?php echo ($paypalEnabled && $paypalClientId !== '') ? 'true' : 'false'; ?>;
        if (!mpEnabled && ppEnabled && window.paypal && typeof window.paypal.Buttons === 'function') {
            switchTab('pp');
        }
    })();
    </script>
</body>
</html>