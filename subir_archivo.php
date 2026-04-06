<?php
require_once __DIR__ . '/bootstrap.php';
require_once 'role_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$topicOptions = [
    'leyes' => 'Leyes y Reglamentos',
    'formatos' => 'Formatos',
    'guias' => 'Gu&iacute;as',
    'boletines' => 'Boletines',
    'circulares' => 'Circulares',
    'casos' => 'Casos Pr&aacute;cticos',
];

$userRole = $_SESSION['user_rol'] ?? '';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
if (isset($pdo)) {
    $dbRole = fetch_user_role($pdo, $userId);
    if ($dbRole !== null) {
        $userRole = $dbRole;
    }
}

$isAdmin = is_admin_role($userRole);
$temaColumnOk = null;
$temaColumnMsg = '';

if ($isAdmin) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM contenidos LIKE ?");
        $stmt->execute(['tema']);
        $temaColumnOk = (bool)$stmt->fetch();
        if ($temaColumnOk === false) {
            $temaColumnMsg = 'La columna tema no existe. Ejecuta: ALTER TABLE contenidos ADD COLUMN tema VARCHAR(100) NULL;';
        }
    } catch (Throwable $e) {
        $temaColumnOk = null;
    }
}

$mensaje = '';
$mensajeTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (empty($_POST) && empty($_FILES) && !empty($_SERVER['CONTENT_LENGTH'])) {
        $postMax = ini_get('post_max_size');
        $uploadMax = ini_get('upload_max_filesize');
        $mensaje = "Error: La carga fue bloqueada por el limite de PHP (post_max_size={$postMax}, upload_max_filesize={$uploadMax}).";
        $mensajeTipo = 'error';
    } else {
        $titulo = trim($_POST['titulo'] ?? '');
        $tema = trim($_POST['tema'] ?? '');
        $archivo = $_FILES['archivo'] ?? null;

        $permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
        $max_size = 4 * 1024 * 1024;
        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if ($titulo === '') {
            $mensaje = 'Error: El titulo es obligatorio.';
            $mensajeTipo = 'error';
        } elseif ($tema === '' || !array_key_exists($tema, $topicOptions)) {
            $mensaje = 'Error: Selecciona un tema valido.';
            $mensajeTipo = 'error';
        } elseif (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $mensaje = 'Error: Selecciona un archivo para subir.';
            $mensajeTipo = 'error';
        } elseif (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $err = $archivo['error'] ?? UPLOAD_ERR_OK;
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                $mensaje = 'Error: El archivo excede el limite permitido por PHP (upload_max_filesize/post_max_size).';
            } elseif ($err === UPLOAD_ERR_PARTIAL) {
                $mensaje = 'Error: El archivo se subio de forma incompleta.';
            } elseif ($err === UPLOAD_ERR_NO_TMP_DIR) {
                $mensaje = 'Error: Falta la carpeta temporal de PHP.';
            } elseif ($err === UPLOAD_ERR_CANT_WRITE) {
                $mensaje = 'Error: No se pudo escribir el archivo en el disco.';
            } elseif ($err === UPLOAD_ERR_EXTENSION) {
                $mensaje = 'Error: La carga fue detenida por una extension de PHP.';
            } else {
                $mensaje = 'Error: Hubo un problema al subir el archivo.';
            }
            $mensajeTipo = 'error';
        } else {
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $permitidos, true)) {
                $mensaje = 'Error: Solo se permiten archivos PDF, Word o Excel.';
                $mensajeTipo = 'error';
            } elseif ($archivo['size'] > $max_size) {
                $mensaje = 'Error: El archivo es demasiado pesado (Max 5MB).';
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
                    $mensaje = 'Error: El tipo de archivo no es valido.';
                    $mensajeTipo = 'error';
                } else {
                    $uploadsDir = app_ensure_storage_directory('documentos');
                    if (!is_writable($uploadsDir)) {
                        $mensaje = 'Error: La carpeta de documentos no tiene permisos de escritura.';
                        $mensajeTipo = 'error';
                    } else {
                        $baseName = pathinfo($archivo['name'], PATHINFO_FILENAME);
                        $safeBase = preg_replace('/[^A-Za-z0-9_-]/', '_', $baseName);
                        if ($safeBase === '') {
                            $safeBase = 'documento';
                        }
                        $token = bin2hex(random_bytes(4));
                        $nombre_final = time() . '_' . $token . '_' . $safeBase . '.' . $extension;
                        $ruta_destino = $uploadsDir . DIRECTORY_SEPARATOR . $nombre_final;

                        if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                            try {
                                $stmt = $pdo->prepare("INSERT INTO contenidos (tipo, titulo, url_recurso, fecha_publicacion, tema) VALUES ('documento', ?, ?, CURDATE(), ?)");
                                $stmt->execute([$titulo, $nombre_final, $tema]);
                                $mensaje = 'Archivo subido con exito.';
                                $mensajeTipo = 'success';
                            } catch (PDOException $e) {
                                $sqlState = $e->getCode();
                                $msg = $e->getMessage();
                                if ($sqlState === '42S22' || stripos($msg, 'Unknown column') !== false) {
                                    $mensaje = 'Error: La columna tema no existe en la tabla contenidos. Ejecuta: ALTER TABLE contenidos ADD COLUMN tema VARCHAR(100) NULL;';
                                } else {
                                    $mensaje = 'Error al guardar el registro en la base de datos.';
                                }
                                $mensajeTipo = 'error';
                            }
                        } else {
                            $mensaje = 'Error al mover el archivo al servidor.';
                            $mensajeTipo = 'error';
                        }
                    }
                }
            }
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
    <title>Subir Documento - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'subir_documentos';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-8 flex justify-center items-center">
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 w-full max-w-lg">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Subir Nuevo Recurso</h2>
            <p class="text-gray-500 text-sm mb-6">Completa los datos para anadir un documento a la biblioteca.</p>

            <?php if (!$isAdmin): ?>
                <div class="mb-4 p-3 rounded-lg text-sm bg-red-50 text-red-600">
                    Acceso restringido: solo administradores.
                </div>
            <?php elseif ($temaColumnMsg): ?>
                <div class="mb-4 p-3 rounded-lg text-sm bg-red-50 text-red-600">
                    <?php echo htmlspecialchars($temaColumnMsg); ?>
                </div>
            <?php elseif ($mensaje): ?>
                <div class="mb-4 p-3 rounded-lg text-sm <?php echo $mensajeTipo === 'error' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titulo del Documento</label>
                    <input type="text" name="titulo" required placeholder="Ej: Nueva Ley de Ingresos 2026"
                           class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tema</label>
                    <select name="tema" required class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="">Selecciona un tema</option>
                        <?php foreach ($topicOptions as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center hover:border-blue-400 transition relative">
                    <input id="archivo" type="file" name="archivo" required accept=".pdf,.doc,.docx,.xls,.xlsx"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="pointer-events-none">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-300 mb-2"></i>
                        <p class="text-sm text-gray-400">Haz clic o arrastra tu archivo aqui</p>
                    <p class="text-[10px] text-gray-300 uppercase mt-1">PDF, DOCX, XLSX hasta 4MB</p>
                    <p class="text-[10px] text-gray-300 mt-1">Limite servidor: <?php echo ini_get('upload_max_filesize'); ?> (post_max_size <?php echo ini_get('post_max_size'); ?>)</p>
                        <p id="archivoNombre" class="mt-3 text-xs text-gray-500">Ningun archivo seleccionado</p>
                    </div>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold shadow-lg hover:bg-blue-700 transition">
                    Publicar Documento
                </button>
            </form>
            <script>
                const inputArchivo = document.getElementById('archivo');
                const labelArchivo = document.getElementById('archivoNombre');
                if (inputArchivo && labelArchivo) {
                    inputArchivo.addEventListener('change', () => {
                        const file = inputArchivo.files && inputArchivo.files[0];
                        labelArchivo.textContent = file ? file.name : 'Ningun archivo seleccionado';
                    });
                }
            </script>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>


