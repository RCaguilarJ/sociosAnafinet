<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$search = trim($_GET['search'] ?? '');
$categoriaFiltro = trim($_GET['categoria'] ?? '');
$sort = $_GET['sort'] ?? 'recientes';

// Estadisticas
$totalTemas = 0;
$respuestas = 0;
$participantes = 0;
$temasHoy = 0;

try {
    $totalTemas = (int)$pdo->query("SELECT COUNT(*) FROM foro_temas")->fetchColumn();
    $temasHoy = (int)$pdo->query("SELECT COUNT(*) FROM foro_temas WHERE DATE(creado_at) = CURDATE()")->fetchColumn();
} catch (Throwable $e) {
    $totalTemas = 0;
    $temasHoy = 0;
}

try {
    $respuestas = (int)$pdo->query("SELECT COUNT(*) FROM foro_respuestas")->fetchColumn();
    $participantes = (int)$pdo->query("SELECT COUNT(DISTINCT usuario_id) FROM (SELECT usuario_id FROM foro_temas UNION SELECT usuario_id FROM foro_respuestas) t")->fetchColumn();
} catch (Throwable $e) {
    try {
        $participantes = (int)$pdo->query("SELECT COUNT(DISTINCT usuario_id) FROM foro_temas")->fetchColumn();
    } catch (Throwable $e2) {
        $participantes = 0;
    }
}

// Categorias para filtros
$categorias = [];
try {
    $stmtCat = $pdo->query("SELECT DISTINCT categoria FROM foro_temas WHERE categoria IS NOT NULL AND categoria <> '' ORDER BY categoria ASC");
    $categorias = $stmtCat->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $categorias = [];
}
if (empty($categorias)) {
    $categorias = ['ISR', 'IVA', 'IMSS', 'SAT', 'Jurisprudencia', 'Casos Prácticos', 'Otros'];
}

// Consultar temas con filtros
$baseSql = " FROM foro_temas t JOIN usuarios u ON t.usuario_id = u.id WHERE 1=1";
$params = [];
if ($search !== '') {
    $baseSql .= " AND (t.titulo LIKE ? OR t.contenido LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if ($categoriaFiltro !== '' && strtolower($categoriaFiltro) !== 'todas') {
    $baseSql .= " AND t.categoria = ?";
    $params[] = $categoriaFiltro;
}

$orderSql = $sort === 'antiguos' ? " ORDER BY t.creado_at ASC" : " ORDER BY t.creado_at DESC";

$stmtCount = $pdo->prepare("SELECT COUNT(*)" . $baseSql);
$stmtCount->execute($params);
$totalFiltrados = (int)$stmtCount->fetchColumn();

$stmt = $pdo->prepare("SELECT t.*, u.nombre as autor, u.foto_perfil" . $baseSql . $orderSql);
$stmt->execute($params);
$temas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Foro Fiscal - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = isset($_GET['nuevo']) ? 'foro_nuevo' : 'foro';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-8">
        <header class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Foro Fiscal</h1>
                <p class="text-gray-500 text-sm">Participa, comparte experiencias y resuelve dudas con otros profesionales.</p>
            </div>
            <button onclick="document.getElementById('modalTema').classList.remove('hidden')" 
                    class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold shadow-lg hover:bg-blue-700 transition flex items-center">
                <i class="fa-solid fa-plus mr-2"></i> Nuevo Tema
            </button>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fa-regular fa-comments"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Temas Totales</p>
                    <p class="text-lg font-bold text-gray-800"><?php echo number_format((int)$totalTemas); ?></p>
                </div>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center">
                    <i class="fa-regular fa-message"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Respuestas</p>
                    <p class="text-lg font-bold text-gray-800"><?php echo number_format((int)$respuestas); ?></p>
                </div>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center">
                    <i class="fa-regular fa-user"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Participantes</p>
                    <p class="text-lg font-bold text-gray-800"><?php echo number_format((int)$participantes); ?></p>
                </div>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Temas Hoy</p>
                    <p class="text-lg font-bold text-gray-800"><?php echo number_format((int)$temasHoy); ?></p>
                </div>
            </div>
        </div>

        <?php
        $baseParams = [];
        if ($search !== '') { $baseParams['search'] = $search; }
        if ($categoriaFiltro !== '') { $baseParams['categoria'] = $categoriaFiltro; }
        if ($sort !== '') { $baseParams['sort'] = $sort; }
        ?>

        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm mb-6 space-y-4">
            <form method="GET" class="flex flex-wrap gap-3 items-center">
                <div class="flex-1 min-w-[200px] relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-300"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar temas..." class="w-full pl-10 pr-4 py-2 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="min-w-[180px]">
                    <select name="sort" class="w-full px-4 py-2 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="recientes" <?php echo $sort === 'recientes' ? 'selected' : ''; ?>>M&aacute;s recientes</option>
                        <option value="antiguos" <?php echo $sort === 'antiguos' ? 'selected' : ''; ?>>M&aacute;s antiguos</option>
                    </select>
                </div>
                <?php if ($categoriaFiltro !== ''): ?>
                    <input type="hidden" name="categoria" value="<?php echo htmlspecialchars($categoriaFiltro); ?>">
                <?php endif; ?>
                <button class="bg-blue-600 text-white px-5 py-2 rounded-xl text-xs font-bold">Buscar</button>
            </form>
            <div class="flex flex-wrap gap-2 text-xs">
                <?php
                $allLink = '?' . http_build_query(array_merge($baseParams, ['categoria' => 'Todas']));
                ?>
                <a href="<?php echo $allLink; ?>" class="px-3 py-1 rounded-full font-semibold <?php echo ($categoriaFiltro === '' || strtolower($categoriaFiltro) === 'todas') ? 'bg-blue-600 text-white' : 'bg-slate-50 text-gray-500'; ?>">Todas</a>
                <?php foreach ($categorias as $cat):
                    $catLink = '?' . http_build_query(array_merge($baseParams, ['categoria' => $cat]));
                    $active = $categoriaFiltro === $cat;
                    $catLabel = html_entity_decode((string)$cat, ENT_QUOTES, 'UTF-8');
                ?>
                    <a href="<?php echo $catLink; ?>" class="px-3 py-1 rounded-full font-semibold <?php echo $active ? 'bg-blue-600 text-white' : 'bg-slate-50 text-gray-500'; ?>">
                        <?php echo htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="text-xs text-gray-400 mb-4">Mostrando <?php echo $totalFiltrados; ?> temas</p>

        <div class="space-y-4">
            <?php foreach ($temas as $t):
                $foto = trim((string)($t['foto_perfil'] ?? ''));
                $fotoUrl = $foto !== '' ? uploaded_file_url('perfiles', $foto) : '';
                $hasFoto = $foto !== '' && app_resolve_storage_path('perfiles', $foto) !== null;
                $autor = (string)($t['autor'] ?? '');
                if ($autor !== '') {
                    $initial = function_exists('mb_substr') ? mb_substr($autor, 0, 1, 'UTF-8') : substr($autor, 0, 1);
                    $initial = strtoupper($initial);
                } else {
                    $initial = '?';
                }
            ?>
            <a href="tema_detalle.php?id=<?php echo $t['id']; ?>" class="block bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-md transition group">
                <div class="flex justify-between items-start">
                    <div class="flex items-start space-x-4">
                        <?php if ($hasFoto): ?>
                            <img src="<?php echo htmlspecialchars($fotoUrl); ?>" class="w-10 h-10 rounded-full object-cover bg-slate-100" alt="Foto de perfil">
                        <?php else: ?>
                            <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold">
                                <?php echo htmlspecialchars($initial); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <span class="text-[10px] font-bold uppercase text-blue-500 bg-blue-50 px-2 py-1 rounded">
                                <?php echo $t['categoria']; ?>
                            </span>
                            <h3 class="font-bold text-gray-800 text-lg mt-1 group-hover:text-blue-600 transition">
                                <?php echo htmlspecialchars($t['titulo']); ?>
                            </h3>
                            <p class="text-xs text-gray-400">Publicado por <?php echo htmlspecialchars($t['autor']); ?> &bull; <?php echo date("d M, Y", strtotime($t['creado_at'])); ?></p>
                        </div>
                    </div>
                    <div class="text-gray-300 group-hover:text-blue-500 transition">
                        <i class="fa-solid fa-chevron-right"></i>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </main>

    <?php $openModal = isset($_GET['nuevo']) && $_GET['nuevo'] === '1'; ?>
    <div id="modalTema" class="<?php echo $openModal ? '' : 'hidden'; ?> fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div class="bg-white w-full max-w-lg rounded-3xl p-8 shadow-2xl">
            <h2 class="text-xl font-bold mb-4">Crear Nuevo Tema</h2>
            <form action="<?php echo BASE_URL; ?>/crear_tema.php" method="POST" class="space-y-4">
                <input type="text" name="titulo" placeholder="Título de tu duda fiscal" required class="w-full p-3 bg-slate-50 border-none rounded-xl outline-none focus:ring-2 focus:ring-blue-500">
                <select name="categoria" class="w-full p-3 bg-slate-50 border-none rounded-xl outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="General">Categoría: General</option>
                    <option value="Impuestos">Impuestos</option>
                    <option value="Auditoría">Auditoría</option>
                </select>
                <textarea name="contenido" rows="4" placeholder="Describe tu duda con detalle..." required class="w-full p-3 bg-slate-50 border-none rounded-xl outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                <div class="flex space-x-3">
                    <button type="button" onclick="document.getElementById('modalTema').classList.add('hidden')" class="flex-1 py-3 text-gray-400 font-bold">Cancelar</button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-bold">Publicar</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>



