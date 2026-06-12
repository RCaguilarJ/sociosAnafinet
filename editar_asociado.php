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
$masterAccess = current_user_has_master_access($pdo ?? null, $userId);

if (!$isAdmin && !$masterAccess) {
    header("Location: dashboard.php");
    exit();
}

require_database_connection($pdo ?? null, 'asociados', 'Editar Asociado');

if (!function_exists('app_delete_user_completely')) {
    function app_delete_user_completely(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('SELECT id, nombre, email, rol, foto_perfil, comprobante_pago FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!is_array($user)) {
            throw new RuntimeException('No se encontro el usuario solicitado.');
        }

        $photoFile = trim((string)($user['foto_perfil'] ?? ''));
        $proofFile = trim((string)($user['comprobante_pago'] ?? ''));
        $pathsToDelete = [];

        if ($photoFile !== '') {
            $photoPath = app_resolve_storage_path('perfiles', $photoFile);
            if ($photoPath !== null) {
                $pathsToDelete[] = $photoPath;
            }
        }

        if ($proofFile !== '') {
            $proofPath = app_resolve_storage_path('comprobantes_pago', $proofFile);
            if ($proofPath !== null) {
                $pathsToDelete[] = $proofPath;
            }
        }

        $pdo->beginTransaction();
        try {
            app_ensure_membership_payment_schema($pdo);
            ensure_session_table($pdo);
            if (function_exists('app_ensure_payment_settings_schema')) {
                app_ensure_payment_settings_schema($pdo);
            }

            $deleteStatements = [
                'DELETE FROM app_notifications WHERE user_id = ?',
                'DELETE FROM actividad_usuario WHERE usuario_id = ?',
                'DELETE FROM foro_likes WHERE usuario_id = ?',
                'DELETE FROM foro_respuestas WHERE usuario_id = ?',
                'DELETE FROM foro_likes WHERE tema_id IN (SELECT id FROM foro_temas WHERE usuario_id = ?)',
                'DELETE FROM foro_respuestas WHERE tema_id IN (SELECT id FROM foro_temas WHERE usuario_id = ?)',
                'DELETE FROM foro_temas WHERE usuario_id = ?',
                'DELETE FROM pagos_membresia WHERE user_id = ?',
            ];

            foreach ($deleteStatements as $sql) {
                try {
                    $deleteStmt = $pdo->prepare($sql);
                    $deleteStmt->execute([$userId]);
                } catch (Throwable $e) {
                    // Some legacy installations may not have every related table yet.
                }
            }

            $deleteUser = $pdo->prepare('DELETE FROM usuarios WHERE id = ? LIMIT 1');
            $deleteUser->execute([$userId]);

            if ($deleteUser->rowCount() !== 1) {
                throw new RuntimeException('No fue posible eliminar el asociado.');
            }

            $verifyStmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE id = ? LIMIT 1');
            $verifyStmt->execute([$userId]);
            if ((bool)$verifyStmt->fetchColumn()) {
                throw new RuntimeException('El registro sigue presente despues del borrado.');
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        foreach ($pathsToDelete as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return [
            'id' => (int)$user['id'],
            'nombre' => (string)($user['nombre'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'rol' => (string)($user['rol'] ?? ''),
        ];
    }
}

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$asociado = null;
$mensaje = '';
$mensajeTipo = 'success';
$emailPopupMessage = '';
$emailPopupTitle = 'Correo enviado correctamente';
$emailPopupType = 'success';
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
    $action = trim((string)($_POST['action'] ?? 'update'));

    if ($action === 'delete') {
        $targetRole = trim((string)($asociado['rol'] ?? ''));
        $targetStatus = trim((string)($asociado['estatus'] ?? ''));
        if ($editId === (int)$userId) {
            $mensaje = 'No puedes eliminar tu propia cuenta desde esta pantalla.';
            $mensajeTipo = 'error';
        } elseif (is_admin_role($targetRole)) {
            $mensaje = 'Desde esta pantalla solo se permite eliminar asociados.';
            $mensajeTipo = 'error';
        } elseif ($targetRole !== '' || strcasecmp($targetStatus, 'Suspendido') !== 0) {
            $mensaje = 'Para eliminar al afiliado primero deja el rol vacio y cambia el estatus a Suspendido.';
            $mensajeTipo = 'error';
        } else {
            try {
                $deletedUser = app_delete_user_completely($pdo, $editId);
                $_SESSION['asociados_flash_message'] = 'El asociado ' . $deletedUser['nombre'] . ' fue eliminado por completo. El correo ' . $deletedUser['email'] . ' ya puede volver a registrarse.';
                $_SESSION['asociados_flash_type'] = 'success';
                header('Location: ' . BASE_URL . '/lista_asociados.php');
                exit();
            } catch (Throwable $e) {
                $mensaje = 'No fue posible eliminar al asociado por completo.';
                if (app_is_local_request() || app_session_debug_enabled()) {
                    $mensaje .= ' Detalle: ' . $e->getMessage();
                }
                $mensajeTipo = 'error';
            }
        }
    } else {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rol = trim($_POST['rol'] ?? '');
    $estatus = trim($_POST['estatus'] ?? '');
    $previousStatus = trim((string)($asociado['estatus'] ?? ''));

    if ($nombre === '' || $email === '' || $estatus === '') {
        $mensaje = 'Todos los campos son obligatorios.';
        $mensajeTipo = 'error';
    } elseif (!app_is_valid_email($email)) {
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
                <input type="hidden" name="action" value="update">
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
                    <input type="text" name="rol" value="<?php echo htmlspecialchars((string)($asociado['rol'] ?? '')); ?>"
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
            <div class="mt-8 border-t border-gray-100 pt-6">
                <?php
                $deleteRoleValue = trim((string)($asociado['rol'] ?? ''));
                $deleteStatusValue = trim((string)($asociado['estatus'] ?? ''));
                $canDeleteAffiliate = $editId !== (int)$userId
                    && !is_admin_role($deleteRoleValue)
                    && $deleteRoleValue === ''
                    && strcasecmp($deleteStatusValue, 'Suspendido') === 0;
                ?>
                <div class="overflow-hidden rounded-[1.75rem] border border-red-200 bg-gradient-to-br from-red-50 via-rose-50 to-white shadow-sm">
                    <div class="border-b border-red-100 bg-red-100/70 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-red-600 text-white shadow-lg shadow-red-200">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </span>
                            <div>
                                <h2 class="text-sm font-black uppercase tracking-[0.2em] text-red-800">Zona delicada</h2>
                                <p class="mt-1 text-sm font-semibold text-red-900">Esta accion elimina al asociado de forma permanente.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 px-5 py-5">
                        <div class="rounded-2xl border border-red-100 bg-white/90 p-4">
                            <p class="text-sm leading-7 text-slate-800">
                                Se borrara su registro, historial de pagos, actividad vinculada y archivos asociados. Despues podra volver a solicitar su afiliacion con el mismo correo.
                            </p>
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm font-medium leading-6 text-amber-900">
                            Primero borra el rol del usuario, cambia el estatus a <strong>Suspendido</strong> y guarda los cambios. Cuando ambos requisitos se cumplan, se activara el boton rojo para eliminar afiliado.
                        </div>

                        <form method="POST" onsubmit="return confirm('Se eliminara por completo este asociado y su correo quedara libre para un nuevo registro. ¿Deseas continuar?');" class="shrink-0">
                            <input type="hidden" name="action" value="delete">
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-2xl px-5 py-4 text-sm font-bold text-white shadow-lg transition <?php echo $canDeleteAffiliate ? 'bg-red-600 shadow-red-200 hover:bg-red-700' : 'bg-red-300 shadow-red-100 cursor-not-allowed'; ?>"
                                <?php echo $canDeleteAffiliate ? '' : 'disabled'; ?>
                            >
                                Eliminar afiliado
                            </button>
                        </form>
                        <?php if ($editId === (int)$userId): ?>
                            <p class="text-xs font-semibold text-red-700">No puedes eliminar tu propia cuenta desde esta pantalla.</p>
                        <?php elseif (is_admin_role((string)($asociado['rol'] ?? ''))): ?>
                            <p class="text-xs font-semibold text-red-700">La eliminacion completa desde esta vista esta reservada para usuarios con rol de asociado.</p>
                        <?php elseif (!$canDeleteAffiliate): ?>
                            <p class="text-xs font-semibold text-red-700">El boton se habilita solo cuando el rol esta vacio y el estatus esta en Suspendido.</p>
                        <?php endif; ?>
                    </div>
                </div>
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
</body>
</html>
