<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$paso = isset($_GET['paso']) ? (int) $_GET['paso'] : 1;
if (!in_array($paso, [1, 2, 4], true)) {
    $paso = 1;
}

$titulos = [
    1 => 'Informacion Personal',
    2 => 'Direccion de Contacto',
    4 => 'Metodo de Pago',
];

$ordenPasos = [1, 2, 4];
$indicePaso = array_search($paso, $ordenPasos, true);
$pasoVisual = $indicePaso === false ? 1 : $indicePaso + 1;
$totalPasos = count($ordenPasos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Afiliacion Anafinet - <?php echo $titulos[$paso] ?? 'Solicitud'; ?></title>
</head>
<body class="bg-slate-100 min-h-screen">

    <div class="max-w-2xl mx-auto pt-10 pb-20 px-4">
        <img src="<?php echo BASE_URL; ?>/logo_anafinet_favicon.png" class="h-16 mx-auto mb-8" alt="Anafinet">

        <div class="mb-8">
            <div class="flex justify-between mb-2">
                <?php foreach ($ordenPasos as $index => $numeroPaso): ?>
                    <span class="text-[10px] font-bold uppercase <?php echo $pasoVisual >= ($index + 1) ? 'text-blue-600' : 'text-gray-400'; ?>">
                        Paso <?php echo $index + 1; ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="w-full bg-gray-200 h-1.5 rounded-full">
                <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500" style="width: <?php echo ($pasoVisual / $totalPasos) * 100; ?>%"></div>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl p-8 md:p-12 border border-gray-100">
            <?php
            switch ($paso) {
                case 1:
                    include 'paso1_personal.php';
                    break;
                case 2:
                    include 'paso2_direccion.php';
                    break;
                case 4:
                    include 'paso4_pago.php';
                    break;
                default:
                    include 'paso1_personal.php';
                    break;
            }
            ?>
        </div>

        <p class="text-center text-gray-400 text-xs mt-8">(c) 2026 Anafinet A.C. - Todos los derechos reservados.</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const invalidMessages = {
                valueMissing: function (field) {
                    if (field.type === 'checkbox') {
                        return 'Debes aceptar este campo para continuar.';
                    }

                    if (field.type === 'radio') {
                        return 'Selecciona una opcion para continuar.';
                    }

                    if (field.tagName === 'SELECT') {
                        return 'Selecciona una opcion valida.';
                    }

                    return 'Completa este campo.';
                },
                typeMismatch: function (field) {
                    if (field.type === 'email') {
                        return 'Ingresa un correo electronico valido.';
                    }

                    if (field.type === 'url') {
                        return 'Ingresa una URL valida.';
                    }

                    return 'El formato capturado no es valido.';
                },
                tooShort: function (field) {
                    return 'Ingresa al menos ' + field.minLength + ' caracteres.';
                },
                tooLong: function (field) {
                    return 'No excedas los ' + field.maxLength + ' caracteres.';
                },
                patternMismatch: function () {
                    return 'El formato capturado no es valido.';
                },
                rangeUnderflow: function (field) {
                    return 'Ingresa un valor mayor o igual a ' + field.min + '.';
                },
                rangeOverflow: function (field) {
                    return 'Ingresa un valor menor o igual a ' + field.max + '.';
                },
                stepMismatch: function () {
                    return 'Ingresa un valor valido.';
                }
            };

            const syncCustomRules = function (field) {
                if (field.type === 'email' && field.name === 'email') {
                    const value = field.value.trim().toLowerCase();
                    if (value !== '' && !value.endsWith('.com')) {
                        field.setCustomValidity('Ingresa un correo electronico valido que termine en .com.');
                        return false;
                    }
                }

                field.setCustomValidity('');
                return true;
            };

            const getMessage = function (field) {
                const validity = field.validity;

                for (const key in invalidMessages) {
                    if (validity[key]) {
                        return invalidMessages[key](field);
                    }
                }

                return 'Revisa este campo.';
            };

            document.querySelectorAll('form').forEach(function (form) {
                form.querySelectorAll('input, select, textarea').forEach(function (field) {
                    field.addEventListener('invalid', function () {
                        if (!syncCustomRules(field)) {
                            return;
                        }
                        field.setCustomValidity(getMessage(field));
                    });

                    field.addEventListener('input', function () {
                        syncCustomRules(field);
                    });

                    field.addEventListener('change', function () {
                        syncCustomRules(field);
                    });
                });
            });
        });
    </script>
</body>
</html>
