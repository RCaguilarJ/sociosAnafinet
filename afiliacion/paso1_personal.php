<?php
$datos = $_SESSION['afiliacion']['paso1'] ?? [];
$mensajeError = $_SESSION['afiliacion_error'] ?? '';
if ($mensajeError !== '') {
    unset($_SESSION['afiliacion_error']);
}
?>

<div class="animate-fadeIn">
    <h2 class="text-2xl font-bold text-gray-800 mb-2">InformaciÃ³n Personal</h2>
    <p class="text-gray-500 text-sm mb-8">Paso 1 de 3: Comencemos con tus datos bÃ¡sicos para el registro de afiliaciÃ³n.</p>

    <?php if ($mensajeError !== ''): ?>
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <?php echo htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo BASE_URL; ?>/afiliacion/procesar_paso.php?paso=1" method="POST" class="space-y-5">
        <div>
            <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Nombre Completo</label>
            <input type="text" name="nombre" required
                   value="<?php echo $datos['nombre'] ?? ''; ?>"
                   class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                   placeholder="Ej. Juan PÃ©rez GarcÃ­a">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">RFC</label>
                <input type="text" name="rfc" required maxlength="13"
                       value="<?php echo $datos['rfc'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all uppercase"
                       placeholder="XXXX000000XXX">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">CURP</label>
                <input type="text" name="curp" required maxlength="18"
                       value="<?php echo $datos['curp'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all uppercase"
                       placeholder="18 caracteres">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Correo ElectrÃ³nico</label>
                <input type="email" name="email" required
                       value="<?php echo $datos['email'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                       placeholder="correo@ejemplo.com">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">TelÃ©fono</label>
                <input type="tel" name="telefono" required
                       value="<?php echo $datos['telefono'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                       placeholder="10 dÃ­gitos">
            </div>
        </div>

        <div class="pt-5">
            <button type="submit"
                    class="w-full bg-[#5282B2] text-white py-4 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 hover:-translate-y-0.5 transition-all flex items-center justify-center">
                <span>Siguiente</span>
            </button>

            <a href="<?php echo BASE_URL; ?>/index.php" class="block text-center mt-4 text-sm text-gray-400 hover:text-gray-600 transition">
                Ya tengo cuenta, quiero iniciar sesiÃ³n
            </a>
        </div>
    </form>
</div>
