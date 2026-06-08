<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/role_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_database_connection($pdo ?? null, 'notificaciones', 'Notificaciones');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: index.php');
    exit();
}

if (isset($_GET['marcar']) && $_GET['marcar'] === 'todas') {
    app_mark_all_notifications_read($pdo, $userId);
    header('Location: ' . base_url('notificaciones.php'));
    exit();
}

if (isset($_GET['leer'])) {
    app_mark_notification_read($pdo, $userId, (int)$_GET['leer']);
    header('Location: ' . base_url('notificaciones.php'));
    exit();
}

$notifications = app_get_all_notifications($pdo, $userId, 120);
$unreadCount = app_get_unread_notifications_count($pdo, $userId);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php app_render_favicon_tags(); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Notificaciones - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
<?php
$activePage = 'notificaciones';
require __DIR__ . '/menu.php';
?>
    <main class="md:ml-64 p-8">
        <header class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Notificaciones</h1>
                <p class="text-sm text-slate-500">Avisos de renovacion y actividad reciente del foro.</p>
            </div>
            <?php if ($unreadCount > 0): ?>
                <a href="<?php echo htmlspecialchars(base_url('notificaciones.php?marcar=todas'), ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">
                    Marcar todas como leidas
                </a>
            <?php endif; ?>
        </header>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Pendientes</p>
                <p class="mt-3 text-4xl font-black text-slate-900"><?php echo number_format($unreadCount); ?></p>
            </section>
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Total</p>
                <p class="mt-3 text-4xl font-black text-slate-900"><?php echo number_format(count($notifications)); ?></p>
            </section>
        </div>

        <div class="space-y-4">
            <?php if (empty($notifications)): ?>
                <section class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm text-sm text-slate-500">
                    No tienes notificaciones por ahora.
                </section>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $url = trim((string)($notification['url'] ?? ''));
                    $isRead = !empty($notification['is_read']);
                    $iconClass = app_notification_icon_class((string)($notification['type'] ?? ''));
                    ?>
                    <section class="rounded-3xl border <?php echo $isRead ? 'border-slate-200 bg-white' : 'border-blue-200 bg-blue-50/50'; ?> p-6 shadow-sm">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div class="flex gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-sm">
                                    <i class="<?php echo htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <h2 class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars((string)($notification['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                                        <?php if (!$isRead): ?>
                                            <span class="rounded-full bg-blue-600 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-white">Nueva</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-2 text-sm leading-7 text-slate-600"><?php echo htmlspecialchars((string)($notification['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="mt-3 text-xs text-slate-400"><?php echo htmlspecialchars((string)($notification['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <?php if ($url !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center justify-center rounded-xl bg-[#5282B2] px-4 py-3 text-sm font-bold text-white hover:bg-blue-700 transition">
                                        Ver detalle
                                    </a>
                                <?php endif; ?>
                                <?php if (!$isRead): ?>
                                    <a href="<?php echo htmlspecialchars(base_url('notificaciones.php?leer=' . (int)$notification['id']), ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">
                                        Marcar como leida
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
