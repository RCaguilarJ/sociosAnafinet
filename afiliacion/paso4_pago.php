<?php
// En este paso no solemos pedir inputs, sino mostrar información de pago
// Pero verificamos que los pasos anteriores existan en la sesión
if (!isset($_SESSION['afiliacion']['paso2'])) {
    header("Location: index.php?paso=1");
    exit();
}
?>

<div class="animate-fadeIn">
    <h2 class="text-2xl font-bold text-gray-800 mb-2">Método de Pago</h2>
    <p class="text-gray-500 text-sm mb-8">Paso 5 de 5: Finaliza tu solicitud realizando el pago de tu membresía.</p>

    <div class="bg-blue-50 p-6 rounded-3xl border border-blue-100 mb-8">
        <div class="flex items-center space-x-4 mb-4">
            <div class="bg-blue-600 text-white p-3 rounded-xl">
                <i class="fa-solid fa-building-columns text-xl"></i>
            </div>
            <div>
                <h4 class="font-bold text-blue-900">Transferencia Electrónica (SPEI)</h4>
                <p class="text-xs text-blue-700">Realiza tu pago para activar tu cuenta.</p>
            </div>
        </div>
        
        <div class="space-y-3 bg-white p-5 rounded-2xl shadow-sm">
            <div class="flex justify-between border-b border-gray-50 pb-2">
                <span class="text-xs text-gray-400 font-bold uppercase">Banco</span>
                <span class="text-sm text-gray-700 font-bold">Banamex</span>
            </div>
            <div class="flex justify-between border-b border-gray-50 pb-2">
                <span class="text-xs text-gray-400 font-bold uppercase">Cuenta</span>
                <span class="text-sm text-gray-700 font-bold">1234567890</span>
            </div>
            <div class="flex justify-between border-b border-gray-50 pb-2">
                <span class="text-xs text-gray-400 font-bold uppercase">CLABE</span>
                <span class="text-sm text-gray-700 font-bold">002180012345678901</span>
            </div>
            <div class="flex justify-between">
                <span class="text-xs text-gray-400 font-bold uppercase">Concepto</span>
                <span class="text-sm text-blue-600 font-bold italic"><?php echo $_SESSION['afiliacion']['paso2']['nombre']; ?></span>
            </div>
        </div>
    </div>

    <form action="finalizar_registro.php" method="POST" class="space-y-6">
        <div class="flex items-start space-x-3 p-2">
            <input type="checkbox" required class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <label class="text-xs text-gray-500 leading-relaxed">
                Acepto que mi registro quedará en estado **"Pendiente"** hasta que el administrador verifique el comprobante de pago que enviaré por correo electrónico.
            </label>
        </div>

        <div class="flex flex-col md:flex-row gap-4 pt-5">
            <a href="index.php?paso=4" class="flex-1 text-center py-4 text-gray-500 font-bold hover:text-gray-700 transition-all">
                Anterior
            </a>
            <button type="submit" class="flex-[2] bg-green-600 text-white py-4 rounded-2xl font-bold shadow-lg shadow-green-100 hover:bg-green-700 hover:-translate-y-0.5 transition-all">
                Enviar Solicitud de Afiliación
            </button>
        </div>
    </form>
</div>


