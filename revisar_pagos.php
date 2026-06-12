<?php
require_once __DIR__ . '/bootstrap.php';
require_once 'role_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userRole = $_SESSION['user_rol'] ?? '';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if (isset($pdo)) {
    $dbRole = fetch_user_role($pdo, $userId);
    if ($dbRole !== null) {
        $userRole = $dbRole;
    }
}
$masterAccess = current_user_has_master_access($pdo ?? null, $userId);

if (!is_admin_role($userRole) && !$masterAccess) {
    header("Location: dashboard.php");
    exit();
}

require_database_connection($pdo ?? null, 'revisar_pagos', 'Revisar Pagos');

ensure_user_payment_columns($pdo);
app_ensure_payment_settings_schema($pdo);

$mensaje = '';
$mensajeTipo = 'success';
$filtro = trim($_GET['filtro'] ?? 'pendientes');
$emailPopupMessage = '';
$emailPopupTitle = 'Correo enviado correctamente';
$emailPopupType = 'success';
$membershipFeeAmount = app_membership_fee_amount();
$membershipFeeCurrency = app_membership_fee_currency();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'actualizar_monto') {
        $amountInput = trim((string)($_POST['membership_fee_amount'] ?? ''));
        $normalizedAmount = str_replace(',', '.', $amountInput);

        if ($normalizedAmount === '' || !is_numeric($normalizedAmount) || (float)$normalizedAmount <= 0) {
            $mensaje = 'Ingresa un monto valido mayor a cero.';
            $mensajeTipo = 'error';
        } else {
            $storedAmount = number_format((float)$normalizedAmount, 2, '.', '');
            app_set_payment_setting($pdo, 'membership_fee_amount', $storedAmount);
            $membershipFeeAmount = app_membership_fee_amount();
            $mensaje = 'El monto de afiliacion se actualizo correctamente.';
            $mensajeTipo = 'success';
        }
    } else {
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        if ($targetUserId <= 0 || ($action !== 'aprobar' && $action !== 'revertir')) {
            $mensaje = 'La accion solicitada no es valida.';
            $mensajeTipo = 'error';
        } else {
            $statusStmt = $pdo->prepare("SELECT estatus FROM usuarios WHERE id = ? LIMIT 1");
            $statusStmt->execute([$targetUserId]);
            $previousStatus = (string)($statusStmt->fetchColumn() ?: '');

            $userDetailStmt = $pdo->prepare("SELECT email FROM usuarios WHERE id = ? LIMIT 1");
            $userDetailStmt->execute([$targetUserId]);
            $targetUserEmail = trim((string)($userDetailStmt->fetchColumn() ?: ''));

            $nuevoEstatus = $action === 'aprobar' ? 'Activo' : 'Pendiente de pago';
            $stmt = $pdo->prepare("UPDATE usuarios SET estatus = ? WHERE id = ?");
            $stmt->execute([$nuevoEstatus, $targetUserId]);

            if ($action === 'aprobar') {
                app_apply_membership_cycle($pdo, $targetUserId, date('Y-m-d H:i:s'));
                $emailSent = app_send_manual_payment_activation_notification_if_needed($pdo, $targetUserId, $previousStatus, 'Activo');

                if (($emailSent ?? false) && $masterAccess) {
                    $emailPopupMessage = 'Se envio correctamente el correo de confirmacion de acceso al foro a '
                        . ($targetUserEmail !== '' ? $targetUserEmail : 'este usuario')
                        . '.';
                }

                if (!($emailSent ?? false) && $masterAccess) {
                    $emailPopupTitle = 'No se envio el correo';
                    $emailPopupType = 'error';
                    $emailPopupMessage = app_mail_last_error() !== ''
                        ? app_mail_last_error()
                        : 'El sistema no devolvio detalle adicional sobre el fallo del correo.';
                }
            }

            $mensaje = $action === 'aprobar'
                ? (($emailSent ?? false)
                    ? 'Pago aprobado y cuenta marcada como Activo.'
                    : 'Pago aprobado y cuenta marcada como Activo, pero el correo de confirmacion al usuario no pudo enviarse. Revisa la configuracion de correo del servidor.')
                : 'El usuario fue regresado a Pendiente de pago.';
            $mensajeTipo = $action === 'aprobar' && !($emailSent ?? true) ? 'error' : 'success';
        }
    }
}

$where = "WHERE rol = 'Asociado'";
if ($filtro === 'pendientes') {
    $where .= " AND estatus IN ('Pendiente de pago', 'Pago reportado', 'Pago en proceso')";
} elseif ($filtro === 'aprobados') {
    $where .= " AND estatus IN ('Activo', 'Afiliado', 'Aprobado', 'Confirmado', 'Pagado')";
}

$stats = [
    'pendientes_pago' => 0,
    'pagos_reportados' => 0,
    'pagos_en_proceso' => 0,
    'activos' => 0,
];

$stats['pendientes_pago'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado' AND estatus = 'Pendiente de pago'")->fetchColumn();
$stats['pagos_reportados'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado' AND estatus = 'Pago reportado'")->fetchColumn();
$stats['pagos_en_proceso'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado' AND estatus = 'Pago en proceso'")->fetchColumn();
$stats['activos'] = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado' AND estatus IN ('Activo', 'Afiliado', 'Aprobado', 'Confirmado', 'Pagado')")->fetchColumn();

$stmt = $pdo->query("
    SELECT id, nombre, email, estatus, referencia_pago, pago_reportado_at, comprobante_pago, creado_at
    FROM usuarios
    $where
    ORDER BY
        CASE estatus
            WHEN 'Pago reportado' THEN 1
            WHEN 'Pago en proceso' THEN 2
            WHEN 'Pendiente de pago' THEN 3
            WHEN 'Activo' THEN 4
            WHEN 'Afiliado' THEN 4
            WHEN 'Aprobado' THEN 4
            WHEN 'Confirmado' THEN 4
            WHEN 'Pagado' THEN 4
            ELSE 4
        END,
        pago_reportado_at DESC,
        creado_at DESC
");
$usuarios = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Revisar Pagos - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php if ($emailPopupMessage !== ''): ?>
        <div id="email-status-popup" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
            <div class="w-full max-w-lg rounded-[2rem] bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] <?php echo $emailPopupType === 'error' ? 'text-red-600' : 'text-emerald-600'; ?>">Master log</p>
                        <h2 class="mt-2 text-2xl font-bold text-slate-900"><?php echo htmlspecialchars($emailPopupTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                    </div>
                    <button type="button" id="email-status-popup-close" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Cerrar aviso">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="mt-5 rounded-2xl px-4 py-4 text-sm font-medium <?php echo $emailPopupType === 'error' ? 'border border-red-100 bg-red-50 text-red-900' : 'border border-emerald-100 bg-emerald-50 text-emerald-900'; ?>">
                    <?php echo htmlspecialchars($emailPopupMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" id="email-status-popup-accept" class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-bold text-white transition <?php echo $emailPopupType === 'error' ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'; ?>">
                        Entendido
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $activePage = 'revisar_pagos';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-6 md:p-8">
        <div class="mx-auto w-full max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-gray-800">Revisar Pagos</h1>
                <p class="text-sm text-gray-500">Valida comprobantes de asociados y actualiza su estatus administrativo.</p>
            </header>

            <?php if ($mensaje !== ''): ?>
                <div class="rounded-2xl px-5 py-4 text-sm <?php echo $mensajeTipo === 'error' ? 'border border-red-200 bg-red-50 text-red-900' : 'border border-emerald-200 bg-emerald-50 text-emerald-900'; ?>">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Pendiente de pago</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900"><?php echo number_format($stats['pendientes_pago']); ?></p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Pago reportado</p>
                    <p class="mt-2 text-3xl font-bold text-amber-600"><?php echo number_format($stats['pagos_reportados']); ?></p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Pago en proceso</p>
                    <p class="mt-2 text-3xl font-bold text-sky-600"><?php echo number_format($stats['pagos_en_proceso']); ?></p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Activos</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-600"><?php echo number_format($stats['activos']); ?></p>
                </div>
            </div>

            <section class="rounded-[2rem] border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Monto de afiliacion</p>
                        <h2 class="mt-2 text-3xl font-black text-slate-900">$<?php echo number_format($membershipFeeAmount, 2, '.', ','); ?> <?php echo htmlspecialchars($membershipFeeCurrency, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="mt-2 text-sm text-slate-500">Este monto se usa en la solicitud de afiliacion, la confirmacion de pago y el checkout de la pasarela.</p>
                    </div>

                    <form method="POST" class="grid w-full max-w-2xl gap-3">
                        <input type="hidden" name="action" value="actualizar_monto">
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                            <div>
                                <label for="membership_fee_amount" class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Editar monto</label>
                                <input
                                    id="membership_fee_amount"
                                    type="number"
                                    name="membership_fee_amount"
                                    min="0.01"
                                    step="0.01"
                                    required
                                    value="<?php echo htmlspecialchars(number_format($membershipFeeAmount, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-base font-semibold text-slate-900 outline-none transition focus:border-blue-400 focus:bg-white focus:ring-2 focus:ring-blue-100"
                                >
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-[#5282B2] px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-700">
                                Guardar monto
                            </button>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" data-set-amount="10.00" class="inline-flex items-center justify-center rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-800 transition hover:bg-amber-100">
                                Prueba Clip: $10.00
                            </button>
                            <button type="button" data-set-amount="1000.00" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-100">
                                Monto real: $1,000.00
                            </button>
                        </div>

                        <p class="text-xs text-slate-500">
                            Usa los presets para cambiar rapido entre el monto de prueba y el monto anual real.
                        </p>
                    </form>
                </div>
            </section>

            <form method="GET" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="text-sm font-semibold text-slate-700">Mostrar</label>
                    <select name="filtro" class="rounded-xl border border-slate-200 px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="pendientes" <?php echo $filtro === 'pendientes' ? 'selected' : ''; ?>>Pendientes y reportados</option>
                        <option value="aprobados" <?php echo $filtro === 'aprobados' ? 'selected' : ''; ?>>Solo activos</option>
                        <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    </select>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#5282B2] px-5 py-2 text-sm font-bold text-white transition hover:bg-blue-700">
                        Aplicar filtro
                    </button>
                </div>
            </form>

            <?php if (empty($usuarios)): ?>
                <div class="rounded-3xl border border-gray-100 bg-white p-8 text-sm text-gray-400 shadow-sm">
                    No hay registros para el filtro seleccionado.
                </div>
            <?php else: ?>
                <div class="grid gap-5">
                    <?php foreach ($usuarios as $usuario): ?>
                        <?php
                        $estatus = (string)($usuario['estatus'] ?? '');
                        $badgeClass = 'bg-slate-100 text-slate-700';
                        if ($estatus === 'Pago reportado') {
                            $badgeClass = 'bg-amber-100 text-amber-800';
                        } elseif ($estatus === 'Pago en proceso') {
                            $badgeClass = 'bg-sky-100 text-sky-800';
                        } elseif (app_is_membership_active_status($estatus)) {
                            $badgeClass = 'bg-emerald-100 text-emerald-800';
                        } elseif ($estatus === 'Pendiente de pago') {
                            $badgeClass = 'bg-blue-100 text-blue-800';
                        }
                        $comprobante = trim((string)($usuario['comprobante_pago'] ?? ''));
                        $comprobanteUrl = $comprobante !== '' ? uploaded_file_url('comprobantes_pago', $comprobante, true) : '';
                        ?>
                        <article class="rounded-[2rem] border border-gray-100 bg-white p-6 shadow-sm">
                            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h2 class="text-xl font-bold text-slate-900"><?php echo htmlspecialchars((string)($usuario['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                                        <span class="rounded-full px-3 py-1 text-xs font-bold <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($estatus, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars((string)($usuario['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>

                                    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Referencia</p>
                                            <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars((string)($usuario['referencia_pago'] ?? 'Sin referencia'), ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Pago reportado</p>
                                            <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars((string)($usuario['pago_reportado_at'] ?? 'Aun no'), ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Comprobante</p>
                                            <?php if ($comprobanteUrl !== ''): ?>
                                                <a href="<?php echo $comprobanteUrl; ?>" class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:text-blue-900">
                                                    <i class="fa-solid fa-paperclip"></i>
                                                    Ver archivo
                                                </a>
                                            <?php else: ?>
                                                <p class="mt-2 text-sm font-semibold text-slate-500">Sin archivo</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Acciones</p>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <a href="<?php echo BASE_URL; ?>/editar_asociado.php?id=<?php echo (int)$usuario['id']; ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">
                                                    Editar
                                                </a>
                                                <?php if (!app_is_membership_active_status($estatus)): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="user_id" value="<?php echo (int)$usuario['id']; ?>">
                                                        <input type="hidden" name="action" value="aprobar">
                                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-700">
                                                            Aprobar pago
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($estatus !== 'Pendiente de pago'): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="user_id" value="<?php echo (int)$usuario['id']; ?>">
                                                        <input type="hidden" name="action" value="revertir">
                                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-700 px-3 py-2 text-xs font-bold text-white transition hover:bg-slate-800">
                                                            Regresar a pendiente
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php if ($emailPopupMessage !== ''): ?>
    <script>
        (function () {
            const popup = document.getElementById('email-status-popup');
            if (!popup) {
                return;
            }

            const closePopup = () => popup.classList.add('hidden');
            document.getElementById('email-status-popup-close')?.addEventListener('click', closePopup);
            document.getElementById('email-status-popup-accept')?.addEventListener('click', closePopup);
            popup.addEventListener('click', (event) => {
                if (event.target === popup) {
                    closePopup();
                }
            });
        }());
    </script>
    <?php endif; ?>
    <script>
        (function () {
            const amountInput = document.getElementById('membership_fee_amount');
            if (!amountInput) {
                return;
            }

            document.querySelectorAll('[data-set-amount]').forEach((button) => {
                button.addEventListener('click', () => {
                    amountInput.value = button.getAttribute('data-set-amount') || amountInput.value;
                    amountInput.focus();
                    amountInput.select();
                });
            });
        }());
    </script>
</body>
</html>
