<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/role_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!($pdo instanceof PDO)) {
    render_database_unavailable_page('confirmar_pago', 'Confirmar pago');
}

ensure_user_payment_columns($pdo);
app_ensure_membership_payment_schema($pdo);

$userId = (int)($_SESSION['user_id'] ?? 0);
$userStatus = (string)($_SESSION['user_estatus'] ?? '');
$mensaje = '';
$mensajeTipo = 'success';

$flashMessage = (string)($_SESSION['payment_flash_message'] ?? '');
$flashType = (string)($_SESSION['payment_flash_type'] ?? 'info');
unset($_SESSION['payment_flash_message'], $_SESSION['payment_flash_type']);

if ($flashMessage !== '') {
    $mensaje = $flashMessage;
    $mensajeTipo = $flashType !== '' ? $flashType : 'info';
}

function payment_status_badge(string $status): array
{
    $normalized = strtolower(trim($status));
    if ($normalized === 'approved') {
        return ['Pago confirmado', 'bg-emerald-100 text-emerald-800'];
    }
    if ($normalized === 'processing' || $normalized === 'pending' || $normalized === 'in_process') {
        return ['Pago en proceso', 'bg-amber-100 text-amber-800'];
    }
    if ($normalized === 'checkout_created' || $normalized === 'initiated') {
        return ['Pago no confirmado', 'bg-rose-100 text-rose-700'];
    }
    if ($normalized === 'failed' || $normalized === 'rejected') {
        return ['Pago rechazado', 'bg-red-100 text-red-700'];
    }

    return ['Sin intentos recientes', 'bg-slate-100 text-slate-700'];
}

function user_status_badge(string $status): string
{
    $normalized = strtolower(trim($status));
    if ($normalized === 'activo') {
        return 'bg-emerald-100 text-emerald-800';
    }
    if ($normalized === 'pago reportado' || $normalized === 'pago en proceso') {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-blue-100 text-blue-800';
}

function process_manual_payment_report(PDO $pdo, int $userId): array
{
    $reference = trim((string)($_POST['referencia_pago'] ?? ''));
    $file = $_FILES['comprobante'] ?? null;

    if ($reference === '') {
        return ['Escribe la referencia o folio del pago.', 'error'];
    }

    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['Adjunta el comprobante del pago.', 'error'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['No fue posible subir el comprobante.', 'error'];
    }

    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    $allowedMimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['El comprobante debe ser PDF, JPG, PNG o WebP.', 'error'];
    }

    if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['El comprobante excede el maximo permitido de 5MB.', 'error'];
    }

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file((string)$file['tmp_name']);
        if ($mime !== '' && !in_array($mime, $allowedMimes, true)) {
            return ['El archivo adjunto no tiene un formato valido.', 'error'];
        }
    }

    $directory = app_ensure_storage_directory('comprobantes_pago');
    if (!is_dir($directory) || !is_writable($directory)) {
        return ['La carpeta de comprobantes no tiene permisos de escritura.', 'error'];
    }

    $filename = 'comprobante_pago_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = app_storage_path('comprobantes_pago', $filename);

    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        return ['No fue posible guardar el comprobante.', 'error'];
    }

    $stmtCurrent = $pdo->prepare('SELECT comprobante_pago, estatus FROM usuarios WHERE id = ? LIMIT 1');
    $stmtCurrent->execute([$userId]);
    $currentUser = $stmtCurrent->fetch();
    $oldFile = is_array($currentUser) ? trim((string)($currentUser['comprobante_pago'] ?? '')) : '';
    $currentStatus = is_array($currentUser) ? (string)($currentUser['estatus'] ?? '') : '';
    $newStatus = app_is_membership_active_status($currentStatus) ? $currentStatus : 'Pago reportado';

    $stmt = $pdo->prepare(
        'UPDATE usuarios
         SET comprobante_pago = ?, referencia_pago = ?, pago_reportado_at = NOW(), estatus = ?
         WHERE id = ?'
    );
    $stmt->execute([$filename, $reference, $newStatus, $userId]);

    if ($oldFile !== '' && $oldFile !== $filename) {
        $oldPath = app_storage_path('comprobantes_pago', $oldFile);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $_SESSION['user_estatus'] = $newStatus;

    return ['Tu confirmacion manual fue guardada correctamente. Tesoreria revisara el comprobante.', 'success'];
}

$provider = strtolower(trim((string)($_GET['provider'] ?? '')));
$isPaypalReturn = $provider === 'paypal' || (($_GET['paypal_success'] ?? '') === '1');
$isMercadoPagoReturn = $provider === 'mercadopago';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($isPaypalReturn || $isMercadoPagoReturn)) {
    try {
        if ($isPaypalReturn) {
            $orderId = trim((string)($_GET['order_id'] ?? ''));
            $externalReference = trim((string)($_GET['external_reference'] ?? ($_SESSION['paypal_membership_external_reference'] ?? '')));

            if ($orderId === '') {
                throw new RuntimeException('No se recibio la orden de PayPal.');
            }

            $syncedPayment = app_sync_paypal_order($pdo, $userId, $externalReference, $orderId);
            if ($syncedPayment['payment_status'] === 'approved') {
                $_SESSION['payment_flash_message'] = 'Tu renovacion fue confirmada exitosamente.';
                $_SESSION['payment_flash_type'] = 'success';
            } elseif ($syncedPayment['payment_status'] === 'processing') {
                $_SESSION['payment_flash_message'] = 'Tu pago en PayPal quedo en proceso. Revisa nuevamente en unos minutos.';
                $_SESSION['payment_flash_type'] = 'info';
            } else {
                $_SESSION['payment_flash_message'] = 'No fue posible confirmar el pago en PayPal.';
                $_SESSION['payment_flash_type'] = 'error';
            }

            unset($_SESSION['paypal_membership_external_reference']);
        } else {
            $paymentId = trim((string)($_GET['payment_id'] ?? $_GET['collection_id'] ?? ''));
            $externalReference = trim((string)($_GET['external_reference'] ?? ''));
            $returnState = trim((string)($_GET['mp_return'] ?? ''));

            if ($paymentId !== '' && app_mercadopago_enabled()) {
                $syncedPayment = app_sync_mercadopago_payment($pdo, $paymentId);
                $paymentStatus = (string)($syncedPayment['payment_status'] ?? '');

                if ($paymentStatus === 'approved') {
                    $_SESSION['payment_flash_message'] = 'Tu renovacion fue confirmada exitosamente.';
                    $_SESSION['payment_flash_type'] = 'success';
                } elseif ($paymentStatus === 'processing' || $returnState === 'pending') {
                    $_SESSION['payment_flash_message'] = 'Tu pago en Mercado Pago esta en proceso. Revisa nuevamente en unos minutos.';
                    $_SESSION['payment_flash_type'] = 'info';
                } else {
                    $_SESSION['payment_flash_message'] = 'No fue posible confirmar el pago en Mercado Pago.';
                    $_SESSION['payment_flash_type'] = 'error';
                }
            } elseif ($externalReference !== '') {
                $status = $returnState === 'pending' ? 'processing' : 'failed';
                app_update_membership_payment_attempt($pdo, $externalReference, [
                    'provider_order_id' => trim((string)($_GET['preference_id'] ?? '')),
                    'status' => $status,
                    'raw_payload' => $_GET,
                ]);

                $_SESSION['payment_flash_message'] = $status === 'processing'
                    ? 'Tu pago en Mercado Pago quedo en proceso.'
                    : 'No fue posible confirmar el pago en Mercado Pago.';
                $_SESSION['payment_flash_type'] = $status === 'processing' ? 'info' : 'error';
            }
        }
    } catch (Throwable $e) {
        $_SESSION['payment_flash_message'] = 'No fue posible validar el resultado del pago en este momento.';
        $_SESSION['payment_flash_type'] = 'error';
    }

    header('Location: ' . base_url('confirmar_pago.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reportar_transferencia'])) {
    [$mensaje, $mensajeTipo] = process_manual_payment_report($pdo, $userId);
}

$paypalExternalReference = (string)($_SESSION['paypal_membership_external_reference'] ?? '');
if ($paypalExternalReference === '') {
    $paypalExternalReference = app_membership_payment_reference($userId);
    $_SESSION['paypal_membership_external_reference'] = $paypalExternalReference;
}

$montoPagoFloat = function_exists('app_membership_fee_amount') ? app_membership_fee_amount() : 1500.00;
$montoTotal = number_format($montoPagoFloat, 2, '.', ',');
$conceptoPago = function_exists('app_membership_fee_label')
    ? app_membership_fee_label()
    : 'Membresia Anafinet';

$mpHabilitado = function_exists('app_mercadopago_enabled') ? app_mercadopago_enabled() : false;
$ppHabilitado = function_exists('app_paypal_enabled') ? app_paypal_enabled() : false;

$stmtUser = $pdo->prepare(
    'SELECT nombre, email, estatus, referencia_pago, pago_reportado_at, comprobante_pago
     FROM usuarios
     WHERE id = ?
     LIMIT 1'
);
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch() ?: [];
$userStatus = (string)($user['estatus'] ?? $userStatus);
$_SESSION['user_estatus'] = $userStatus;

$stmtLatestPayment = $pdo->prepare(
    'SELECT provider, external_reference, status, status_detail, amount, currency, paid_at, created_at
     FROM pagos_membresia
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 1'
);
$stmtLatestPayment->execute([$userId]);
$latestPayment = $stmtLatestPayment->fetch();

$latestPaymentStatus = is_array($latestPayment) ? (string)($latestPayment['status'] ?? '') : '';
[$latestPaymentLabel, $latestPaymentBadgeClass] = payment_status_badge($latestPaymentStatus);
$latestProvider = is_array($latestPayment) ? app_payment_provider_label((string)($latestPayment['provider'] ?? '')) : 'Sin intentos';
$latestReference = is_array($latestPayment) ? (string)($latestPayment['external_reference'] ?? '') : '';
$latestStatusDetail = is_array($latestPayment) ? (string)($latestPayment['status_detail'] ?? '') : '';
$latestPaidAt = is_array($latestPayment) ? (string)($latestPayment['paid_at'] ?? '') : '';
$latestCreatedAt = is_array($latestPayment) ? (string)($latestPayment['created_at'] ?? '') : '';

$manualReference = (string)($user['referencia_pago'] ?? '');
$manualReportedAt = (string)($user['pago_reportado_at'] ?? '');
$manualFile = (string)($user['comprobante_pago'] ?? '');
$manualFileUrl = $manualFile !== '' ? uploaded_file_url('comprobantes_pago', $manualFile, true) : '';

$alertClass = 'border-emerald-200 bg-emerald-50 text-emerald-700';
if ($mensajeTipo === 'error') {
    $alertClass = 'border-red-200 bg-red-50 text-red-700';
} elseif ($mensajeTipo === 'info') {
    $alertClass = 'border-blue-200 bg-blue-50 text-blue-700';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php app_render_favicon_tags(); ?>
    <title>Confirmar Pago - Anafinet</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('assets/tailwind.build.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .tab-active {
            border-color: #bfdbfe;
            background-color: #eff6ff;
            color: #1d4ed8;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
<?php
$activePage = 'confirmar_pago';
require __DIR__ . '/menu.php';
?>
    <main class="md:ml-64 p-6 md:p-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="space-y-2">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Confirmacion de pago</p>
                <h1 class="text-3xl font-bold text-slate-900">Confirma tu membresia y completa tu acceso</h1>
                <p class="max-w-3xl text-sm leading-7 text-slate-600">
                    Aqui puedes pagar en linea con las pasarelas habilitadas o registrar manualmente una transferencia o deposito.
                    El acceso total se habilita cuando tu pago quede confirmado.
                </p>
            </header>

            <?php if ($mensaje !== ''): ?>
                <div class="rounded-3xl border px-5 py-4 text-sm font-medium shadow-sm <?php echo $alertClass; ?>">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <section class="rounded-[2rem] border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="grid gap-0 xl:grid-cols-[1.7fr_0.9fr]">
                    <div class="p-6 md:p-8 space-y-6">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                <i class="fa-solid fa-bolt"></i>
                                Pago recomendado
                            </span>
                            <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Pago en linea</span>
                        </div>

                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-3xl font-bold text-slate-900">Mercado Pago / PayPal</h2>
                                    <span class="rounded-full px-3 py-1 text-xs font-bold <?php echo $latestPaymentBadgeClass; ?>">
                                        <?php echo htmlspecialchars($latestPaymentLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <p class="max-w-2xl text-sm leading-7 text-slate-600">
                                    Paga tu membresia con tarjeta, saldo o medios compatibles desde un checkout seguro.
                                    El acceso completo se habilita automaticamente cuando la pasarela confirma la operacion.
                                </p>
                            </div>
                            <div class="rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 min-w-[220px]">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Importe</p>
                                <p class="mt-2 text-4xl font-black text-slate-900">$<?php echo htmlspecialchars($montoTotal, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="mt-1 text-sm text-slate-500">MXN · <?php echo htmlspecialchars($conceptoPago, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                                <h3 class="font-bold text-slate-900">Checkout seguro</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    El cobro se procesa fuera del portal y vuelve con confirmacion del proveedor.
                                </p>
                            </div>
                            <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <h3 class="font-bold text-slate-900">Activacion automatica</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    Cuando el pago se aprueba, tu membresia cambia a Activo sin captura manual.
                                </p>
                            </div>
                            <div class="rounded-3xl border border-slate-200 bg-white p-5">
                                <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </div>
                                <h3 class="font-bold text-slate-900">Seguimiento del intento</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">
                                    Puedes reanudar el checkout si saliste antes de terminarlo.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-5 xl:grid-cols-[1fr_1.15fr]">
                            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Como funciona</p>
                                <ol class="mt-4 space-y-4 text-sm text-slate-700">
                                    <li class="flex gap-3">
                                        <span class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">1</span>
                                        <div>
                                            <p class="font-semibold text-slate-900">Abres el checkout</p>
                                            <p class="mt-1 text-slate-600">Te enviamos a la pasarela con el importe y la referencia ya configurados.</p>
                                        </div>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">2</span>
                                        <div>
                                            <p class="font-semibold text-slate-900">Pagas con el metodo que prefieras</p>
                                            <p class="mt-1 text-slate-600">Tarjeta, saldo o el medio disponible en tu cuenta y tu region.</p>
                                        </div>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">3</span>
                                        <div>
                                            <p class="font-semibold text-slate-900">Se actualiza tu acceso</p>
                                            <p class="mt-1 text-slate-600">Si el pago se aprueba, el sistema habilita automaticamente tu membresia.</p>
                                        </div>
                                    </li>
                                </ol>
                            </div>

                            <div class="rounded-[2rem] border border-sky-100 bg-white p-6 shadow-[0_20px_45px_rgba(14,116,144,0.12)]">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Estado del ultimo intento</p>
                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <h3 class="text-3xl font-bold text-slate-900"><?php echo htmlspecialchars($latestPaymentLabel, ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <span class="rounded-full px-3 py-1 text-xs font-bold <?php echo $latestPaymentBadgeClass; ?>">
                                        <?php echo htmlspecialchars($latestProvider, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <p class="mt-3 text-sm leading-7 text-slate-600">
                                    <?php if ($latestReference !== ''): ?>
                                        Referencia: <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($latestReference, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        Aun no hay un pago aprobado para esta membresia.
                                    <?php endif; ?>
                                </p>
                                <?php if ($latestStatusDetail !== ''): ?>
                                    <p class="mt-2 text-sm text-slate-500">Detalle: <?php echo htmlspecialchars($latestStatusDetail, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <?php if ($latestPaidAt !== '' || $latestCreatedAt !== ''): ?>
                                    <p class="mt-2 text-sm text-slate-500">
                                        <?php echo htmlspecialchars($latestPaidAt !== '' ? $latestPaidAt : $latestCreatedAt, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-[2rem] border border-slate-200 bg-slate-50 p-6">
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" id="tab-mp" onclick="switchPaymentMethod('mp')" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm transition tab-active">
                                    Mercado Pago
                                </button>
                                <button type="button" id="tab-paypal" onclick="switchPaymentMethod('paypal')" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-500 shadow-sm transition">
                                    PayPal
                                </button>
                            </div>

                            <div id="panel-mp" class="payment-panel mt-6 block">
                                <?php if (!$mpHabilitado): ?>
                                    <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-800">
                                        Mercado Pago aun no esta configurado en este ambiente.
                                    </div>
                                <?php endif; ?>

                                <form action="<?php echo htmlspecialchars(base_url('mercadopago_create_payment.php'), ENT_QUOTES, 'UTF-8'); ?>" method="POST" class="space-y-4">
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-2">Numero de tarjeta</label>
                                            <input type="text" placeholder="0000 0000 0000 0000" oninput="formatCardNumber(this)" maxlength="19" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" required <?php echo !$mpHabilitado ? 'disabled value="4556 7812 9011 4452"' : ''; ?>>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-2">Nombre del titular</label>
                                            <input type="text" placeholder="Como aparece en el plastico" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" required <?php echo !$mpHabilitado ? 'disabled value="JUAN PEREZ LOZANO"' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="grid gap-4 md:grid-cols-3">
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-2">Expiracion</label>
                                            <input type="text" placeholder="MM/AA" oninput="formatExpiryDate(this)" maxlength="5" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" required <?php echo !$mpHabilitado ? 'disabled value="12/29"' : ''; ?>>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-2">CVV</label>
                                            <input type="password" placeholder="***" maxlength="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" required <?php echo !$mpHabilitado ? 'disabled value="123"' : ''; ?>>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-2">Plazos</label>
                                            <select class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" <?php echo !$mpHabilitado ? 'disabled' : ''; ?>>
                                                <option>1 Pago liquido de $<?php echo htmlspecialchars($montoTotal, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <option>3 Mensualidades sin intereses</option>
                                                <option>6 Mensualidades sin intereses</option>
                                            </select>
                                        </div>
                                    </div>

                                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#5282B2] px-8 py-4 text-sm font-bold text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700 <?php echo !$mpHabilitado ? 'opacity-60 cursor-not-allowed' : ''; ?>" <?php echo !$mpHabilitado ? 'disabled' : ''; ?>>
                                        Pagar con Mercado Pago
                                    </button>
                                </form>
                            </div>

                            <div id="panel-paypal" class="payment-panel mt-6 hidden">
                                <?php if (!$ppHabilitado): ?>
                                    <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-800">
                                        PayPal aun no esta configurado en este ambiente.
                                    </div>
                                <?php endif; ?>

                                <div class="rounded-3xl border border-slate-200 bg-white p-6 text-center">
                                    <div class="flex justify-center mb-3">
                                        <span class="text-2xl font-black italic text-[#003087]">Pay<span class="text-[#009CDE]">Pal</span></span>
                                    </div>
                                    <p class="mx-auto max-w-xl text-sm leading-7 text-slate-600">
                                        Seras redirigido a la ventana segura de PayPal para finalizar tu transaccion.
                                    </p>
                                    <?php if ($ppHabilitado): ?>
                                        <div id="paypal-button-container" class="mt-6 w-full"></div>
                                    <?php else: ?>
                                        <button type="button" class="mt-6 inline-flex items-center justify-center rounded-2xl bg-[#003087] px-8 py-4 text-sm font-bold text-white opacity-60 cursor-not-allowed">
                                            Pagar con PayPal
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-6 md:p-8 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Respaldo manual</p>
                        <h2 class="text-3xl font-bold text-slate-900">Transferencia electronica</h2>
                    </div>
                </div>
                <p class="mt-4 max-w-4xl text-sm leading-7 text-slate-600">
                    Si pagaste fuera del checkout, reporta tu pago con una referencia y adjunta tu comprobante.
                    Algunas secciones seguiran restringidas hasta que el equipo valide tu membresia.
                </p>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Banco</p>
                        <p class="mt-3 text-xl font-bold text-slate-900">BANSI</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 md:col-span-2">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Beneficiario</p>
                        <p class="mt-3 text-xl font-bold text-slate-900">Asociacion Nacional de Fiscalistas Net A.C.</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 md:col-span-3">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">CLABE</p>
                        <p class="mt-3 text-2xl font-black tracking-[0.08em] text-slate-900">0603200000991177021</p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
                    <input type="hidden" name="reportar_transferencia" value="1">
                    <div>
                        <label for="referencia_pago" class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-2">Referencia o folio de pago</label>
                        <input id="referencia_pago" type="text" name="referencia_pago" value="<?php echo htmlspecialchars($manualReference, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. SPEI 548219 / Pago membresia mayo" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" required>
                    </div>

                    <div>
                        <label for="comprobante" class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-2">Comprobante de pago</label>
                        <label class="flex min-h-[180px] cursor-pointer flex-col items-center justify-center rounded-[2rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center transition hover:border-blue-300 hover:bg-blue-50/40">
                            <span class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-700 shadow-sm">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </span>
                            <span class="text-lg font-bold text-slate-900">Sube tu comprobante</span>
                            <span class="mt-2 text-sm text-slate-500">Haz clic para elegir un archivo o reemplazar el que ya tienes cargado.</span>
                            <span class="mt-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">PDF, JPG, PNG o WebP · maximo 5MB</span>
                            <input id="comprobante" type="file" name="comprobante" accept="image/*,application/pdf" class="hidden" required>
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="rounded-3xl border border-slate-200 bg-white p-4 md:col-span-2">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Archivo seleccionado</p>
                            <?php if ($manualFileUrl !== ''): ?>
                                <a href="<?php echo htmlspecialchars($manualFileUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-900">
                                    <i class="fa-solid fa-paperclip"></i>
                                    Ver comprobante cargado
                                </a>
                            <?php else: ?>
                                <p class="mt-3 text-sm text-slate-600">PDF, JPG, PNG o WebP hasta 5MB</p>
                            <?php endif; ?>
                        </div>
                        <div class="rounded-3xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Formato</p>
                            <p class="mt-3 text-sm font-semibold text-slate-900">Legible y completo</p>
                        </div>
                        <div class="rounded-3xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Revision</p>
                            <p class="mt-3 text-sm font-semibold text-slate-900">Se valida antes del acceso total</p>
                        </div>
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-[#5282B2] px-8 py-4 text-sm font-bold text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700">
                        Guardar confirmacion manual
                    </button>
                </form>
            </section>

            <div class="grid gap-4 lg:grid-cols-3">
                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Acceso</p>
                    <h3 class="mt-3 text-2xl font-bold text-slate-900">Acceso disponible</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-600">
                        Tu perfil, el dashboard y este modulo seguiran disponibles. Las secciones restringidas se habilitaran cuando tu membresia quede activa.
                    </p>
                </section>

                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Estatus</p>
                    <div class="mt-3 flex items-center gap-3">
                        <h3 class="text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($userStatus, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="rounded-full px-3 py-1 text-xs font-bold <?php echo user_status_badge($userStatus); ?>">
                            Membresia
                        </span>
                    </div>
                    <p class="mt-3 text-sm leading-7 text-slate-600">
                        El acceso completo solo se habilita con estatus Activo.
                    </p>
                </section>

                <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400"><?php echo htmlspecialchars($latestProvider, ENT_QUOTES, 'UTF-8'); ?></p>
                    <h3 class="mt-3 text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($latestPaymentStatus !== '' ? $latestPaymentStatus : 'Sin intentos', ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="mt-3 break-all text-sm leading-7 text-slate-600">
                        <?php echo $latestReference !== '' ? htmlspecialchars($latestReference, ENT_QUOTES, 'UTF-8') : 'Aun no existe una referencia generada para tu ultimo intento.'; ?>
                    </p>
                    <?php if ($manualReportedAt !== ''): ?>
                        <p class="mt-3 text-sm text-slate-500">Ultimo reporte manual: <?php echo htmlspecialchars($manualReportedAt, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </section>
            </div>

            <div class="pt-2">
                <a href="<?php echo htmlspecialchars(base_url('dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-8 py-4 text-sm font-bold text-slate-700 transition hover:bg-slate-100">
                    Ir al dashboard
                </a>
            </div>
        </div>
    </main>

    <script>
        const paypalExternalReference = <?php echo json_encode($paypalExternalReference, JSON_UNESCAPED_SLASHES); ?>;
        const paypalAmount = <?php echo json_encode(number_format($montoPagoFloat, 2, '.', ''), JSON_UNESCAPED_SLASHES); ?>;

        function switchPaymentMethod(method) {
            document.querySelectorAll('.payment-panel').forEach(panel => panel.classList.add('hidden'));
            document.querySelectorAll('#tab-mp, #tab-paypal').forEach(tab => {
                tab.classList.remove('tab-active');
                tab.classList.remove('text-slate-700');
                tab.classList.add('text-slate-500');
            });

            document.getElementById('panel-' + method).classList.remove('hidden');
            document.getElementById('tab-' + method).classList.add('tab-active');
            document.getElementById('tab-' + method).classList.remove('text-slate-500');
            document.getElementById('tab-' + method).classList.add('text-slate-700');
        }

        function formatCardNumber(input) {
            let value = input.value.replace(/\D/g, '').substring(0, 16);
            input.value = value.replace(/(.{4})/g, '$1 ').trim();
        }

        function formatExpiryDate(input) {
            let value = input.value.replace(/\D/g, '').substring(0, 4);
            if (value.length >= 3) {
                input.value = value.substring(0, 2) + '/' + value.substring(2);
            } else {
                input.value = value;
            }
        }
    </script>

    <?php if ($ppHabilitado): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode((string)getenv('PAYPAL_CLIENT_ID')); ?>&currency=MXN"></script>
    <script>
        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: { value: paypalAmount },
                        custom_id: paypalExternalReference
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function() {
                    const params = new URLSearchParams({
                        provider: 'paypal',
                        paypal_success: '1',
                        order_id: data.orderID,
                        external_reference: paypalExternalReference
                    });
                    window.location.href = <?php echo json_encode(base_url('confirmar_pago.php') . '?', JSON_UNESCAPED_SLASHES); ?> + params.toString();
                });
            }
        }).render('#paypal-button-container');
    </script>
    <?php endif; ?>
</body>
</html>
