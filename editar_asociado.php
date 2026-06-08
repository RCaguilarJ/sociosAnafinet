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
$isAdmin = is_admin_role($userRole);

if (!$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

require_database_connection($pdo ?? null, 'asociados', 'Editar Asociado');

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$asociado = null;
$mensaje = '';
$mensajeTipo = 'success';
$emailPopupMessage = '';
$emailPopupTitle = 'Correo enviado correctamente';
$emailPopupType = 'success';
$masterAccess = current_user_has_master_access($pdo ?? null, $userId);

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $asociado = $stmt->fetch();
}

if (!$asociado) {
    $mensaje = 'No se encontr&oacute; el usuario solicitado.';
    $mensajeTipo = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $asociado) {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rol = trim($_POST['rol'] ?? '');
    $estatus = trim($_POST['estatus'] ?? '');
    $previousStatus = trim((string)($asociado['estatus'] ?? ''));

    if ($nombre === '' || $email === '' || $rol === '' || $estatus === '') {
        $mensaje = 'Todos los campos son obligatorios.';
        $mensajeTipo = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'El email no es v&aacute;lido.';
        $mensajeTipo = 'error';
    } else {
        // Actualizamos los datos del asociado en la base de datos[cite: 4]
        $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, estatus = ? WHERE id = ?");
        $stmt->execute([$nombre, $email, $rol, $estatus, $editId]);
        
        $activatedByStatusChange = !app_is_membership_active_status($previousStatus) && app_is_membership_active_status($estatus);
        $emailSent = false;

        // Si cambia de inactivo/procesando a un estado activo, mandamos el correo corporativo[cite: 4]
        if ($activatedByStatusChange) {

            // Contenido dinámico del correo usando tus estilos en mail_helpers[cite: 3]
            $introActivacion = "<p>Hola <strong>" . app_mail_html_escape($nombre) . "</strong>,</p>"
                             . "<p>Te informamos que tu comprobante de pago ha sido validado correctamente por nuestro equipo de finanzas.</p>"
                             . "<p>A partir de este momento, tu membresía se encuentra **Activa**. Ya puedes ingresar a todas las salas de discusión y compartir con la comunidad.</p>";
            
            $buttonForo = app_mail_button(BASE_URL . '/foro.php', 'Disfrutar del Foro Fiscal');
            
            $htmlActivacion = app_mail_wrap_layout(
                'Verificación Exitosa',
                '¡Tu pago ha sido aprobado!',
                $introActivacion,
                '', // Sin tabla de resumen
                $buttonForo,
                'Si tienes inconvenientes para iniciar sesión, responde directamente a este correo.',
                'Bienvenido al Foro Fiscal Anafinet'
            );

            // Enviamos el correo forzando que el remitente sea tesoreria@anafinet.mx[cite: 3]
            $emailSent = app_send_html_email(
                $email,
                '¡Pago Verificado! Tu cuenta de acceso al foro está activa',
                $htmlActivacion,
                null,
                [
                    'from_email' => 'noreply@anafinet.mx',
                    'from_name'  => 'Tesorería Anafinet'
                    , 'reply_to'   => 'tesoreria@anafinet.mx'
                ]
            );
        }

        // Controladores visuales del log maestro del dashboard[cite: 4]
        if ($activatedByStatusChange && $emailSent && $masterAccess) {
            $emailPopupMessage = 'Se envio correctamente el correo de confirmacion de acceso al foro a '
                . $email
                . '.';
        }
        if ($activatedByStatusChange && !$emailSent && $masterAccess) {
            $emailPopupTitle = 'No se envio el correo';
            $emailPopupType = 'error';
            $emailPopupMessage = app_mail_last_error() !== ''
                ? app_mail_last_error()
                : 'El sistema no devolvio detalle adicional sobre el fallo del correo.';
        }
        if ($activatedByStatusChange && !$emailSent) {
            $mensaje = 'Cambios guardados correctamente, pero no se pudo enviar el correo de activacion al usuario.';
            $mensajeTipo = 'error';
        } else {
            $mensaje = 'Cambios guardados correctamente.';
            $mensajeTipo = 'success';
        }

        // Recargar los nuevos datos modificados del usuario[cite: 4]
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$editId]);
        $asociado = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Editar Asociado - Anafinet</title>
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
    $activePage = 'asociados';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-6 md:p-10 flex justify-center">
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-8 w-full max-w-xl">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Editar Asociado</h1>
                    <p class="text-gray-500 text-sm">Actualiza los datos del miembro seleccionado.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>/lista_asociados.php" class="text-sm text-gray-500 hover:text-gray-700">Volver</a>
            </div>

            <?php if ($mensaje): ?>
                <div class="mb-4 p-3 rounded-lg text-sm <?php echo $mensajeTipo === 'error' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?>">
                    <?php echo htmlspecialchars_decode($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if ($asociado): ?>
            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="nombre" required value="<?php echo htmlspecialchars((string)($asociado['nombre'] ?? '')); ?>"
                           class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars((string)($asociado['email'] ?? '')); ?>"
                           class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                    <input type="text" name="rol" required value="<?php echo htmlspecialchars((string)($asociado['rol'] ?? '')); ?>"
                           class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estatus</label>
                    <?php $estatusActual = (string)($asociado['estatus'] ?? ''); ?>
                    <select name="estatus" required class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                        <?php
                        $estatusOpciones = [
                            'Pendiente de pago',
                            'Pago reportado',
                            'Pago en proceso',
                            'Activo',
                            'Afiliado',
                            'Aprobado',
                            'Confirmado',
                            'Pagado',
                            'Inactivo',
                            'Suspendido',
                        ];
                        foreach ($estatusOpciones as $opcion):
                        ?>
                            <option value="<?php echo htmlspecialchars($opcion, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $estatusActual === $opcion ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($opcion, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($estatusActual !== '' && !in_array($estatusActual, $estatusOpciones, true)): ?>
                            <option value="<?php echo htmlspecialchars($estatusActual, ENT_QUOTES, 'UTF-8'); ?>" selected>
                                <?php echo htmlspecialchars($estatusActual, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold shadow-lg hover:bg-blue-700 transition">
                    Guardar Cambios
                </button>
            </form>
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
</body>
</html>
