<?php
require_once __DIR__ . '/bootstrap.php';
require_once 'role_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userRole = $_SESSION['user_rol'] ?? '';
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
if (isset($pdo)) {
    $dbRole = fetch_user_role($pdo, $userId);
    if ($dbRole !== null) {
        $userRole = $dbRole;
    }
}

if (!is_admin_role($userRole)) {
    header("Location: dashboard.php");
    exit();
}

require_database_connection($pdo ?? null, 'revisar_pagos', 'Revisar Pagos');

ensure_user_payment_columns($pdo);

$mensaje = '';
$mensajeTipo = 'success';
$filtro = trim($_GET['filtro'] ?? 'pendientes');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = (int) ($_POST['user_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');

    if ($targetUserId <= 0 || ($action !== 'aprobar' && $action !== 'revertir')) {
        $mensaje = 'La acción solicitada no es válida.';
        $mensajeTipo = 'error';
    } else {
        $nuevoEstatus = $action === 'aprobar' ? 'Activo' : 'Pendiente de pago';
        $stmt = $pdo->prepare("UPDATE usuarios SET estatus = ? WHERE id = ?");
        $stmt->execute([$nuevoEstatus, $targetUserId]);
        if ($action === 'aprobar') {
            $emailSent = app_send_manual_payment_approved_notification($pdo, $targetUserId);
        }
        $mensaje = $action === 'aprobar'
            ? (($emailSent ?? false)
                ? 'Pago aprobado y cuenta marcada como Activo.'
                : 'Pago aprobado y cuenta marcada como Activo, pero el correo de confirmacion al usuario no pudo enviarse. Revisa la configuracion de correo del servidor.')
            : 'El usuario fue regresado a Pendiente de pago.';
        $mensajeTipo = $action === 'aprobar' && !($emailSent ?? true) ? 'error' : 'success';
    }
}

$where = "WHERE rol = 'Asociado'";
if ($filtro === 'pendientes') {
    $where .= " AND estatus IN ('Pendiente de pago', 'Pago reportado', 'Pago en proceso')";
} elseif ($filtro === 'aprobados') {
    $where .= " AND estatus IN ('Activo', 'Afiliado')";
}

$stats = [
    'pendientes_pago' => 0,
    'pagos_reportados' => 0,
    'pagos_en_proceso' => 0,
    'activos' => 0,
];

$stats['pendientes_pago'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado' AND estatus = 'Pendiente de pago'")->fetchColumn();
$stats['pagos_reportados'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado' AND estatus = 'Pago reportado'")->fetchColumn();
$stats['pagos_en_proceso'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado' AND estatus = 'Pago en proceso'")->fetchColumn();
$stats['activos'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado' AND estatus IN ('Activo', 'Afiliado')")->fetchColumn();

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
                <div class="rounded-2xl bg-white p-5 border border-gray-100 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Pendiente de pago</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900"><?php echo number_format($stats['pendientes_pago']); ?></p>
                </div>
                <div class="rounded-2xl bg-white p-5 border border-gray-100 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Pago reportado</p>
                    <p class="mt-2 text-3xl font-bold text-amber-600"><?php echo number_format($stats['pagos_reportados']); ?></p>
                </div>
                <div class="rounded-2xl bg-white p-5 border border-gray-100 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Pago en proceso</p>
                    <p class="mt-2 text-3xl font-bold text-sky-600"><?php echo number_format($stats['pagos_en_proceso']); ?></p>
                </div>
                <div class="rounded-2xl bg-white p-5 border border-gray-100 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Activos</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-600"><?php echo number_format($stats['activos']); ?></p>
                </div>
            </div>

            <form method="GET" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="text-sm font-semibold text-slate-700">Mostrar</label>
                    <select name="filtro" class="rounded-xl border border-slate-200 px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="pendientes" <?php echo $filtro === 'pendientes' ? 'selected' : ''; ?>>Pendientes y reportados</option>
                        <option value="aprobados" <?php echo $filtro === 'aprobados' ? 'selected' : ''; ?>>Solo activos</option>
                        <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    </select>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#5282B2] px-5 py-2 text-sm font-bold text-white hover:bg-blue-700 transition">
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
                        $estatus = (string) ($usuario['estatus'] ?? '');
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
                        $comprobante = trim((string) ($usuario['comprobante_pago'] ?? ''));
                        $comprobanteUrl = $comprobante !== '' ? uploaded_file_url('comprobantes_pago', $comprobante, true) : '';
                        ?>
                        <article class="rounded-[2rem] border border-gray-100 bg-white p-6 shadow-sm">
                            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h2 class="text-xl font-bold text-slate-900"><?php echo htmlspecialchars((string) ($usuario['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                                        <span class="rounded-full px-3 py-1 text-xs font-bold <?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($estatus, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars((string) ($usuario['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>

                                    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Referencia</p>
                                            <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars((string) ($usuario['referencia_pago'] ?? 'Sin referencia'), ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Pago reportado</p>
                                            <p class="mt-2 text-sm font-semibold text-slate-900"><?php echo htmlspecialchars((string) ($usuario['pago_reportado_at'] ?? 'Aún no'), ENT_QUOTES, 'UTF-8'); ?></p>
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
                                                <a href="<?php echo BASE_URL; ?>/editar_asociado.php?id=<?php echo (int) $usuario['id']; ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">
                                                    Editar
                                                </a>
                                                <?php if (!app_is_membership_active_status($estatus)): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="user_id" value="<?php echo (int) $usuario['id']; ?>">
                                                        <input type="hidden" name="action" value="aprobar">
                                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition">
                                                            Aprobar pago
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($estatus !== 'Pendiente de pago'): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="user_id" value="<?php echo (int) $usuario['id']; ?>">
                                                        <input type="hidden" name="action" value="revertir">
                                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-700 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800 transition">
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
</body>
</html>
