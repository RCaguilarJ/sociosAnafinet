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

$search = $_GET['search'] ?? '';
$estado_filtro = $_GET['estado'] ?? '';
$page = isset($_GET['p']) ? (int)$_GET['p'] : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
$page = $page > 0 ? $page : 1;
$perPage = 12;

$asociados = [];
$estados = [];
$total_asoc = 0;
$total_estados = 0;
$total_despachos = 0;
$total_filtrados = 0;
$total_pages = 1;
$offset = 0;

if ($isAdmin) {
    $stmtEstados = $pdo->query("SELECT DISTINCT estado FROM usuarios WHERE estado IS NOT NULL AND estado <> '' ORDER BY estado ASC");
    $estados = $stmtEstados->fetchAll(PDO::FETCH_COLUMN);

    $where = " WHERE rol = 'Asociado'";
    $params = [];

    if ($search !== '') {
        $where .= " AND (nombre LIKE ? OR empresa LIKE ? OR ciudad LIKE ? OR especialidad LIKE ? OR email LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($estado_filtro !== '') {
        $where .= " AND estado = ?";
        $params[] = $estado_filtro;
    }

    $countQuery = "SELECT COUNT(*) FROM usuarios" . $where;
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->execute($params);
    $total_filtrados = (int)$stmtCount->fetchColumn();
    $total_pages = max(1, (int)ceil($total_filtrados / $perPage));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $perPage;

    $query = "SELECT * FROM usuarios" . $where . " ORDER BY creado_at DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);
    $bindIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($bindIndex, $param);
        $bindIndex++;
    }
    $stmt->bindValue($bindIndex, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($bindIndex + 1, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $asociados = $stmt->fetchAll();

    $total_asoc = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'Asociado'")->fetchColumn();
    $total_estados = $pdo->query("SELECT COUNT(DISTINCT estado) FROM usuarios")->fetchColumn();
    $total_despachos = $pdo->query("SELECT COUNT(DISTINCT empresa) FROM usuarios")->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Lista de Asociados - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'asociados';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-8">
        <header class="mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Lista de Asociados</h1>
            <p class="text-sm text-gray-500">Directorio de profesionales fiscales miembros de ANAFINET</p>
        </header>

        <?php if (!$isAdmin): ?>
            <div class="bg-white rounded-2xl border border-gray-100 p-6 text-sm text-red-600">
                Acceso restringido: solo administradores.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-[#5282B2] p-4 rounded-xl text-white flex justify-between items-center">
                    <div>
                        <p class="text-[10px] opacity-80 uppercase">Total Asociados</p>
                        <h2 class="text-2xl font-bold"><?php echo number_format((int)$total_asoc); ?></h2>
                    </div>
                    <i class="fa-solid fa-users text-2xl opacity-30"></i>
                </div>
                <div class="bg-[#E67E22] p-4 rounded-xl text-white flex justify-between items-center">
                    <div>
                        <p class="text-[10px] opacity-80 uppercase">Estados Representados</p>
                        <h2 class="text-2xl font-bold"><?php echo (int)$total_estados; ?></h2>
                    </div>
                    <i class="fa-solid fa-location-dot text-2xl opacity-30"></i>
                </div>
                <div class="bg-[#9B59B6] p-4 rounded-xl text-white flex justify-between items-center">
                    <div>
                        <p class="text-[10px] opacity-80 uppercase">Despachos Afiliados</p>
                        <h2 class="text-2xl font-bold"><?php echo (int)$total_despachos; ?></h2>
                    </div>
                    <i class="fa-solid fa-building text-2xl opacity-30"></i>
                </div>
            </div>

            <form method="GET" class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap gap-4 mb-6">
                <div class="flex-1 min-w-[200px] relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-300"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por nombre, despacho o ciudad..." class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="min-w-[200px]">
                    <select name="estado" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?php echo htmlspecialchars($estado); ?>" <?php echo $estado_filtro === $estado ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($estado); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-xl text-sm font-bold">Filtrar</button>
            </form>

            <?php
            $baseParams = [];
            if ($search !== '') {
                $baseParams['search'] = $search;
            }
            if ($estado_filtro !== '') {
                $baseParams['estado'] = $estado_filtro;
            }
            ?>

            <?php if (empty($asociados)): ?>
                <div class="bg-white p-8 rounded-3xl border border-gray-100 text-sm text-gray-400">
                    No se encontraron asociados con los filtros aplicados.
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php foreach ($asociados as $asoc):
                        $foto = !empty($asoc['foto_perfil']) ? $asoc['foto_perfil'] : 'default.png';
                        $empresa = (string)($asoc['empresa'] ?? '');
                        $especialidad = (string)($asoc['especialidad'] ?? '');
                        $ciudad = (string)($asoc['ciudad'] ?? '');
                        $estado = (string)($asoc['estado'] ?? '');
                        $ubicacion = trim($ciudad . ($ciudad !== '' && $estado !== '' ? ', ' : '') . $estado);
                        $email = (string)($asoc['email'] ?? '');
                        $telefono = (string)($asoc['telefono'] ?? '');
                        $creado = !empty($asoc['creado_at']) ? date("Y", strtotime((string)$asoc['creado_at'])) : '';
                    ?>
                    <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 hover:shadow-md transition relative overflow-hidden group">
                        <div class="flex items-start space-x-4">
                            <img src="uploads/perfiles/<?php echo htmlspecialchars($foto); ?>" class="w-16 h-16 rounded-full object-cover" alt="Foto de perfil">
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars((string)($asoc['nombre'] ?? '')); ?></h3>
                                <p class="text-xs text-gray-400 flex items-center mb-2">
                                    <i class="fa-solid fa-building mr-1"></i> <?php echo htmlspecialchars($empresa); ?>
                                </p>
                                <?php if ($especialidad !== ''): ?>
                                    <span class="bg-blue-50 text-blue-500 text-[10px] font-bold px-2 py-1 rounded uppercase">
                                        <?php echo htmlspecialchars($especialidad); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($ubicacion !== ''): ?>
                                    <p class="text-[10px] text-gray-400 mt-4"><i class="fa-solid fa-location-dot mr-1"></i> <?php echo htmlspecialchars($ubicacion); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-gray-50 flex flex-wrap gap-3 items-center justify-between text-xs">
                            <div class="flex flex-wrap gap-4">
                                <?php if ($email !== ''): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($email); ?>" class="text-gray-500 hover:text-blue-600">
                                        <i class="fa-regular fa-envelope mr-1"></i> Email
                                    </a>
                                <?php endif; ?>
                                <?php if ($telefono !== ''): ?>
                                    <a href="tel:<?php echo htmlspecialchars($telefono); ?>" class="text-gray-500 hover:text-blue-600">
                                        <i class="fa-solid fa-phone mr-1"></i> Llamar
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php if ($creado !== ''): ?>
                                <span class="text-gray-300 italic">Asociado desde <?php echo htmlspecialchars($creado); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($total_pages > 1): ?>
                <?php
                $maxPagesToShow = 7;
                $startPage = max(1, $page - 3);
                $endPage = min($total_pages, $startPage + $maxPagesToShow - 1);
                if (($endPage - $startPage + 1) < $maxPagesToShow) {
                    $startPage = max(1, $endPage - $maxPagesToShow + 1);
                }
                ?>
                <div class="mt-10 flex items-center justify-center space-x-2 flex-wrap">
                    <?php for ($p = $startPage; $p <= $endPage; $p++):
                        $link = '?' . http_build_query(array_merge($baseParams, ['p' => $p]));
                    ?>
                        <a href="<?php echo $link; ?>"
                           class="w-10 h-10 flex items-center justify-center rounded-xl font-bold transition <?php echo $page === $p ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-gray-500 hover:bg-gray-100 border border-gray-100'; ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <div class="mt-4 flex items-center justify-center text-xs text-gray-400">
                    P&aacute;gina <?php echo $page; ?> de <?php echo $total_pages; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>


