<?php
require_once __DIR__ . '/bootstrap.php';
require_once 'role_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$userStatus = $_SESSION['user_estatus'] ?? '';
$mensaje = '';
$mensajeTipo = 'info';
$registroNuevo = isset($_GET['registro']) && $_GET['registro'] === '1';
$paymentData = [
    'comprobante_pago' => '',
    'referencia_pago' => '',
    'pago_reportado_at' => '',
];
$schemaError = '';

if ($pdo instanceof PDO) {
    try {
        ensure_user_payment_columns($pdo);

        $stmt = $pdo->prepare("SELECT estatus, comprobante_pago, referencia_pago, pago_reportado_at FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (is_array($row)) {
            $userStatus = (string) ($row['estatus'] ?? $userStatus);
            $paymentData['comprobante_pago'] = (string) ($row['comprobante_pago'] ?? '');
            $paymentData['referencia_pago'] = (string) ($row['referencia_pago'] ?? '');
            $paymentData['pago_reportado_at'] = (string) ($row['pago_reportado_at'] ?? '');
            $_SESSION['user_estatus'] = $userStatus;
        }
    } catch (Throwable $e) {
        $schemaError = 'No fue posible preparar el módulo de confirmación de pago en este momento.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO && $schemaError === '') {
    $referenciaPago = trim($_POST['referencia_pago'] ?? '');
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
        $mensaje = 'Ocurrió un error al subir el comprobante.';
        $mensajeTipo = 'error';
    } else {
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $permitidos, true)) {
            $mensaje = 'Solo se permiten archivos PDF, JPG, PNG o WebP.';
            $mensajeTipo = 'error';
        } elseif (($archivo['size'] ?? 0) > $maxSize) {
            $mensaje = 'El comprobante excede el tamaño permitido de 5MB.';
            $mensajeTipo = 'error';
        } else {
            $mimeOk = true;
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($archivo['tmp_name']);
                if (!in_array($mime, $allowedMimes, true)) {
                    $mimeOk = false;
                }
            }

            if (!$mimeOk) {
                $mensaje = 'El archivo cargado no tiene un formato válido.';
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

                    if (move_uploaded_file($archivo['tmp_name'], $destino)) {
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
            $stmt = $pdo->prepare("UPDATE usuarios SET estatus = ?, referencia_pago = ?, comprobante_pago = ?, pago_reportado_at = NOW() WHERE id = ?");
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
            $mensaje = 'Tu pago fue reportado correctamente y el comprobante quedó adjunto. Ya tienes acceso al portal mientras nuestro equipo lo valida.';
            $mensajeTipo = 'success';
        } catch (Throwable $e) {
            if ($seSubioNuevoArchivo) {
                $newPath = app_storage_path('comprobantes_pago', $nuevoNombre);
                if (is_file($newPath)) {
                    @unlink($newPath);
                }
            }
            $mensaje = 'No se pudo registrar la confirmación de pago en este momento.';
            $mensajeTipo = 'error';
        }
    }
}

$comprobanteUrl = $paymentData['comprobante_pago'] !== '' ? uploaded_file_url('comprobantes_pago', $paymentData['comprobante_pago'], true) : '';
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
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <?php if ($registroNuevo): ?>
                <div class="rounded-3xl border border-blue-200 bg-blue-50 px-6 py-5 text-blue-900">
                    <p class="text-sm font-bold uppercase tracking-wide">Registro completado</p>
                    <h1 class="mt-2 text-2xl font-bold">Su solicitud como afiliado está siendo en proceso.</h1>
                    <p class="mt-2 text-sm text-blue-800">Como siguiente paso, confirma tu pago con tu comprobante para cerrar el proceso administrativo y así desbloquee todas las funciones que tenemos por ofrecerte.</p>
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

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_360px]">
                <div class="rounded-[2rem] border border-slate-200 bg-[#EFF6FB] p-6 shadow-sm md:p-8">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[#5282B2] shadow-sm">
                            <i class="fa-solid fa-building-columns text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Confirmación de pago</p>
                            <h2 class="text-2xl font-bold text-slate-900">Transferencia electrónica</h2>
                        </div>
                    </div>

                    <p class="mt-5 text-slate-600 leading-relaxed">
                        Ya puedes usar el portal. Para terminar el proceso, reporta tu pago con una referencia y adjunta tu comprobante.
                    </p>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-white p-5 border border-slate-200">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Banco</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">Banamex</p>
                        </div>
                        <div class="rounded-2xl bg-white p-5 border border-slate-200">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Cuenta</p>
                            <p class="mt-2 text-lg font-semibold text-slate-900">1234567890</p>
                        </div>
                        <div class="rounded-2xl bg-white p-5 border border-slate-200 sm:col-span-2">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">CLABE</p>
                            <p class="mt-2 break-all text-lg font-semibold text-slate-900">002180012345678901</p>
                        </div>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Referencia o folio de pago</label>
                            <input type="text" name="referencia_pago" required maxlength="120"
                                   value="<?php echo htmlspecialchars($paymentData['referencia_pago'], ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="Ej. SPEI 548219 / Pago membresía mayo"
                                   class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Comprobante de pago</label>
                            <div class="rounded-2xl border-2 border-dashed border-slate-300 bg-white px-5 py-6">
                                <input id="comprobante_pago" type="file" name="comprobante_pago" accept=".pdf,.jpg,.jpeg,.png,.webp" class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-[#5282B2] file:px-4 file:py-2 file:font-semibold file:text-white hover:file:bg-blue-700">
                                <p class="mt-3 text-xs text-slate-500">Formatos permitidos: PDF, JPG, PNG o WebP. Tamaño máximo: 5MB.</p>
                                <?php if ($comprobanteUrl !== ''): ?>
                                    <a href="<?php echo $comprobanteUrl; ?>" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-900">
                                        <i class="fa-solid fa-paperclip"></i>
                                        Ver comprobante cargado
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-[#5282B2] px-6 py-4 font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700">
                            Guardar confirmación de pago
                        </button>
                    </form>
                </div>

                <aside class="space-y-4">
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Acceso</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900">Funciones desbloqueadas</p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">Puedes entrar al dashboard, biblioteca, foro, perfil y demás secciones desde este momento.</p>
                    </div>

                    <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Estatus</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900"><?php echo htmlspecialchars($userStatus !== '' ? $userStatus : 'Pendiente de pago', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">Este estatus solo sirve para seguimiento administrativo; no limita tu uso del portal.</p>
                    </div>

                    <?php if ($paymentData['pago_reportado_at'] !== ''): ?>
                        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Último reporte</p>
                            <p class="mt-3 text-lg font-semibold text-slate-900"><?php echo htmlspecialchars($paymentData['pago_reportado_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-2 text-sm leading-7 text-slate-500">Tu comprobante ya quedó registrado en el sistema.</p>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 py-4 font-bold text-slate-700 transition hover:bg-slate-50">
                        Ir al dashboard
                    </a>
                </aside>
            </section>
        </div>
    </main>
</body>
</html>
