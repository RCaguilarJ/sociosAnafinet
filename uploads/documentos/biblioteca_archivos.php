<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Consultar solo documentos
$stmt = $pdo->query("SELECT * FROM contenidos WHERE tipo = 'documento' ORDER BY creado_at DESC");
$documentos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Biblioteca de Archivos - Anafinet</title>
</head>
<body class="bg-slate-50 flex flex-col md:flex-row min-h-screen">

    <?php include 'sidebar_template.php'; ?>

    <main class="flex-1 p-8">
        <header class="mb-10">
            <h1 class="text-2xl font-bold text-gray-800">Biblioteca de Archivos</h1>
            <p class="text-gray-500">Consulta y descarga documentos oficiales y recursos fiscales.</p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <?php foreach ($documentos as $doc): ?>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition flex items-center justify-between group">
                <div class="flex items-center space-x-4">
                    <div class="bg-red-50 p-3 rounded-xl text-red-500 group-hover:bg-red-500 group-hover:text-white transition">
                        <i class="fa-solid fa-file-pdf text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($doc['titulo']); ?></h3>
                        <p class="text-xs text-gray-400">Publicado el: <?php echo date("d/m/Y", strtotime($doc['fecha_publicacion'])); ?></p>
                    </div>
                </div>
                
                <a href="uploads/documentos/<?php echo $doc['url_recurso']; ?>" download 
                   class="bg-slate-100 hover:bg-blue-600 hover:text-white p-2 rounded-lg transition text-gray-500">
                    <i class="fa-solid fa-download"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if(empty($documentos)): ?>
            <div class="text-center py-20 bg-white rounded-3xl border border-dashed border-gray-300">
                <i class="fa-regular fa-folder-open text-4xl text-gray-200 mb-4"></i>
                <p class="text-gray-400">No hay documentos disponibles en este momento.</p>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>