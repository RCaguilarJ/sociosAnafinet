<?php
session_start();
require 'db.php';
require_once 'role_helpers.php';

$docsDir = __DIR__ . '/uploads/documentos';
$files = [];
$allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

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

try {
    $stmt = $pdo->query("SELECT * FROM contenidos WHERE tipo = 'documento' ORDER BY creado_at DESC");
    $documentos = $stmt->fetchAll();
} catch (Throwable $e) {
    $documentos = [];
}

foreach ($documentos as $doc) {
    $fileName = basename((string)($doc['url_recurso'] ?? ''));
    if ($fileName === '') {
        continue;
    }
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        continue;
    }
    $path = $docsDir . DIRECTORY_SEPARATOR . $fileName;
    $title = trim((string)($doc['titulo'] ?? ''));
    $title = $title !== '' ? $title : $fileName;
    $timestamp = 0;
    if (is_file($path)) {
        $timestamp = filemtime($path);
    } elseif (!empty($doc['fecha_publicacion'])) {
        $timestamp = strtotime((string)$doc['fecha_publicacion']) ?: 0;
    } elseif (!empty($doc['creado_at'])) {
        $timestamp = strtotime((string)$doc['creado_at']) ?: 0;
    }
    $files[] = [
        'name' => $fileName,
        'title' => $title,
        'tema' => $doc['tema'] ?? '',
        'url' => 'uploads/documentos/' . rawurlencode($fileName),
        'size' => is_file($path) ? filesize($path) : null,
        'mtime' => $timestamp,
        'ext' => $ext,
    ];
}

if (empty($files) && is_dir($docsDir)) {
    $entries = scandir($docsDir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $docsDir . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            continue;
        }
        $files[] = [
            'name' => $entry,
            'title' => $entry,
            'tema' => '',
            'url' => 'uploads/documentos/' . rawurlencode($entry),
            'size' => filesize($path),
            'mtime' => filemtime($path),
            'ext' => $ext,
        ];
    }
}

usort($files, function ($a, $b) {
    return ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0);
});

function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $value = $bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return number_format($value, 2) . ' ' . $units[$i];
}

function normalize_topic_key(string $value, array $topicOptions): string
{
    $raw = trim($value);
    if ($raw === '') {
        return '';
    }
    $lower = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $lower);
    if ($normalized === false) {
        $normalized = $lower;
    }
    $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

    foreach ($topicOptions as $key => $label) {
        $keyNorm = preg_replace('/[^a-z0-9]+/', '', strtolower($key));
        $labelNorm = iconv('UTF-8', 'ASCII//TRANSLIT', $label);
        if ($labelNorm === false) {
            $labelNorm = $label;
        }
        $labelNorm = preg_replace('/[^a-z0-9]+/', '', strtolower($labelNorm));

        if ($normalized === $keyNorm || $normalized === $labelNorm) {
            return $key;
        }
    }

    return '';
}

function infer_category_key(string $name): string
{
    $lower = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $lower);
    if ($normalized === false) {
        $normalized = $lower;
    }

    if (str_contains($normalized, 'ley') || str_contains($normalized, 'reglamento')) {
        return 'leyes';
    }
    if (str_contains($normalized, 'formato')) {
        return 'formatos';
    }
    if (str_contains($normalized, 'guia')) {
        return 'guias';
    }
    if (str_contains($normalized, 'boletin')) {
        return 'boletines';
    }
    if (str_contains($normalized, 'circular')) {
        return 'circulares';
    }
    if (str_contains($normalized, 'caso') || str_contains($normalized, 'practico')) {
        return 'casos';
    }

    return 'todos';
}

function category_label(string $key, array $topicOptions): string
{
    return $topicOptions[$key] ?? 'Todos';
}

function file_icon_data(string $ext): array
{
    switch ($ext) {
        case 'pdf':
            return ['icon' => 'fa-file-pdf', 'bg' => 'bg-red-100', 'text' => 'text-red-600'];
        case 'doc':
        case 'docx':
            return ['icon' => 'fa-file-word', 'bg' => 'bg-blue-100', 'text' => 'text-blue-600'];
        case 'xls':
        case 'xlsx':
            return ['icon' => 'fa-file-excel', 'bg' => 'bg-green-100', 'text' => 'text-green-600'];
        default:
            return ['icon' => 'fa-file-lines', 'bg' => 'bg-slate-100', 'text' => 'text-slate-600'];
    }
}

$availableTopicKeys = [];
foreach ($files as &$file) {
    $displayTitle = $file['title'] ?? $file['name'];
    $temaKey = normalize_topic_key((string)($file['tema'] ?? ''), $topicOptions);
    $categoryKey = $temaKey !== '' ? $temaKey : infer_category_key($displayTitle);
    $file['display_title'] = $displayTitle;
    $file['category_key'] = $categoryKey;
    if ($categoryKey !== 'todos') {
        $availableTopicKeys[$categoryKey] = true;
    }
}
unset($file);

$topics = [
    ['key' => 'todos', 'label' => 'Todos'],
];
foreach ($topicOptions as $key => $label) {
    if (empty($availableTopicKeys) || isset($availableTopicKeys[$key])) {
        $topics[] = ['key' => $key, 'label' => $label];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com?plugins=line-clamp"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Biblioteca de Archivos - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'archivos';
    require 'menu.php';
    ?>

    <main class="md:ml-64 max-w-7xl mx-auto p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-1">Biblioteca de Archivos</h1>
        <p class="text-gray-500 mb-6">Descarga documentos, formatos, gu&iacute;as y recursos fiscales actualizados</p>

        <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input id="docSearch" type="text" placeholder="Buscar documentos..." class="w-full border border-gray-200 rounded-xl pl-11 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div class="flex items-center gap-3">
                    <button class="inline-flex items-center gap-2 px-4 py-3 rounded-xl border border-gray-200 text-sm text-gray-600">
                        <i class="fa-solid fa-filter"></i>
                        Filtrar
                    </button>
                    <select id="sortOrder" class="border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-700">
                        <option value="recent">M&aacute;s recientes</option>
                        <option value="old">M&aacute;s antiguos</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 mt-5">
                <?php foreach ($topics as $topic):
                    $isActive = $topic['key'] === 'todos';
                ?>
                    <button class="doc-chip px-4 py-2 rounded-xl text-sm border <?php echo $isActive ? 'bg-blue-600 border-blue-600 text-white' : 'border-gray-200 text-gray-600 hover:bg-gray-50'; ?>"
                            data-category="<?php echo htmlspecialchars($topic['key']); ?>">
                        <?php echo $topic['label']; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (!is_dir($docsDir)): ?>
            <p class="text-gray-400">No se encontr&oacute; la carpeta de documentos.</p>
        <?php elseif (empty($files)): ?>
            <p class="text-gray-400">No hay documentos disponibles en este momento.</p>
        <?php else: ?>
        <div id="docsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($files as $file):
                $displayTitle = $file['display_title'] ?? $file['name'];
                $categoryKey = $file['category_key'] ?? 'todos';
                $iconData = file_icon_data($file['ext'] ?? '');
                $searchText = function_exists('mb_strtolower')
                    ? mb_strtolower($displayTitle . ' ' . $file['name'], 'UTF-8')
                    : strtolower($displayTitle . ' ' . $file['name']);
                $logUrl = 'registrar_actividad.php?tipo=documento_descargado&detalle=' . rawurlencode($displayTitle);
            ?>
            <div class="doc-card bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex flex-col gap-4"
                 data-name="<?php echo htmlspecialchars($searchText); ?>"
                 data-category="<?php echo htmlspecialchars($categoryKey); ?>"
                 data-mtime="<?php echo (int)($file['mtime'] ?? 0); ?>">
                <div class="flex items-start gap-4">
                    <span class="w-12 h-12 rounded-xl <?php echo $iconData['bg']; ?> <?php echo $iconData['text']; ?> flex items-center justify-center">
                        <i class="fa-regular <?php echo $iconData['icon']; ?> text-xl"></i>
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-800 text-sm line-clamp-2">
                            <?php echo htmlspecialchars($displayTitle); ?>
                        </h3>
                        <?php if ($categoryKey !== 'todos'): ?>
                            <p class="text-xs text-blue-600 mt-1"><?php echo category_label($categoryKey, $topicOptions); ?></p>
                        <?php endif; ?>
                        <?php if (is_numeric($file['size'])): ?>
                            <p class="text-xs text-gray-400 mt-1"><?php echo format_bytes((int)$file['size']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?php echo htmlspecialchars($file['url']); ?>" download data-log-url="<?php echo htmlspecialchars($logUrl); ?>"
                   class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50 transition">
                    <i class="fa-solid fa-download text-xs"></i> Descargar
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <p id="noResults" class="text-gray-400 mt-6 hidden">No hay documentos que coincidan con tu b&uacute;squeda.</p>
        <?php endif; ?>

        <section class="mt-10 bg-white border border-gray-100 rounded-2xl p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">&iquest;Necesitas un documento o propuesta?</h3>
                <?php if ($isAdmin): ?>
                    <p class="text-sm text-gray-500">Gestiona y publica documentos directamente desde esta secci&oacute;n.</p>
                <?php else: ?>
                    <p class="text-sm text-gray-500">Escr&iacute;benos para solicitar documentos o recursos adicionales.</p>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($isAdmin): ?>
                    <a href="subir_archivo.php" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Subir documento
                    </a>
                <?php else: ?>
                    <a href="mailto:carlagular800@gmail.com?subject=Solicitud%20de%20documento" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-50 transition">
                        <i class="fa-regular fa-envelope"></i> Solicitar documento
                    </a>
                    <p class="text-xs text-gray-400 mt-2">carlagular800@gmail.com</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

<?php if (!empty($files)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('docSearch');
    const sortSelect = document.getElementById('sortOrder');
    const chips = Array.from(document.querySelectorAll('.doc-chip'));
    const grid = document.getElementById('docsGrid');
    const cards = Array.from(document.querySelectorAll('.doc-card'));
    const noResults = document.getElementById('noResults');
    const logLinks = Array.from(document.querySelectorAll('[data-log-url]'));

    let activeCategory = 'todos';

    const setActiveChip = (category) => {
        activeCategory = category;
        chips.forEach(chip => {
            const isActive = chip.dataset.category === category;
            chip.classList.toggle('bg-blue-600', isActive);
            chip.classList.toggle('border-blue-600', isActive);
            chip.classList.toggle('text-white', isActive);
            chip.classList.toggle('border-gray-200', !isActive);
            chip.classList.toggle('text-gray-600', !isActive);
        });
    };

    const applyFilters = () => {
        const query = searchInput.value.trim().toLowerCase();
        const order = sortSelect.value;

        const filtered = cards.filter(card => {
            const name = card.dataset.name || '';
            const category = card.dataset.category || 'todos';
            const matchCategory = activeCategory === 'todos' || category === activeCategory;
            const matchQuery = query === '' || name.includes(query);
            return matchCategory && matchQuery;
        });

        filtered.sort((a, b) => {
            const aTime = Number(a.dataset.mtime || 0);
            const bTime = Number(b.dataset.mtime || 0);
            return order === 'old' ? aTime - bTime : bTime - aTime;
        });

        cards.forEach(card => card.classList.add('hidden'));
        filtered.forEach(card => {
            card.classList.remove('hidden');
            grid.appendChild(card);
        });

        if (noResults) {
            noResults.classList.toggle('hidden', filtered.length > 0);
        }
    };

    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            setActiveChip(chip.dataset.category || 'todos');
            applyFilters();
        });
    });

    searchInput.addEventListener('input', applyFilters);
    sortSelect.addEventListener('change', applyFilters);

    logLinks.forEach(link => {
        link.addEventListener('click', () => {
            const url = link.getAttribute('data-log-url');
            if (!url) return;
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url);
            } else {
                fetch(url, { method: 'POST', keepalive: true });
            }
        });
    });

    applyFilters();
});
</script>
<?php endif; ?>
</body>
</html>
