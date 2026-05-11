<?php
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
$latestMercadoPagoPayment = null;

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
             FROM usuarios
             WHERE id = ?
             LIMIT 1'
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
    } catch (Throwable $e) {
        $schemaError = 'No fue posible preparar el modulo de confirmacion de pago en este momento.';
    }
}

if (
    $schemaError === ''
    && $pdo instanceof PDO
    && isset($_GET['provider'])
    && $_GET['provider'] === 'mercadopago'
) {
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
        } elseif ($paymentStatus === 'processing' || $returnState === 'pending') {
            $mensaje = 'Tu pago esta en proceso. Mientras Mercado Pago lo confirma, algunas secciones seguiran restringidas.';
            $mensajeTipo = 'info';
        } elseif ($returnState === 'failure' || $paymentStatus === 'failed') {
            $mensaje = 'El pago no pudo confirmarse. Puedes intentarlo de nuevo o reportarlo manualmente con comprobante.';
            $mensajeTipo = 'error';
        }
    } catch (Throwable $e) {
        $mensaje = 'No fue posible sincronizar el estado de tu pago en linea. Intenta recargar en unos minutos.';
        $mensajeTipo = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO && $schemaError === '') {
    $referenciaPago = trim((string) ($_POST['referencia_pago'] ?? ''));
    $archivo = $_FILES['comprobante_pago'] ?? null;
    $archivoActual = $paymentData['comprobante_pago'];
    $nuevoNombre = $archivoActual;
    $seSubioNuevoArchivo = false;

    $permitidos = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];
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
                if (!in_array($mime, $allowedMimes, true)) {
                    $mimeOk = false;
                }
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
                'UPDATE usuarios
                 SET estatus = ?, referencia_pago = ?, comprobante_pago = ?, pago_reportado_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute(['Pago reportado', $referenciaPago, $nuevoNombre, $userId]);

            if ($archivoActual !== '' && $archivoActual !== $nuevoNombre) {
                $oldPath = app_storage_path('comprobantes_pago', $archivoActual);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $userStatus = 'Pago reportado';
            $_SESSION['user_estatus'] = $userStatus;
            $paymentData['comprobante_pago'] = $nuevoNombre;
            $paymentData['referencia_pago'] = $referenciaPago;
            $paymentData['pago_reportado_at'] = date('Y-m-d H:i:s');
            $mensaje = 'Tu pago fue reportado correctamente y el comprobante quedo adjunto. Cuando el equipo lo valide, tu membresia quedara habilitada con acceso completo.';
            $mensajeTipo = 'success';
        } catch (Throwable $e) {
            if ($seSubioNuevoArchivo) {
                $newPath = app_storage_path('comprobantes_pago', $nuevoNombre);
                if (is_file($newPath)) {
                    @unlink($newPath);
                }
            }
            $mensaje = 'No se pudo registrar la confirmacion de pago en este momento.';
            $mensajeTipo = 'error';
        }
    }
}

if ($pdo instanceof PDO && $schemaError === '') {
    try {
        $latestMercadoPagoPayment = app_get_latest_membership_payment_for_user($pdo, $userId, 'mercadopago');
    } catch (Throwable $e) {
        $latestMercadoPagoPayment = null;
    }
}

$comprobanteUrl = $paymentData['comprobante_pago'] !== ''
    ? uploaded_file_url('comprobantes_pago', $paymentData['comprobante_pago'], true)
    : '';
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
                <div class="rounded-2xl px-5 py-4 text-sm <?php echo $mensajeTipo === 'success' ? 'border border-emerald-200 bg-emerald-50 text-emerald-900' : ($mensajeTipo === 'error' ? 'border border-red-200 bg-red-50 text-red-900' : 'border border-blue-200 bg-blue-50 text-blue-900'); ?>">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_360px]">
                <div class="space-y-6">
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm md:p-8" style="background: linear-gradient(135deg, #ffffff 0%, #f4fbff 58%, #eef8ff 100%);">
                        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                            <div class="max-w-2xl">
                                <div class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold text-blue-600">
                                    <i class="fa-solid fa-bolt"></i>
                                    Pago recomendado
                                </div>
                                <p class="mt-5 text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Pago en linea</p>
                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                    <h1 class="text-3xl font-bold text-slate-900">Mercado Pago</h1>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide <?php echo $mercadoPagoStatusClasses; ?>">
                                        <?php echo htmlspecialchars($mercadoPagoStatusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600">
                                    Paga tu membresia con tarjeta, saldo o medios compatibles desde un checkout seguro. El acceso completo se habilita automaticamente cuando Mercado Pago confirme la operacion.
                                </p>
                            </div>

                            <div class="rounded-[2rem] border border-white bg-white p-5 shadow-sm" style="min-width: 260px;">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Importe</p>
                                <p class="mt-2 text-3xl font-bold text-slate-900">$<?php echo $membresiaImporte; ?></p>
                                <p class="mt-1 text-sm font-semibold text-slate-500"><?php echo htmlspecialchars($membresiaMoneda, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(app_membership_fee_label(), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                                <p class="mt-4 text-sm font-bold text-slate-900">Checkout seguro</p>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500">El cobro se procesa fuera del portal y vuelve con confirmacion del proveedor.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-100 text-green-600">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <p class="mt-4 text-sm font-bold text-slate-900">Activacion automatica</p>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500">Cuando el pago se aprueba, tu membresia cambia a Activo sin captura manual.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </div>
                                <p class="mt-4 text-sm font-bold text-slate-900">Seguimiento del intento</p>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500">Puedes reanudar el checkout si saliste antes de terminarlo.</p>
                            </div>
                        </div>

                        <div class="mt-6 rounded-[2rem] border border-slate-200 bg-white p-5">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Como funciona</p>
                                    <div class="mt-4 space-y-4">
                                        <div class="flex items-start gap-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-600">1</span>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Abres el checkout</p>
                                                <p class="mt-1 text-sm text-slate-500">Te enviamos a Mercado Pago con el importe y la referencia ya configurados.</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-600">2</span>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Pagas con el metodo que prefieras</p>
                                                <p class="mt-1 text-sm text-slate-500">Tarjeta, saldo o el medio disponible en tu cuenta y tu region.</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-600">3</span>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">Se actualiza tu acceso</p>
                                                <p class="mt-1 text-sm text-slate-500">Si el pago se aprueba, el sistema habilita automaticamente la membresia.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-[#EFF6FB] p-5">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Estado del ultimo intento</p>
                                    <p class="mt-3 text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($mercadoPagoStatusLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="mt-2 text-sm leading-7 text-slate-500">
                                        <?php if ($mercadoPagoStatusNormalized === 'approved'): ?>
                                            Tu ultimo intento ya quedo acreditado.
                                        <?php elseif (in_array($mercadoPagoStatusNormalized, ['pending', 'in_process', 'authorized', 'processing', 'checkout_created'], true)): ?>
                                            Tu ultimo intento sigue abierto o en revision.
                                        <?php else: ?>
                                            Aun no hay un pago aprobado para esta membresia.
                                        <?php endif; ?>
                                    </p>

                                    <?php if ($mercadoPagoEnabled): ?>
                                        <form action="<?php echo BASE_URL; ?>/mercadopago_create_payment.php" method="POST" class="mt-5">
                                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-[#009EE3] px-6 py-4 font-bold text-white shadow-lg transition" style="box-shadow: 0 18px 35px -18px rgba(0, 158, 227, 0.85);">
                                                <i class="fa-solid fa-credit-card mr-2"></i>
                                                Pagar con Mercado Pago
                                            </button>
                                        </form>

                                        <?php if ($mercadoPagoCheckoutUrl !== '' && $mercadoPagoStatusNormalized !== 'approved'): ?>
                                            <a href="<?php echo htmlspecialchars($mercadoPagoCheckoutUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-900">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                Reanudar ultimo checkout
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                                            Mercado Pago aun no esta configurado en este ambiente. Define <code>PUBLIC_APP_URL</code>, <code>MERCADOPAGO_ACCESS_TOKEN</code> y <code>MERCADOPAGO_WEBHOOK_SECRET</code> para habilitarlo.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-[#EFF6FB] p-6 shadow-sm md:p-8">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#5282B2] shadow-sm">
                                <i class="fa-solid fa-building-columns text-lg"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Respaldo manual</p>
                                <h2 class="text-2xl font-bold text-slate-900">Transferencia electronica</h2>
                            </div>
                        </div>

                        <p class="mt-5 text-sm leading-relaxed text-slate-600">
                            Si pagaste fuera del checkout, reporta tu pago con una referencia y adjunta tu comprobante. Algunas secciones seguiran restringidas hasta que el equipo valide tu membresia.
                        </p>

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
                                <input
                                    type="text"
                                    name="referencia_pago"
                                    required
                                    maxlength="120"
                                    value="<?php echo htmlspecialchars($paymentData['referencia_pago'], ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Ej. SPEI 548219 / Pago membresia mayo"
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                                >
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
                                                <p id="comprobante_file_name" class="mt-2 truncate text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($comprobanteStatusLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                            <?php if ($comprobanteUrl !== ''): ?>
                                                <a href="<?php echo $comprobanteUrl; ?>" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-blue-700 shadow-sm hover:text-blue-900">
                                                    <i class="fa-solid fa-paperclip"></i>
                                                    Ver comprobante actual
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Formato</p>
                                            <p class="mt-2 text-sm font-semibold text-slate-900">Legible y completo</p>
                                        </div>
                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Referencia</p>
                                            <p class="mt-2 text-sm font-semibold text-slate-900">Debe coincidir con tu folio</p>
                                        </div>
                                        <div class="rounded-2xl bg-white px-4 py-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Revision</p>
                                            <p class="mt-2 text-sm font-semibold text-slate-900">Se valida antes del acceso total</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-[#5282B2] px-6 py-4 font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">
                                Guardar confirmacion manual
                            </button>
                        </form>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Acceso</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900">Acceso disponible</p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">Tu perfil, el dashboard y este modulo seguiran disponibles. Las secciones restringidas se habilitaran cuando tu membresia quede activa.</p>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Estatus</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900"><?php echo htmlspecialchars($userStatus !== '' ? $userStatus : 'Pendiente de pago', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">El acceso completo solo se habilita con estatus Activo.</p>
                    </div>

                    <?php if ($paymentData['pago_reportado_at'] !== ''): ?>
                        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Ultimo reporte manual</p>
                            <p class="mt-3 text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($paymentData['pago_reportado_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-2 text-sm leading-7 text-slate-500">Tu comprobante ya quedo registrado en el sistema.</p>
                        </div>
                    <?php endif; ?>

                    <?php if (is_array($latestMercadoPagoPayment)): ?>
                        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Mercado Pago</p>
                            <p class="mt-3 text-lg font-semibold text-slate-900"><?php echo htmlspecialchars((string) ($latestMercadoPagoPayment['status'] ?? 'Sin estatus'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-2 text-sm leading-7 text-slate-500">Referencia: <?php echo htmlspecialchars((string) ($latestMercadoPagoPayment['external_reference'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-4 font-bold text-slate-700 transition hover:bg-slate-50">
                        Ir al dashboard
                    </a>
                </aside>
            </section>
        </div>
    </main>
    <script>
        (function () {
            var input = document.getElementById('comprobante_pago');
            var fileNameNode = document.getElementById('comprobante_file_name');

            if (!input || !fileNameNode) {
                return;
            }

            input.addEventListener('change', function () {
                if (input.files && input.files.length > 0) {
                    fileNameNode.textContent = input.files[0].name;
                    return;
                }

                fileNameNode.textContent = <?php echo json_encode($comprobanteStatusLabel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            });
        })();
    </script>
</body>
</html>
