<?php
// Precargamos datos si el usuario regresó desde el paso 4
$datos = $_SESSION['afiliacion']['paso4'] ?? [];
?>

<div class="animate-fadeIn">
    <h2 class="text-2xl font-bold text-gray-800 mb-2">Perfil Profesional</h2>
    <p class="text-gray-500 text-sm mb-8">Paso 4 de 5: Cuéntanos sobre tu trayectoria y especialidad actual.</p>

    <form action="procesar_paso.php?paso=4" method="POST" class="space-y-5">
        
        <div>
            <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Empresa / Despacho / Institución</label>
            <input type="text" name="empresa" required 
                   value="<?php echo $datos['empresa'] ?? ''; ?>"
                   class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                   placeholder="Nombre de tu lugar de trabajo actual">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Puesto Actual</label>
                <input type="text" name="puesto" required 
                       value="<?php echo $datos['puesto'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                       placeholder="Ej. Socio Director, Auditor, etc.">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Especialidad</label>
                <select name="especialidad" required 
                        class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all appearance-none">
                    <option value="">Selecciona una opción</option>
                    <option value="Fiscal" <?php echo ($datos['especialidad'] ?? '') == 'Fiscal' ? 'selected' : ''; ?>>Fiscal</option>
                    <option value="Auditoría" <?php echo ($datos['especialidad'] ?? '') == 'Auditoría' ? 'selected' : ''; ?>>Auditoría</option>
                    <option value="Contabilidad General" <?php echo ($datos['especialidad'] ?? '') == 'Contabilidad General' ? 'selected' : ''; ?>>Contabilidad General</option>
                    <option value="Legal / Abogado" <?php echo ($datos['especialidad'] ?? '') == 'Legal / Abogado' ? 'selected' : ''; ?>>Legal / Abogado</option>
                    <option value="Docencia / Investigación" <?php echo ($datos['especialidad'] ?? '') == 'Docencia / Investigación' ? 'selected' : ''; ?>>Docencia / Investigación</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Número de Cédula Profesional</label>
            <input type="text" name="cedula" required 
                   value="<?php echo $datos['cedula'] ?? ''; ?>"
                   class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                   placeholder="Indispensable para validación de asociado">
        </div>

        <div class="flex flex-col md:flex-row gap-4 pt-5">
            <a href="index.php?paso=3" 
               class="flex-1 text-center py-4 text-gray-500 font-bold hover:text-gray-700 transition-all">
                Anterior
            </a>
            <button type="submit" 
                    class="flex-[2] bg-[#5282B2] text-white py-4 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 hover:-translate-y-0.5 transition-all flex items-center justify-center space-x-2">
                <span>Continuar al Pago</span>
                <i class="fa-solid fa-credit-card text-sm ml-2"></i>
            </button>
        </div>
    </form>
</div>

