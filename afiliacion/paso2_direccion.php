<?php
// Precargamos datos si el usuario regresó desde el paso 3
$datos = $_SESSION['afiliacion']['paso3'] ?? [];
?>

<div class="animate-fadeIn">
    <h2 class="text-2xl font-bold text-gray-800 mb-2">Dirección de Contacto</h2>
    <p class="text-gray-500 text-sm mb-8">Paso 3 de 5: Indica dónde se ubica tu despacho o domicilio fiscal.</p>

    <form action="procesar_paso.php?paso=3" method="POST" class="space-y-5">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Calle</label>
                <input type="text" name="calle" required 
                       value="<?php echo $datos['calle'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                       placeholder="Nombre de la vialidad">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Número</label>
                <input type="text" name="numero" required 
                       value="<?php echo $datos['numero'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                       placeholder="Ext/Int">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Colonia</label>
                <input type="text" name="colonia" required 
                       value="<?php echo $datos['colonia'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                       placeholder="Nombre de la colonia">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">C.P.</label>
                <input type="text" name="cp" required maxlength="5"
                       value="<?php echo $datos['cp'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                       placeholder="5 dígitos">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Ciudad / Municipio</label>
                <input type="text" name="ciudad" required 
                       value="<?php echo $datos['ciudad'] ?? ''; ?>"
                       class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                       placeholder="Ej. Guadalajara">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Estado</label>
                <select name="estado" required 
                        class="w-full p-4 bg-slate-50 border border-transparent rounded-2xl outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all appearance-none">
                    <option value="">Selecciona un estado</option>
                    <?php
                    $estados = ["Aguascalientes", "Baja California", "Baja California Sur", "Campeche", "Chiapas", "Chihuahua", "CDMX", "Coahuila", "Colima", "Durango", "Guanajuato", "Guerrero", "Hidalgo", "Jalisco", "México", "Michoacán", "Morelos", "Nayarit", "Nuevo León", "Oaxaca", "Puebla", "Querétaro", "Quintana Roo", "San Luis Potosí", "Sinaloa", "Sonora", "Tabasco", "Tamaulipas", "Tlaxcala", "Veracruz", "Yucatán", "Zacatecas"];
                    foreach($estados as $e){
                        $selected = ($datos['estado'] ?? '') == $e ? 'selected' : '';
                        echo "<option value='$e' $selected>$e</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-4 pt-5">
            <a href="index.php?paso=2" 
               class="flex-1 text-center py-4 text-gray-500 font-bold hover:text-gray-700 transition-all">
                Anterior
            </a>
            <button type="submit" 
                    class="flex-[2] bg-[#5282B2] text-white py-4 rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 hover:-translate-y-0.5 transition-all flex items-center justify-center space-x-2">
                <span>Siguiente: Perfil Profesional</span>
                <i class="fa-solid fa-arrow-right text-sm"></i>
            </button>
        </div>
    </form>
</div>


