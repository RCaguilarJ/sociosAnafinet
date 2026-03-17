<?php
// Precargamos datos si el usuario regresó desde el paso 2
$datos = $_SESSION['afiliacion']['paso1'] ?? [];
$rolSeleccionado = $datos['rol_solicitado'] ?? '';
?>

<div class="animate-fadeIn">
    <h2 class="text-2xl font-bold text-gray-800 mb-2">Selecciona tu Perfil</h2>
    <p class="text-gray-500 text-sm mb-8">Paso 1 de 5: Elige el perfil que mejor se adapte a tu situación profesional.</p>

    <form action="procesar_paso.php?paso=1" method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php
            $perfiles = [
                [
                    'value' => 'Profesionista',
                    'icon' => 'fa-briefcase',
                    'color' => 'bg-blue-600',
                    'title' => 'Profesionista',
                    'desc' => 'Responsable del área de impuestos en empresas'
                ],
                [
                    'value' => 'Especialista',
                    'icon' => 'fa-shield',
                    'color' => 'bg-purple-600',
                    'title' => 'Especialista',
                    'desc' => 'Profesional en servicios y consultoría fiscal'
                ],
                [
                    'value' => 'Docente',
                    'icon' => 'fa-chalkboard-user',
                    'color' => 'bg-green-600',
                    'title' => 'Docente',
                    'desc' => 'Consultor, auditor o académico fiscal'
                ],
                [
                    'value' => 'Estudiante',
                    'icon' => 'fa-graduation-cap',
                    'color' => 'bg-orange-600',
                    'title' => 'Estudiante',
                    'desc' => 'Licenciatura en áreas afines al objeto fiscal'
                ],
                [
                    'value' => 'Persona Moral',
                    'icon' => 'fa-building',
                    'color' => 'bg-indigo-600',
                    'title' => 'Persona Moral',
                    'desc' => 'Autorizada conforme a Estatutos'
                ],
            ];

            foreach ($perfiles as $index => $perfil):
                $checked = $rolSeleccionado === $perfil['value'] ? 'checked' : '';
                $required = $index === 0 ? 'required' : '';
            ?>
                <label class="border border-gray-200 rounded-2xl p-4 flex items-start gap-4 cursor-pointer transition hover:border-blue-400">
                    <input type="radio" name="rol_solicitado" value="<?php echo $perfil['value']; ?>" class="sr-only peer" <?php echo $checked; ?> <?php echo $required; ?>>
                    <div class="<?php echo $perfil['color']; ?> text-white p-3 rounded-xl">
                        <i class="fa-solid <?php echo $perfil['icon']; ?> text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-gray-800"><?php echo $perfil['title']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo $perfil['desc']; ?></p>
                    </div>
                    <div class="w-4 h-4 rounded-full border-2 border-gray-300 mt-1 peer-checked:border-blue-500 peer-checked:bg-blue-500"></div>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="flex flex-col md:flex-row gap-4 pt-5">
            <a href="../index.php" class="flex-1 text-center py-4 text-gray-500 font-bold hover:text-gray-700 transition-all">
                Volver
            </a>
            <button type="submit"
                    class="flex-[2] bg-[#5282B2] text-white py-4 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 hover:-translate-y-0.5 transition-all flex items-center justify-center space-x-2">
                <span>Siguiente: Información Personal</span>
                <i class="fa-solid fa-arrow-right text-sm"></i>
            </button>
        </div>
    </form>
</div>
