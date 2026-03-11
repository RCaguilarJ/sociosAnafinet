<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$temaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($temaId <= 0) {
    header("Location: foro.php");
    exit();
}

$stmtTema = $pdo->prepare("SELECT t.*, u.nombre as autor FROM foro_temas t JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ? LIMIT 1");
$stmtTema->execute([$temaId]);
$tema = $stmtTema->fetch();

if (!$tema) {
    header("Location: foro.php?error=tema_no_encontrado");
    exit();
}

$respPage = isset($_GET['rpage']) ? (int)$_GET['rpage'] : 1;
$respPage = $respPage > 0 ? $respPage : 1;
$respPerPage = 6;

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM foro_respuestas WHERE tema_id = ?");
$stmtCount->execute([$temaId]);
$respTotal = (int)$stmtCount->fetchColumn();
$respPages = max(1, (int)ceil($respTotal / $respPerPage));
if ($respPage > $respPages) {
    $respPage = $respPages;
}
$respOffset = ($respPage - 1) * $respPerPage;

$stmtResp = $pdo->prepare("SELECT r.*, u.nombre as autor FROM foro_respuestas r JOIN usuarios u ON r.usuario_id = u.id WHERE r.tema_id = ? ORDER BY r.creado_at ASC LIMIT ? OFFSET ?");
$stmtResp->bindValue(1, $temaId, PDO::PARAM_INT);
$stmtResp->bindValue(2, $respPerPage, PDO::PARAM_INT);
$stmtResp->bindValue(3, $respOffset, PDO::PARAM_INT);
$stmtResp->execute();
$respuestas = $stmtResp->fetchAll();

$likesCount = 0;
$userLiked = false;
try {
    $stmtLikes = $pdo->prepare("SELECT COUNT(*) FROM foro_likes WHERE tema_id = ?");
    $stmtLikes->execute([$temaId]);
    $likesCount = (int)$stmtLikes->fetchColumn();

    $stmtLiked = $pdo->prepare("SELECT id FROM foro_likes WHERE tema_id = ? AND usuario_id = ? LIMIT 1");
    $stmtLiked->execute([$temaId, $_SESSION['user_id']]);
    $userLiked = (bool)$stmtLiked->fetchColumn();
} catch (Throwable $e) {
    $likesCount = 0;
    $userLiked = false;
}

$status = $_GET['status'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Tema - Foro Fiscal</title>
</head>
<body class="bg-slate-50 min-h-screen">
    <?php
    $activePage = 'foro';
    require 'menu.php';
    ?>

    <main class="md:ml-64 p-8">
        <header class="mb-6">
            <a href="<?php echo BASE_URL; ?>/foro.php" class="text-xs text-blue-600 font-semibold inline-flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Volver al foro
            </a>
        </header>

        <?php if ($status === 'respuesta_creada'): ?>
            <div class="mb-4 p-3 rounded-lg text-sm bg-green-50 text-green-600">Respuesta publicada correctamente.</div>
        <?php elseif ($status === 'like_added'): ?>
            <div class="mb-4 p-3 rounded-lg text-sm bg-green-50 text-green-600">Marcaste este tema como favorito.</div>
        <?php elseif ($status === 'like_removed'): ?>
            <div class="mb-4 p-3 rounded-lg text-sm bg-slate-50 text-gray-500">Quitaste tu me gusta.</div>
        <?php elseif (($error !== '') || $status === 'like_error'): ?>
            <div class="mb-4 p-3 rounded-lg text-sm bg-red-50 text-red-600">No se pudo completar la acci&oacute;n.</div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400">
                    <span class="bg-blue-50 text-blue-600 px-2 py-1 rounded font-semibold"><?php echo htmlspecialchars((string)$tema['categoria']); ?></span>
                    <span>Publicado por <?php echo htmlspecialchars((string)$tema['autor']); ?></span>
                    <span>&bull;</span>
                    <span><?php echo date("d M, Y", strtotime((string)$tema['creado_at'])); ?></span>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <span><i class="fa-regular fa-comment-dots mr-1"></i> <?php echo $respTotal; ?> respuestas</span>
                    <form action="<?php echo BASE_URL; ?>/toggle_like.php" method="POST">
                        <input type="hidden" name="tema_id" value="<?php echo (int)$temaId; ?>">
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars("tema_detalle.php?id={$temaId}&rpage={$respPage}#respuestas"); ?>">
                        <button class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold <?php echo $userLiked ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-gray-600'; ?>">
                            <i class="fa-<?php echo $userLiked ? 'solid' : 'regular'; ?> fa-heart"></i>
                            <?php echo $likesCount; ?>
                        </button>
                    </form>
                </div>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars((string)$tema['titulo']); ?></h1>
            <div class="text-sm text-gray-600 leading-relaxed">
                <?php echo nl2br(htmlspecialchars((string)$tema['contenido'])); ?>
            </div>
        </div>

        <div class="mt-8" id="respuestas">
            <h2 class="text-sm font-bold text-gray-500 uppercase mb-4">Respuestas (<?php echo $respTotal; ?>)</h2>

            <?php if (empty($respuestas)): ?>
                <div class="bg-white p-6 rounded-2xl border border-gray-100 text-sm text-gray-400">
                    A&uacute;n no hay respuestas en este tema.
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($respuestas as $resp): ?>
                        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm ml-4 md:ml-10">
                            <div class="flex items-center gap-3 text-xs text-gray-400 mb-2">
                                <span class="font-semibold text-gray-600"><?php echo htmlspecialchars((string)$resp['autor']); ?></span>
                                <span>&bull;</span>
                                <span><?php echo date("d M, Y H:i", strtotime((string)$resp['creado_at'])); ?></span>
                            </div>
                            <div class="text-sm text-gray-600 leading-relaxed">
                                <?php echo nl2br(htmlspecialchars((string)$resp['respuesta'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($respPages > 1): ?>
                <div class="mt-6 flex items-center justify-center gap-2">
                    <?php
                    $start = max(1, $respPage - 2);
                    $end = min($respPages, $start + 4);
                    if (($end - $start + 1) < 5) {
                        $start = max(1, $end - 4);
                    }
                    ?>
                    <?php for ($p = $start; $p <= $end; $p++): ?>
                        <a href="<?php echo "tema_detalle.php?id={$temaId}&rpage={$p}#respuestas"; ?>"
                           class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold border <?php echo $p === $respPage ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-200 text-gray-500 hover:bg-gray-50'; ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-10 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <h3 class="font-bold text-gray-800 mb-4">Responder</h3>
            <form action="<?php echo BASE_URL; ?>/responder_tema.php" method="POST" class="space-y-4">
                <input type="hidden" name="tema_id" value="<?php echo (int)$temaId; ?>">
                <textarea name="contenido" rows="4" placeholder="Escribe tu respuesta..." required class="w-full p-3 bg-slate-50 border-none rounded-xl outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                <button class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold">Publicar Respuesta</button>
            </form>
        </div>
    </main>
</body>
</html>



