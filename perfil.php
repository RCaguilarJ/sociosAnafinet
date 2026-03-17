<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$tab = $_GET['tab'] ?? 'informacion';

$uploadMsg = '';
$uploadType = 'success';
$profileMsg = '';
$profileType = 'success';
$refreshUser = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload_photo') {
        $archivo = $_FILES['foto_perfil'] ?? null;
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
        $max_size = 3 * 1024 * 1024;
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $minSize = 300;
        $targetSize = 400;

        if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $uploadMsg = 'Selecciona una imagen para subir.';
            $uploadType = 'error';
        } elseif (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $uploadMsg = 'Ocurri&oacute; un error al subir la imagen.';
            $uploadType = 'error';
        } else {
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $permitidos, true)) {
                $uploadMsg = 'Solo se permiten im&aacute;genes JPG, PNG o WebP.';
                $uploadType = 'error';
            } elseif ($archivo['size'] > $max_size) {
                $uploadMsg = 'La imagen excede el tama&ntilde;o permitido (3MB).';
                $uploadType = 'error';
            } else {
                $mimeOk = true;
                if (class_exists('finfo')) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($archivo['tmp_name']);
                    if (!in_array($mime, $allowedMimes, true)) {
                        $mimeOk = false;
                    }
                }

                $imageInfo = @getimagesize($archivo['tmp_name']);
                if (!$mimeOk || !$imageInfo) {
                    $uploadMsg = 'El archivo no es una imagen v&aacute;lida.';
                    $uploadType = 'error';
                } else {
                    $width = (int)($imageInfo[0] ?? 0);
                    $height = (int)($imageInfo[1] ?? 0);
                    if ($width < $minSize || $height < $minSize) {
                        $uploadMsg = 'La imagen debe tener al menos ' . $minSize . 'x' . $minSize . ' px.';
                        $uploadType = 'error';
                    } elseif (!function_exists('imagecreatetruecolor')) {
                        $uploadMsg = 'El servidor no tiene soporte para procesar im&aacute;genes.';
                        $uploadType = 'error';
                    } else {
                        $mime = $imageInfo['mime'] ?? '';
                        $srcImg = null;
                        $outExt = 'jpg';
                        $saveFn = null;

                        if ($mime === 'image/jpeg') {
                            $srcImg = @imagecreatefromjpeg($archivo['tmp_name']);
                            $outExt = 'jpg';
                            $saveFn = function ($img, $path) { return imagejpeg($img, $path, 85); };
                        } elseif ($mime === 'image/png') {
                            $srcImg = @imagecreatefrompng($archivo['tmp_name']);
                            $outExt = 'png';
                            $saveFn = function ($img, $path) { return imagepng($img, $path, 6); };
                        } elseif ($mime === 'image/webp') {
                            if (function_exists('imagecreatefromwebp')) {
                                $srcImg = @imagecreatefromwebp($archivo['tmp_name']);
                                $outExt = 'jpg';
                                $saveFn = function ($img, $path) { return imagejpeg($img, $path, 85); };
                            }
                        }

                        if (!$srcImg || !$saveFn) {
                            $uploadMsg = 'No se pudo procesar la imagen seleccionada.';
                            $uploadType = 'error';
                        } else {
                            $size = min($width, $height);
                            $srcX = (int)(($width - $size) / 2);
                            $srcY = (int)(($height - $size) / 2);

                            $dstImg = imagecreatetruecolor($targetSize, $targetSize);
                            if ($outExt === 'png') {
                                imagealphablending($dstImg, false);
                                imagesavealpha($dstImg, true);
                                $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
                                imagefill($dstImg, 0, 0, $transparent);
                            }

                            imagecopyresampled($dstImg, $srcImg, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $size, $size);

                            $uploadsDir = __DIR__ . '/uploads/perfiles';
                            if (!is_dir($uploadsDir)) {
                                mkdir($uploadsDir, 0775, true);
                            }
                            if (!is_writable($uploadsDir)) {
                                $uploadMsg = 'La carpeta de perfiles no tiene permisos de escritura.';
                                $uploadType = 'error';
                            } else {
                                $token = bin2hex(random_bytes(4));
                                $newName = 'perfil_' . $_SESSION['user_id'] . '_' . time() . '_' . $token . '.' . $outExt;
                                $destino = $uploadsDir . DIRECTORY_SEPARATOR . $newName;
                                $saved = $saveFn($dstImg, $destino);

                                if ($saved) {
                                    $oldFoto = $user['foto_perfil'] ?? '';
                                    $stmtUpdate = $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                                    $stmtUpdate->execute([$newName, $_SESSION['user_id']]);
                                    if ($oldFoto && $oldFoto !== $newName) {
                                        $oldPath = $uploadsDir . DIRECTORY_SEPARATOR . $oldFoto;
                                        if (is_file($oldPath)) {
                                            @unlink($oldPath);
                                        }
                                    }
                                    $uploadMsg = 'Foto actualizada correctamente.';
                                    $uploadType = 'success';
                                    $refreshUser = true;
                                } else {
                                    $uploadMsg = 'No se pudo guardar la imagen.';
                                    $uploadType = 'error';
                                }
                            }

                            // Image resources are automatically destroyed when out of scope in PHP 8.0+
                        }
                    }
                }
            }
        }
    } elseif ($action === 'update_profile') {
        $nombreNuevo = trim($_POST['nombre'] ?? '');
        $emailNuevo = trim($_POST['email'] ?? '');
        $telefonoNuevo = trim($_POST['telefono'] ?? '');
        $empresaNuevo = trim($_POST['empresa'] ?? '');
        $puestoNuevo = trim($_POST['puesto'] ?? '');
        $ciudadNuevo = trim($_POST['ciudad'] ?? '');
        $estadoNuevo = trim($_POST['estado'] ?? '');
        $especialidadNueva = trim($_POST['especialidad'] ?? '');
        $biografiaNueva = trim($_POST['biografia'] ?? '');
        $cedulaNueva = trim($_POST['cedula_profesional'] ?? '');

        if ($nombreNuevo === '' || $emailNuevo === '') {
            $profileMsg = 'Nombre y email son obligatorios.';
            $profileType = 'error';
        } elseif (!filter_var($emailNuevo, FILTER_VALIDATE_EMAIL)) {
            $profileMsg = 'El email no es v&aacute;lido.';
            $profileType = 'error';
        } else {
            if (function_exists('mb_substr')) {
                $biografiaNueva = mb_substr($biografiaNueva, 0, 1000, 'UTF-8');
            } else {
                $biografiaNueva = substr($biografiaNueva, 0, 1000);
            }
            $stmtUpdate = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, empresa = ?, puesto = ?, ciudad = ?, estado = ?, especialidad = ?, biografia = ?, cedula_profesional = ? WHERE id = ?");
            $stmtUpdate->execute([
                $nombreNuevo,
                $emailNuevo,
                $telefonoNuevo,
                $empresaNuevo,
                $puestoNuevo,
                $ciudadNuevo,
                $estadoNuevo,
                $especialidadNueva,
                $biografiaNueva,
                $cedulaNueva,
                $_SESSION['user_id'],
            ]);
            $_SESSION['user_name'] = $nombreNuevo;
            $profileMsg = 'Perfil actualizado correctamente.';
            $profileType = 'success';
            $refreshUser = true;
        }
    }
}

if ($refreshUser) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

function calcularTiempo(string $fecha): string
{
    try {
        $inicio = new DateTime($fecha);
    } catch (Throwable $e) {
        return '0 minutos';
    }
    $ahora = new DateTime();
    $diff = $ahora->getTimestamp() - $inicio->getTimestamp();
    if ($diff < 60) {
        $valor = max(1, $diff);
        return $valor . ' segundo' . ($valor === 1 ? '' : 's');
    }
    if ($diff < 3600) {
        $valor = (int)floor($diff / 60);
        return $valor . ' minuto' . ($valor === 1 ? '' : 's');
    }
    if ($diff < 86400) {
        $valor = (int)floor($diff / 3600);
        return $valor . ' hora' . ($valor === 1 ? '' : 's');
    }
    if ($diff < 2592000) {
        $valor = (int)floor($diff / 86400);
        return $valor . ' d&iacute;a' . ($valor === 1 ? '' : 's');
    }
    if ($diff < 31536000) {
        $valor = (int)floor($diff / 2592000);
        return $valor . ' mes' . ($valor === 1 ? '' : 'es');
    }
    $valor = (int)floor($diff / 31536000);
    return $valor . ' a&ntilde;o' . ($valor === 1 ? '' : 's');
}

function initial_from_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $first = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
    return function_exists('mb_strtoupper') ? mb_strtoupper($first, 'UTF-8') : strtoupper($first);
}

$fotoPerfil = $user['foto_perfil'] ?? '';
$fotoRel = $fotoPerfil !== '' ? 'uploads/perfiles/' . $fotoPerfil : 'uploads/perfiles/default.png';
$fotoPath = __DIR__ . '/' . $fotoRel;
$hasFoto = $fotoPerfil !== '' && file_exists($fotoPath);

$nombre = (string)($user['nombre'] ?? '');
$rol = (string)($user['rol'] ?? '');
$email = (string)($user['email'] ?? '');
$telefono = (string)($user['telefono'] ?? '');
$empresa = (string)($user['empresa'] ?? '');
$puesto = (string)($user['puesto'] ?? '');
$ciudad = (string)($user['ciudad'] ?? '');
$estado = (string)($user['estado'] ?? '');
$especialidad = (string)($user['especialidad'] ?? '');
$biografia = (string)($user['biografia'] ?? '');
$cedula = (string)($user['cedula_profesional'] ?? '');
$miembroDesde = !empty($user['creado_at']) ? date("Y", strtotime((string)$user['creado_at'])) : '';
$ubicacion = trim($ciudad . ($ciudad !== '' && $estado !== '' ? ', ' : '') . $estado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Mi Perfil - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'perfil';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-8">
        <header class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Mi Perfil</h1>
            <p class="text-gray-500">Administra tu informaci&oacute;n personal y configuraci&oacute;n de cuenta</p>
        </header>

        <div class="flex flex-wrap gap-2 mb-8 bg-gray-200/50 p-1 rounded-xl w-fit">
            <?php
            $tabs = ['informacion', 'estadisticas', 'seguridad', 'notificaciones'];
            foreach ($tabs as $t):
            ?>
                <a href="?tab=<?php echo $t; ?>"
                   class="px-6 py-2 rounded-lg text-sm font-medium transition <?php echo $tab == $t ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'; ?>">
                    <?php echo ucfirst($t); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="space-y-6">
            <?php if ($tab == 'informacion'): ?>
                <?php if ($uploadMsg): ?>
                    <div class="mb-4 p-3 rounded-lg text-sm <?php echo $uploadType === 'error' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?>">
                        <?php echo htmlspecialchars($uploadMsg); ?>
                    </div>
                <?php endif; ?>
                <?php if ($profileMsg): ?>
                    <div class="mb-4 p-3 rounded-lg text-sm <?php echo $profileType === 'error' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?>">
                        <?php echo htmlspecialchars_decode($profileMsg); ?>
                    </div>
                <?php endif; ?>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                        <div class="flex flex-col sm:flex-row sm:items-start gap-6">
                            <form method="POST" enctype="multipart/form-data" class="relative">
                                <input type="hidden" name="action" value="upload_photo">
                                <input id="fotoInput" type="file" name="foto_perfil" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="this.form.submit()">
                                <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-white shadow-sm bg-gray-100 flex items-center justify-center">
                                    <?php if ($hasFoto): ?>
                                        <img src="<?php echo htmlspecialchars($fotoRel); ?>" class="w-full h-full object-cover" alt="Foto de perfil">
                                    <?php else: ?>
                                        <span class="text-2xl font-semibold text-gray-500"><?php echo htmlspecialchars(initial_from_name($nombre)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <label for="fotoInput" class="absolute -bottom-1 -right-1 bg-white shadow-md p-2 rounded-full text-xs hover:bg-gray-50 cursor-pointer" title="Cambiar foto">
                                    <i class="fa-solid fa-camera"></i>
                                </label>
                                <label for="fotoInput" class="mt-3 text-xs text-gray-500 flex items-center gap-2 cursor-pointer">
                                    <i class="fa-solid fa-pen"></i> Cambiar Foto
                                </label>
                                <p class="text-[10px] text-gray-400 mt-2">JPG, PNG o WebP &middot; M&iacute;n 300x300 &middot; M&aacute;x 3MB</p>
                            </form>
                            <div class="space-y-2">
                                <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($nombre); ?></h2>
                                <p class="text-sm text-gray-400"><?php echo htmlspecialchars($rol); ?></p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-gray-500">
                                    <?php if ($email !== ''): ?>
                                        <span class="flex items-center gap-2"><i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($email); ?></span>
                                    <?php endif; ?>
                                    <?php if ($empresa !== ''): ?>
                                        <span class="flex items-center gap-2"><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($empresa); ?></span>
                                    <?php endif; ?>
                                    <?php if ($ubicacion !== ''): ?>
                                        <span class="flex items-center gap-2"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($ubicacion); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button id="editSaveBtn" data-state="view" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold flex items-center transition" type="button" form="perfilForm">
                                <i class="fa-regular fa-pen-to-square mr-2"></i><span>Editar</span>
                            </button>
                            <a href="<?php echo BASE_URL; ?>/perfil.php?tab=informacion" class="text-gray-500 hover:text-gray-700 text-xs font-semibold">Cancelar</a>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs text-gray-500">
                        <?php if ($telefono !== ''): ?>
                            <div class="flex items-center gap-2"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($telefono); ?></div>
                        <?php endif; ?>
                        <?php if ($puesto !== ''): ?>
                            <div class="flex items-center gap-2"><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($puesto); ?></div>
                        <?php endif; ?>
                        <?php if ($empresa !== ''): ?>
                            <div class="flex items-center gap-2"><i class="fa-solid fa-building"></i> <?php echo htmlspecialchars($empresa); ?></div>
                        <?php endif; ?>
                        <?php if ($miembroDesde !== ''): ?>
                            <div class="flex items-center gap-2"><i class="fa-regular fa-calendar"></i> Asociado desde <?php echo htmlspecialchars($miembroDesde); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <form id="perfilForm" method="POST" class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
                    <input type="hidden" name="action" value="update_profile">
                    <h3 class="font-bold text-gray-800 mb-6">Informaci&oacute;n Personal</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Nombre Completo</label>
                            <input type="text" name="nombre" required disabled value="<?php echo htmlspecialchars($nombre, ENT_QUOTES); ?>" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Email</label>
                            <input type="email" name="email" required disabled value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Tel&eacute;fono</label>
                            <input type="text" name="telefono" disabled value="<?php echo htmlspecialchars($telefono, ENT_QUOTES); ?>" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">C&eacute;dula Profesional</label>
                            <input type="text" name="cedula_profesional" disabled value="<?php echo htmlspecialchars($cedula, ENT_QUOTES); ?>" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Despacho/Empresa</label>
                            <input type="text" name="empresa" disabled value="<?php echo htmlspecialchars($empresa, ENT_QUOTES); ?>" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Puesto</label>
                            <input type="text" name="puesto" disabled value="<?php echo htmlspecialchars($puesto, ENT_QUOTES); ?>" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Ciudad</label>
                            <input type="text" name="ciudad" disabled value="<?php echo htmlspecialchars($ciudad, ENT_QUOTES); ?>" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Estado</label>
                            <input type="text" name="estado" disabled value="<?php echo htmlspecialchars($estado, ENT_QUOTES); ?>" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Especialidad</label>
                            <input type="text" name="especialidad" disabled value="<?php echo htmlspecialchars($especialidad, ENT_QUOTES); ?>" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Biograf&iacute;a</label>
                        <textarea name="biografia" rows="4" maxlength="1000" disabled class="w-full p-3 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($biografia, ENT_QUOTES); ?></textarea>
                        <p class="text-[10px] text-gray-400 mt-1">M&aacute;ximo 1000 caracteres.</p>
                    </div>
                </form>

                <div class="bg-white p-6 rounded-3xl border border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <div class="bg-blue-50 p-3 rounded-xl text-blue-600"><i class="fa-solid fa-award text-2xl"></i></div>
                        <div>
                            <p class="font-bold text-gray-800">Certificado de Asociado</p>
                            <p class="text-xs text-gray-400">Descarga tu certificado oficial de ANAFINET</p>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/generar_certificado.php" target="_blank" class="bg-[#E67E22] text-white px-6 py-2 rounded-xl font-bold hover:bg-orange-600 transition shadow-lg flex items-center">
                        <i class="fa-solid fa-download mr-2"></i> Descargar Certificado
                    </a>
                </div>
                <script>
                (function () {
                    const perfilForm = document.getElementById('perfilForm');
                    const editSaveBtn = document.getElementById('editSaveBtn');
                    if (!perfilForm || !editSaveBtn) return;

                    const editableFields = perfilForm.querySelectorAll('input, textarea, select');
                    const icon = editSaveBtn.querySelector('i');
                    const label = editSaveBtn.querySelector('span');

                    const setEditing = (isEditing) => {
                        editableFields.forEach((el) => {
                            if (el.type === 'hidden') return;
                            el.disabled = !isEditing;
                        });

                        if (isEditing) {
                            editSaveBtn.dataset.state = 'edit';
                            editSaveBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                            editSaveBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                            if (icon) icon.className = 'fa-regular fa-floppy-disk mr-2';
                            if (label) label.textContent = 'Guardar';
                        } else {
                            editSaveBtn.dataset.state = 'view';
                            editSaveBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                            editSaveBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                            if (icon) icon.className = 'fa-regular fa-pen-to-square mr-2';
                            if (label) label.textContent = 'Editar';
                        }
                    };

                    setEditing(false);

                    editSaveBtn.addEventListener('click', () => {
                        const isEditing = editSaveBtn.dataset.state === 'edit';
                        if (!isEditing) {
                            setEditing(true);
                            return;
                        }
                        perfilForm.requestSubmit();
                    });
                })();
                </script>

            <?php elseif ($tab == 'estadisticas'): ?>
                <?php
                $vistos = 0;
                $descargas = 0;
                $actividades = [];
                try {
                    $stmtV = $pdo->prepare("SELECT COUNT(*) FROM actividad_usuario WHERE usuario_id = ? AND tipo_accion = 'video_visto'");
                    $stmtV->execute([$_SESSION['user_id']]);
                    $vistos = (int)$stmtV->fetchColumn();

                    $stmtD = $pdo->prepare("SELECT COUNT(*) FROM actividad_usuario WHERE usuario_id = ? AND tipo_accion = 'documento_descargado'");
                    $stmtD->execute([$_SESSION['user_id']]);
                    $descargas = (int)$stmtD->fetchColumn();

                    $stmtA = $pdo->prepare("SELECT * FROM actividad_usuario WHERE usuario_id = ? ORDER BY creado_at DESC LIMIT 5");
                    $stmtA->execute([$_SESSION['user_id']]);
                    $actividades = $stmtA->fetchAll();
                } catch (Throwable $e) {
                    $vistos = 0;
                    $descargas = 0;
                    $actividades = [];
                }
                ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm text-center">
                        <div class="w-10 h-10 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fa-regular fa-eye"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800"><?php echo $vistos; ?></h2>
                        <p class="text-xs text-gray-400">Videos Vistos</p>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm text-center">
                        <div class="w-10 h-10 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fa-solid fa-download"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800"><?php echo $descargas; ?></h2>
                        <p class="text-xs text-gray-400">Documentos Descargados</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
                    <h3 class="font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fa-solid fa-chart-line mr-2 text-blue-500"></i> Actividad Reciente
                    </h3>
                    <div class="space-y-4">
                        <?php if (empty($actividades)): ?>
                            <p class="text-sm text-gray-400">A&uacute;n no hay actividad registrada.</p>
                        <?php else: ?>
                            <?php foreach ($actividades as $act): ?>
                                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl">
                                    <div class="flex items-center space-x-4">
                                        <div class="p-2 bg-white rounded-lg shadow-sm">
                                            <i class="fa-solid fa-arrow-trend-up text-blue-400 text-xs"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars((string)($act['detalle'] ?? '')); ?></p>
                                            <p class="text-[10px] text-gray-400">Hace <?php echo calcularTiempo((string)($act['creado_at'] ?? '')); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($tab == 'seguridad'): ?>
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 max-w-2xl">
                    <h3 class="font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fa-solid fa-lock mr-2 text-blue-500"></i> Cambiar Contrase&ntilde;a
                    </h3>
                    <form action="<?php echo BASE_URL; ?>/update_password.php" method="POST" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Contrase&ntilde;a Actual</label>
                            <input type="password" name="current" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Nueva Contrase&ntilde;a</label>
                            <input type="password" name="new" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <button class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold">Actualizar Contrase&ntilde;a</button>
                    </form>
                </div>
            <?php elseif ($tab == 'notificaciones'): ?>
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 max-w-3xl">
                    <h3 class="font-bold text-gray-800 mb-6 flex items-center">
                        <i class="fa-regular fa-bell mr-2 text-blue-500"></i> Preferencias de Notificaciones
                    </h3>

                    <div class="space-y-6">
                        <?php
                        $opciones = [
                            ['id' => 'notif_email', 'titulo' => 'Notificaciones por Email', 'desc' => 'Recibe actualizaciones importantes por correo', 'val' => !empty($user['notif_email'])],
                            ['id' => 'notif_boletin', 'titulo' => 'Bolet&iacute;n ANAFINET', 'desc' => 'Recibe nuestro bolet&iacute;n mensual con novedades', 'val' => !empty($user['notif_boletin'])],
                            ['id' => 'notif_eventos', 'titulo' => 'Eventos y Webinars', 'desc' => 'Recibe avisos de eventos y sesiones en vivo', 'val' => !empty($user['notif_eventos'])],
                            ['id' => 'notif_foro', 'titulo' => 'Actividad del Foro', 'desc' => 'Recibe notificaciones cuando haya nuevas participaciones', 'val' => !empty($user['notif_foro'])],
                        ];
                        foreach ($opciones as $op):
                        ?>
                        <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl">
                            <div>
                                <p class="text-sm font-bold text-gray-700"><?php echo $op['titulo']; ?></p>
                                <p class="text-xs text-gray-400"><?php echo $op['desc']; ?></p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer" <?php echo $op['val'] ? 'checked' : ''; ?>
                                       onchange="updateNotif('<?php echo $op['id']; ?>', this.checked)">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <script>
                function updateNotif(campo, valor) {
                    const valNumeric = valor ? 1 : 0;
                    fetch(`update_settings.php?campo=${campo}&valor=${valNumeric}`);
                }
                </script>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>


