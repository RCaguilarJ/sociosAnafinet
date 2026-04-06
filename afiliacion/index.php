<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Detectar en quÃ© paso estamos
$paso = isset($_GET['paso']) ? (int)$_GET['paso'] : 1;

// TÃ­tulos dinÃ¡micos segÃºn el flujo de tus imÃ¡genes
$titulos = [
    1 => "SelecciÃ³n de Perfil",
    2 => "InformaciÃ³n Personal",
    3 => "DirecciÃ³n de Contacto",
    4 => "Perfil Profesional",
    5 => "MÃ©todo de Pago"
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>AfiliaciÃ³n Anafinet - <?php echo $titulos[$paso] ?? 'Solicitud'; ?></title>
</head>
<body class="bg-slate-100 min-h-screen">

    <div class="max-w-2xl mx-auto pt-10 pb-20 px-4">
        <img src="<?php echo BASE_URL; ?>/logo_anafinet_favicon.png" class="h-16 mx-auto mb-8" alt="Anafinet">

        <div class="mb-8">
            <div class="flex justify-between mb-2">
                <?php foreach($titulos as $num => $txt): ?>
                    <span class="text-[10px] font-bold uppercase <?php echo $paso >= $num ? 'text-blue-600' : 'text-gray-400'; ?>">
                        Paso <?php echo $num; ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="w-full bg-gray-200 h-1.5 rounded-full">
                <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500" style="width: <?php echo ($paso / 5) * 100; ?>%"></div>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl p-8 md:p-12 border border-gray-100">
            <?php
                // Carga dinÃ¡mica del contenido del paso
                switch($paso) {
                    case 1: include 'paso0_perfil.php'; break;
                    case 2: include 'paso1_personal.php'; break;
                    case 3: include 'paso2_direccion.php'; break;
                    case 4: include 'paso3_profesional.php'; break;
                    case 5: include 'paso4_pago.php'; break;
                    default: include 'paso0_perfil.php';
                }
            ?>
        </div>

        <p class="text-center text-gray-400 text-xs mt-8">Â© 2026 Anafinet A.C. - Todos los derechos reservados.</p>
    </div>

</body>
</html>
