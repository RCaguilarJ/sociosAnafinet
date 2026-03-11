<?php
session_start();
require 'db.php';
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

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$asociado = null;
$mensaje = '';
$mensajeTipo = 'success';

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

    if ($nombre === '' || $email === '' || $rol === '') {
        $mensaje = 'Todos los campos son obligatorios.';
        $mensajeTipo = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'El email no es v&aacute;lido.';
        $mensajeTipo = 'error';
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?");
        $stmt->execute([$nombre, $email, $rol, $editId]);
        $mensaje = 'Cambios guardados correctamente.';
        $mensajeTipo = 'success';
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
                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold shadow-lg hover:bg-blue-700 transition">
                    Guardar Cambios
                </button>
            </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>



