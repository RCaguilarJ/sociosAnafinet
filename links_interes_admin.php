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
    header("Location: links_interes.php");
    exit();
}

$adminMsg = '';
$adminType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $icono = trim($_POST['icono'] ?? '');
    if ($icono === '') {
        $icono = 'fa-link';
    }

    $urlValida = $url !== '' && ($url === '#' || filter_var($url, FILTER_VALIDATE_URL));
    if ($action !== 'delete_link' && ($titulo === '' || $categoria === '' || !$urlValida)) {
        $adminMsg = 'Completa t&iacute;tulo, categor&iacute;a y una URL v&aacute;lida (o #).';
        $adminType = 'error';
    } else {
        try {
            if ($action === 'add_link') {
                $stmt = $pdo->prepare("INSERT INTO links_interes (titulo, descripcion, url, categoria, icono) VALUES (?,?,?,?,?)");
                $stmt->execute([$titulo, $descripcion, $url, $categoria, $icono]);
                header('Location: links_interes_admin.php?ok=1');
                exit();
            }
            if ($action === 'update_link') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE links_interes SET titulo = ?, descripcion = ?, url = ?, categoria = ?, icono = ? WHERE id = ?");
                    $stmt->execute([$titulo, $descripcion, $url, $categoria, $icono, $id]);
                    header('Location: links_interes_admin.php?ok=1');
                    exit();
                }
            }
            if ($action === 'delete_link') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM links_interes WHERE id = ?");
                    $stmt->execute([$id]);
                    header('Location: links_interes_admin.php?ok=1');
                    exit();
                }
            }
        } catch (Throwable $e) {
            $adminMsg = 'No se pudo guardar el cambio.';
            $adminType = 'error';
        }
    }
}

$stmt = $pdo->query("SELECT * FROM links_interes ORDER BY categoria, titulo ASC");
$links = $stmt->fetchAll();

$categoryOptions = [];
foreach ($links as $link) {
    $cat = trim((string)($link['categoria'] ?? ''));
    if ($cat !== '' && !in_array($cat, $categoryOptions, true)) {
        $categoryOptions[] = $cat;
    }
}
sort($categoryOptions);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Administrar Links - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'links_admin';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-8">
        <header class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Administrar Links de Inter&eacute;s</h1>
                <p class="text-sm text-gray-500">Gestiona los recursos visibles en la secci&oacute;n de links.</p>
            </div>
            <a href="links_interes.php" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-700">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Ver vista p&uacute;blica
            </a>
        </header>

        <?php if ($adminMsg): ?>
            <div class="mb-6 p-3 rounded-lg text-sm <?php echo $adminType === 'error' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600'; ?>">
                <?php echo htmlspecialchars($adminMsg); ?>
            </div>
        <?php elseif (isset($_GET['ok'])): ?>
            <div class="mb-6 p-3 rounded-lg text-sm bg-green-50 text-green-600">
                Cambios guardados correctamente.
            </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm mb-8">
            <h2 class="font-semibold text-gray-800 mb-4">Agregar Link</h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="add_link">
                <div>
                    <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">T&iacute;tulo</label>
                    <input type="text" name="titulo" required class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">URL</label>
                    <input type="text" name="url" required placeholder="https://..." class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Categor&iacute;a</label>
                    <input type="text" name="categoria" list="categoriasLinks" required class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                    <datalist id="categoriasLinks">
                        <?php foreach ($categoryOptions as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Icono (FontAwesome)</label>
                    <input type="text" name="icono" placeholder="fa-link" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs font-bold text-gray-400 uppercase mb-1 block">Descripci&oacute;n</label>
                    <input type="text" name="descripcion" class="w-full p-2 border border-gray-200 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button class="bg-blue-600 text-white px-5 py-2 rounded-xl text-xs font-bold">Agregar Link</button>
                </div>
            </form>
        </div>

        <?php if (empty($links)): ?>
            <div class="bg-white p-8 rounded-3xl border border-gray-100 text-sm text-gray-400">
                A&uacute;n no hay links cargados.
            </div>
        <?php else: ?>
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Editar / Eliminar Links</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($links as $link): ?>
                        <div class="p-4 sm:p-5">
                            <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                                <input type="hidden" name="action" value="update_link">
                                <input type="hidden" name="id" value="<?php echo (int)$link['id']; ?>">
                                <div class="md:col-span-1">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">T&iacute;tulo</label>
                                    <input type="text" name="titulo" value="<?php echo htmlspecialchars((string)$link['titulo']); ?>" class="w-full p-2 border border-gray-200 rounded-lg text-xs">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">URL</label>
                                    <input type="text" name="url" value="<?php echo htmlspecialchars((string)$link['url']); ?>" class="w-full p-2 border border-gray-200 rounded-lg text-xs">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Categor&iacute;a</label>
                                    <input type="text" name="categoria" value="<?php echo htmlspecialchars((string)$link['categoria']); ?>" class="w-full p-2 border border-gray-200 rounded-lg text-xs">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Icono</label>
                                    <input type="text" name="icono" value="<?php echo htmlspecialchars((string)($link['icono'] ?? 'fa-link')); ?>" class="w-full p-2 border border-gray-200 rounded-lg text-xs">
                                </div>
                                <div class="md:col-span-1 flex gap-2">
                                    <button class="bg-blue-600 text-white px-3 py-2 rounded-lg text-xs font-bold">Guardar</button>
                                    <button class="bg-red-50 text-red-600 px-3 py-2 rounded-lg text-xs font-bold" type="submit" formmethod="POST" name="action" value="delete_link" onclick="return confirm('&iquest;Eliminar este link?')">Eliminar</button>
                                </div>
                                <div class="md:col-span-5">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Descripci&oacute;n</label>
                                    <input type="text" name="descripcion" value="<?php echo htmlspecialchars((string)($link['descripcion'] ?? '')); ?>" class="w-full p-2 border border-gray-200 rounded-lg text-xs">
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
