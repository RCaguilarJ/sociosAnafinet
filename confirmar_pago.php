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

if (function_exists('app_mark_notifications_by_url')) {
    app_mark_notifications_by_url($pdo, $userId, base_url('confirmar_pago.php'));
}

$flashMessage = (string)($_SESSION['payment_flash_message'] ?? '');
$flashType = (string)($_SESSION['payment_flash_type'] ?? 'info');
$flashSource = (string)($_SESSION['payment_flash_source'] ?? '');
unset($_SESSION['payment_flash_message'], $_SESSION['payment_flash_type'], $_SESSION['payment_flash_source']);

if ($flashMessage !== '') {
    $mensaje = $flashMessage;
    $mensajeTipo = $flashType !== '' ? $flashType : 'info';
}

$showPaymentPopup = $flashMessage !== '' && in_array($mensajeTipo, ['success', 'info'], true);
$paymentPopupTitle = $mensajeTipo === 'success'
    ? 'Pago recibido con exito'
    : 'Tu tramite de pago esta en proceso';
$paymentPopupBody = $mensajeTipo === 'success'
    ? 'Tu pago fue procesado correctamente. Ademas de la activacion de tu acceso, el sistema enviara la confirmacion por correo al usuario que realizo el pago.'
    : 'Recibimos tu operacion y el tramite de pago ya esta en proceso. El sistema enviara un correo al usuario que realizo el pago con la confirmacion y el seguimiento correspondiente.';

if ($flashSource === 'manual_confirmation') {
    $paymentPopupTitle = 'Confirmacion manual registrada';
    $paymentPopupBody = 'Tu confirmacion manual fue guardada correctamente. Tesoreria revisara el comprobante.';
}

function payment_status_badge(string $status): array
{
    $normalized = strtolower(trim($status));
    if ($normalized === 'approved') {
        return ['Pago confirmado', 'bg-emerald-100 text-emerald-800'];
    }
    if (in_array($normalized, ['processing', 'pending', 'in_process'], true)) {
        return ['Pago en proceso', 'bg-amber-100 text-amber-800'];
    }
    if (in_array($normalized, ['checkout_created', 'initiated'], true)) {
        return ['Pago no confirmado', 'bg-rose-100 text-rose-700'];
    }
    if (in_array($normalized, ['failed', 'rejected'], true)) {
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

    try {
        $result = app_store_manual_payment_report($pdo, $userId, $reference, is_array($file) ? $file : []);
        if (!($result['ok'] ?? false)) {
            return [(string)($result['message'] ?? 'No fue posible guardar el comprobante.'), 'error'];
        }

        $_SESSION['user_estatus'] = (string)($result['status'] ?? $_SESSION['user_estatus'] ?? '');
        app_send_manual_payment_received_notifications(
            $pdo,
            $userId,
            $reference,
            (string)($result['proof_url'] ?? '')
        );

        return [(string)($result['message'] ?? 'Tu confirmacion manual fue guardada correctamente.'), 'success'];
    } catch (Throwable $e) {
        return ['No fue posible guardar el comprobante en este momento.', 'error'];
    }
}

$provider = strtolower(trim((string)($_GET['provider'] ?? '')));
$isClipReturn = $provider === 'clip';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $isClipReturn) {
    try {
        $externalReference = trim((string)($_GET['external_reference'] ?? ''));
        if ($externalReference === '') {
            throw new RuntimeException('No se recibio la referencia externa de Clip.');
        }

        $existingPayment = app_get_membership_payment_by_external_reference($pdo, $externalReference);
        $paymentRequestId = trim((string)($existingPayment['provider_order_id'] ?? ''));
        if ($paymentRequestId === '') {
            throw new RuntimeException('No se encontro la referencia del checkout de Clip.');
        }

        $syncedPayment = app_sync_clip_payment_request($pdo, $userId, $externalReference, $paymentRequestId);
        $paymentStatus = (string)($syncedPayment['payment_status'] ?? '');

        if ($paymentStatus === 'approved') {
            $_SESSION['payment_flash_message'] = 'Tu pago en Clip fue confirmado exitosamente. Tambien recibiras el correo de activacion de acceso al foro.';
            $_SESSION['payment_flash_type'] = 'success';
        } elseif ($paymentStatus === 'processing' || $paymentStatus === 'checkout_created') {
            $_SESSION['payment_flash_message'] = 'Tu pago en Clip esta en proceso. Te enviaremos un correo con la confirmacion y podras revisar nuevamente en unos minutos.';
            $_SESSION['payment_flash_type'] = 'info';
        } else {
            $_SESSION['payment_flash_message'] = 'No fue posible confirmar el pago en Clip.';
            $_SESSION['payment_flash_type'] = 'error';
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
    $_SESSION['payment_flash_message'] = $mensaje;
    $_SESSION['payment_flash_type'] = $mensajeTipo;
    $_SESSION['payment_flash_source'] = 'manual_confirmation';
    header('Location: ' . base_url('confirmar_pago.php'));
    exit();
}

$montoPagoFloat = function_exists('app_membership_fee_amount') ? app_membership_fee_amount() : 2500.00;
$montoTotal = number_format($montoPagoFloat, 2, '.', ',');
$conceptoPago = function_exists('app_membership_fee_label')
    ? app_membership_fee_label()
    : 'Membresia anual Anafinet';
$clipHabilitado = function_exists('app_clip_enabled') ? app_clip_enabled() : false;

$stmtUser = $pdo->prepare(
    'SELECT nombre, email, telefono, estatus, referencia_pago, pago_reportado_at, comprobante_pago
     FROM usuarios
     WHERE id = ?
     LIMIT 1'
);
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch() ?: [];
$userStatus = (string)($user['estatus'] ?? $userStatus);
$_SESSION['user_estatus'] = $userStatus;

$stmtLatestPayment = $pdo->prepare(
    'SELECT provider, external_reference, provider_order_id, status, status_detail, amount, currency, checkout_url, paid_at, created_at
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
$latestCheckoutUrl = is_array($latestPayment) ? trim((string)($latestPayment['checkout_url'] ?? '')) : '';
$latestStatusDetail = is_array($latestPayment) ? trim((string)($latestPayment['status_detail'] ?? '')) : '';
$latestPaidAt = is_array($latestPayment) ? trim((string)($latestPayment['paid_at'] ?? '')) : '';
$latestCreatedAt = is_array($latestPayment) ? trim((string)($latestPayment['created_at'] ?? '')) : '';

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
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
<?php
$activePage = 'confirmar_pago';
require __DIR__ . '/menu.php';
?>
    <?php if ($showPaymentPopup): ?>
        <div id="payment-status-popup" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-lg rounded-[2rem] border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] <?php echo $mensajeTipo === 'success' ? 'text-emerald-600' : 'text-blue-600'; ?>">
                            <?php echo $mensajeTipo === 'success' ? 'Confirmacion de pago' : 'Seguimiento del pago'; ?>
                        </p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($paymentPopupTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                    </div>
                    <button type="button" id="payment-status-popup-close" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Cerrar aviso">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="mt-4 rounded-2xl border px-4 py-3 text-sm font-medium <?php echo $alertClass; ?>">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <p class="mt-4 text-sm leading-7 text-slate-600">
                    <?php echo htmlspecialchars($paymentPopupBody, ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <div class="mt-6 flex justify-end">
                    <button type="button" id="payment-status-popup-accept" class="inline-flex items-center justify-center rounded-2xl bg-[#5282B2] px-6 py-3 text-sm font-bold text-white transition hover:bg-blue-700">
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <main class="md:ml-64 p-6 md:p-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="space-y-2">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Confirmacion de pago</p>
                <h1 class="text-3xl font-bold text-slate-900">Confirma tu membresia y completa tu acceso</h1>
                <p class="max-w-3xl text-sm leading-7 text-slate-600">
                    El checkout en linea activo en este momento es Clip. Si prefieres pagar por transferencia o deposito, puedes reportarlo abajo sin alterar el flujo de comprobacion manual.
                </p>
            </header>

            <?php if ($mensaje !== ''): ?>
                <div class="rounded-3xl border px-5 py-4 text-sm font-medium shadow-sm <?php echo $alertClass; ?>">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-6 md:p-8 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                <i class="fa-solid fa-bolt"></i>
                                Checkout activo
                            </span>
                            <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Pago en linea</span>
                        </div>
                        <div>
                            <h2 class="text-3xl font-bold text-slate-900">Clip</h2>
                            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
                                El pago en linea se genera con Clip mediante un checkout redireccionado y seguro.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 min-w-[220px]">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Importe</p>
                        <p class="mt-2 text-4xl font-black text-slate-900">$<?php echo htmlspecialchars($montoTotal, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mt-1 text-sm text-slate-500">MXN / <?php echo htmlspecialchars($conceptoPago, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                    <div class="rounded-[2rem] border border-slate-200 bg-slate-50/70 p-5 md:p-6">
                        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-5 md:p-6 shadow-sm">
                            <div class="grid gap-5 md:grid-cols-[1.2fr_0.8fr] md:items-start">
                                <div class="space-y-3">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Metodo de pago</p>
                                    <h3 class="text-3xl font-bold text-slate-900">Checkout Clip</h3>
                                    <p class="text-sm leading-7 text-slate-600">
                                        Se genera un link de pago seguro y el sistema valida el estado del checkout al volver al portal.
                                    </p>
                                </div>
                                <div class="rounded-[1.35rem] border border-slate-200 bg-slate-50 p-5">
                                    <div class="text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Resumen</div>
                                    <div class="mt-3 text-4xl font-black leading-none text-slate-900">$<?php echo htmlspecialchars($montoTotal, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="mt-3 text-sm text-slate-500"><?php echo htmlspecialchars($conceptoPago, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <span class="mt-4 inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">Pago seguro</span>
                                </div>
                            </div>

                            <div class="mt-6 grid gap-4 md:grid-cols-3">
                                <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Proveedor</p>
                                    <p class="mt-3 text-lg font-bold text-slate-900">Clip</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Estado del ultimo intento</p>
                                    <span class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-bold <?php echo $latestPaymentBadgeClass; ?>">
                                        <?php echo htmlspecialchars($latestPaymentLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Acceso</p>
                                    <p class="mt-3 text-sm font-semibold text-slate-900">Se activa cuando Clip confirma el pago</p>
                                </div>
                            </div>

                            <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                                <?php if (!$clipHabilitado): ?>
                                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                                        Clip aun no esta configurado en este ambiente.
                                    </div>
                                <?php else: ?>
                                    <form action="<?php echo htmlspecialchars(base_url('clip_create_payment.php'), ENT_QUOTES, 'UTF-8'); ?>" method="POST" class="space-y-4">
                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Cliente</p>
                                                <p class="mt-3 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars((string)($user['nombre'] ?? 'Asociado'), ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Reanudar checkout</p>
                                                <?php if ($latestCheckoutUrl !== '' && is_array($latestPayment) && strtolower((string)($latestPayment['provider'] ?? '')) === 'clip' && !app_is_membership_active_status($userStatus)): ?>
                                                    <a href="<?php echo htmlspecialchars($latestCheckoutUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-900">
                                                        <i class="fa-solid fa-up-right-from-square"></i>
                                                        Abrir ultimo link generado
                                                    </a>
                                                <?php else: ?>
                                                    <p class="mt-3 text-sm text-slate-500">Al generar un link nuevo, aqui podras retomarlo si sales antes de pagar.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-[#5b3df5] px-8 py-4 text-base font-bold text-white shadow-lg shadow-indigo-100 transition hover:bg-indigo-700 <?php echo !$clipHabilitado ? 'opacity-60 cursor-not-allowed' : ''; ?>" <?php echo !$clipHabilitado ? 'disabled' : ''; ?>>
                                            Pagar con Clip
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Ultimo intento</p>
                            <h3 class="mt-3 text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($latestProvider, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="mt-3 break-all text-sm leading-7 text-slate-600">
                                <?php echo $latestReference !== '' ? htmlspecialchars($latestReference, ENT_QUOTES, 'UTF-8') : 'Aun no existe una referencia generada para tu ultimo intento.'; ?>
                            </p>
                            <?php if ($latestStatusDetail !== ''): ?>
                                <p class="mt-3 text-sm text-slate-500">Detalle: <?php echo htmlspecialchars($latestStatusDetail, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <?php if ($latestPaidAt !== ''): ?>
                                <p class="mt-3 text-sm text-slate-500">Pago confirmado: <?php echo htmlspecialchars($latestPaidAt, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php elseif ($latestCreatedAt !== ''): ?>
                                <p class="mt-3 text-sm text-slate-500">Intento creado: <?php echo htmlspecialchars($latestCreatedAt, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
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
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Acceso</p>
                            <h3 class="mt-3 text-2xl font-bold text-slate-900">Acceso disponible</h3>
                            <p class="mt-3 text-sm leading-7 text-slate-600">
                                Tu perfil, el dashboard y este modulo seguiran disponibles. Las secciones restringidas se habilitaran cuando tu membresia quede activa.
                            </p>
                        </section>
                    </div>
                </div>
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-6 md:p-8 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                                <i class="fa-solid fa-building-columns"></i>
                                Respaldo manual
                            </span>
                            <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Transferencia o deposito</span>
                        </div>
                        <div>
                            <h2 class="text-3xl font-bold text-slate-900">Transferencia electronica</h2>
                            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
                                Si pagaste fuera del checkout de Clip, registra tu referencia y sube el comprobante. La validacion manual permanece intacta.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4 min-w-[220px]">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Importe a validar</p>
                        <p class="mt-2 text-4xl font-black text-slate-900">$<?php echo htmlspecialchars($montoTotal, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mt-1 text-sm text-slate-500">MXN / <?php echo htmlspecialchars($conceptoPago, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="mt-6 rounded-[2rem] border border-slate-200 bg-gradient-to-b from-slate-100 to-slate-50 p-4 md:p-6">
                    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.3fr]">
                        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_50px_rgba(15,23,42,0.08)]">
                            <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-6">
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                                        <i class="fa-solid fa-money-check-dollar"></i>
                                        Transferencia
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-500 ring-1 ring-slate-200">
                                        Deposito
                                    </span>
                                </div>
                                <h3 class="mt-4 text-2xl font-bold text-slate-900">Datos bancarios para validar</h3>
                                <p class="mt-2 text-sm leading-7 text-slate-600">
                                    Usa estos datos para tu SPEI o deposito y conserva una evidencia legible del movimiento.
                                </p>
                            </div>
                            <div class="space-y-4 px-6 py-6">
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Banco</p>
                                    <p class="mt-3 text-xl font-bold text-slate-900">BANSI</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Beneficiario</p>
                                    <p class="mt-3 text-xl font-bold text-slate-900">Asociacion Nacional de Fiscalistas Net A.C.</p>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">CLABE</p>
                                    <p class="mt-3 text-2xl font-black tracking-[0.08em] text-slate-900">0603200000991177021</p>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Revision</p>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Validacion manual por tesoreria</p>
                                    </div>
                                    <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Acceso total</p>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Se libera al confirmar el deposito</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_20px_50px_rgba(15,23,42,0.08)]">
                            <input type="hidden" name="reportar_transferencia" value="1">
                            <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-6">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Reporta tu pago</p>
                                <h3 class="mt-2 text-2xl font-bold text-slate-900">Referencia y comprobante</h3>
                                <p class="mt-2 text-sm leading-7 text-slate-600">
                                    Captura la referencia del SPEI o del deposito y adjunta el comprobante completo para acelerar la revision.
                                </p>
                            </div>
                            <div class="space-y-5 px-6 py-6">
                                <div>
                                    <label for="referencia_pago" class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Referencia o folio de pago</label>
                                    <input id="referencia_pago" type="text" name="referencia_pago" value="<?php echo htmlspecialchars($manualReference, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. SPEI 548219 / Pago membresia mayo" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-base outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" required>
                                </div>

                                <div>
                                    <label for="comprobante" class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Comprobante de pago</label>
                                    <label class="flex min-h-[210px] cursor-pointer flex-col items-center justify-center rounded-[2rem] border border-dashed border-slate-300 bg-slate-50 px-6 py-8 text-center transition hover:border-blue-300 hover:bg-blue-50/40">
                                        <span class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-700 shadow-sm">
                                            <i class="fa-solid fa-cloud-arrow-up"></i>
                                        </span>
                                        <span class="text-xl font-bold text-slate-900">Sube tu comprobante</span>
                                        <span class="mt-2 text-sm text-slate-500">Haz clic para elegir un archivo o reemplazar el que ya tienes cargado.</span>
                                        <span class="mt-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">PDF, JPG, PNG o WebP · maximo 5MB</span>
                                        <input id="comprobante" type="file" name="comprobante" accept="image/*,application/pdf" class="hidden" required>
                                    </label>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
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
                                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Formato</p>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Legible y completo</p>
                                    </div>
                                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Revision</p>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Se valida antes del acceso total</p>
                                    </div>
                                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Seguimiento</p>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Tesoreria revisara la referencia, el monto y la evidencia adjunta.</p>
                                    </div>
                                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Accion</p>
                                        <p class="mt-3 text-sm font-semibold text-slate-900">Guardar confirmacion manual</p>
                                    </div>
                                </div>

                                <?php if ($manualReportedAt !== ''): ?>
                                    <div class="rounded-3xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                                        Ultimo reporte manual registrado el <?php echo htmlspecialchars($manualReportedAt, ENT_QUOTES, 'UTF-8'); ?>.
                                    </div>
                                <?php endif; ?>

                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-[#5282B2] px-8 py-4 text-base font-bold text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700">
                                    Guardar confirmacion manual
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <div class="pt-2">
                <a href="<?php echo htmlspecialchars(base_url('dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-8 py-4 text-sm font-bold text-slate-700 transition hover:bg-slate-100">
                    Ir al dashboard
                </a>
            </div>
        </div>
    </main>

    <script>
        const paymentStatusPopup = document.getElementById('payment-status-popup');
        const closePaymentStatusPopup = () => {
            if (paymentStatusPopup) {
                paymentStatusPopup.classList.add('hidden');
            }
        };

        document.getElementById('payment-status-popup-close')?.addEventListener('click', closePaymentStatusPopup);
        document.getElementById('payment-status-popup-accept')?.addEventListener('click', closePaymentStatusPopup);
        paymentStatusPopup?.addEventListener('click', (event) => {
            if (event.target === paymentStatusPopup) {
                closePaymentStatusPopup();
            }
        });
    </script>
</body>
</html>
