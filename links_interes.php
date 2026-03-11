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

$stmt = $pdo->query("SELECT * FROM links_interes ORDER BY categoria, titulo ASC");
$links = $stmt->fetchAll();

$grouped = [];
foreach ($links as $link) {
    $categoria = trim((string)($link['categoria'] ?? ''));
    if ($categoria === '') {
        $categoria = 'Otros';
    }
    if (!isset($grouped[$categoria])) {
        $grouped[$categoria] = [];
    }
    $grouped[$categoria][] = $link;
}

$categoryStyles = [
    'instituciones fiscales' => ['bg' => 'bg-blue-600', 'icon' => 'fa-building-columns'],
    'legislacion y normatividad' => ['bg' => 'bg-orange-500', 'icon' => 'fa-scale-balanced'],
    'herramientas y calculadoras' => ['bg' => 'bg-purple-600', 'icon' => 'fa-calculator'],
    'recursos educativos' => ['bg' => 'bg-green-600', 'icon' => 'fa-graduation-cap'],
    'organismos internacionales' => ['bg' => 'bg-teal-600', 'icon' => 'fa-globe'],
    'publicaciones especializadas' => ['bg' => 'bg-pink-600', 'icon' => 'fa-book'],
];

function normalize_category(string $categoria): string
{
    $key = strtolower(trim($categoria));
    if ($key === '') {
        return 'otros';
    }
    if (function_exists('iconv')) {
        $trans = iconv('UTF-8', 'ASCII//TRANSLIT', $key);
        if ($trans !== false) {
            $key = $trans;
        }
    }
    $key = preg_replace('/[^a-z0-9 ]/', '', $key);
    $key = trim(preg_replace('/\s+/', ' ', $key));
    return $key === '' ? 'otros' : $key;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Links de Inter&eacute;s - Anafinet</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'links';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-8">
        <header class="mb-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Links de Inter&eacute;s</h1>
                <p class="text-gray-500">Acceso r&aacute;pido a portales oficiales y herramientas fiscales.</p>
            </div>
            <?php if ($isAdmin): ?>
                <a href="links_interes_admin.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition">
                    <i class="fa-solid fa-pen-to-square"></i> Administrar Links
                </a>
            <?php endif; ?>
        </header>

        <?php if (empty($links)): ?>
            <div class="bg-white p-8 rounded-3xl border border-gray-100 text-sm text-gray-400">
                A&uacute;n no hay links cargados.
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($grouped as $categoria => $items):
                    $key = normalize_category($categoria);
                    $style = $categoryStyles[$key] ?? ['bg' => 'bg-slate-700', 'icon' => 'fa-link'];
                ?>
                <section class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="<?php echo $style['bg']; ?> text-white px-6 py-3 flex items-center gap-3">
                        <i class="fa-solid <?php echo $style['icon']; ?>"></i>
                        <h2 class="font-semibold"><?php echo htmlspecialchars($categoria); ?></h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($items as $link):
                            $url = (string)($link['url'] ?? '');
                            $hasUrl = $url !== '' && $url !== '#';
                            $titulo = (string)($link['titulo'] ?? '');
                            $desc = (string)($link['descripcion'] ?? '');
                            $icon = !empty($link['icono']) ? $link['icono'] : 'fa-link';
                        ?>
                        <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid <?php echo htmlspecialchars($icon); ?> text-gray-400 text-sm"></i>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($titulo); ?></p>
                                </div>
                                <?php if ($desc !== ''): ?>
                                    <p class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($desc); ?></p>
                                <?php endif; ?>
                                <?php if ($hasUrl): ?>
                                    <p class="text-[10px] text-gray-400 mt-1 break-all"><?php echo htmlspecialchars($url); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="shrink-0">
                                <?php if ($hasUrl): ?>
                                    <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center gap-2 text-xs font-semibold text-gray-600 hover:text-blue-600">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Visitar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>
            </div>

            <div class="mt-10 border-2 border-dashed border-gray-200 rounded-3xl p-8 text-center bg-white">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4">
                    <i class="fa-solid fa-link"></i>
                </div>
                <h3 class="font-semibold text-gray-800 mb-2">&iquest;Conoces alg&uacute;n recurso &uacute;til?</h3>
                <p class="text-xs text-gray-400 mb-4">Comp&aacute;rtelo con nosotros y lo agregaremos a esta secci&oacute;n.</p>
                <a href="mailto:carlagular800@gmail.com?subject=Sugerir%20link"
                   class="inline-flex items-center justify-center px-5 py-2 rounded-xl bg-orange-500 text-white text-xs font-bold hover:bg-orange-600 transition">
                    Sugerir un Link
                </a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

