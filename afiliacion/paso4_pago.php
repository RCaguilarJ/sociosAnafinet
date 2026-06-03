<?php
// Verificaciones de pasarelas del .env (para el modo de demostración visual)
$mpHabilitado = function_exists('app_mercadopago_enabled') ? app_mercadopago_enabled() : false;
$ppHabilitado = function_exists('app_paypal_enabled') ? app_paypal_enabled() : false;

$montoTotal = "1,500.00";
$conceptoPago = "Membresía Anafinet · Cuota de Afiliación";
?>

<style>
    .tab-active {
        background-color: #ffffff !important;
        color: #009EE3 !important;
        border-bottom: 3px solid #009EE3 !important;
    }
    .tab-active.paypal-theme {
        color: #003087 !important;
        border-bottom: 3px solid #003087 !important;
    }
</style>

<div class="text-left">
    <h2 class="text-2xl font-bold text-slate-800">Método de Pago</h2>
    <p class="text-sm text-slate-500 mt-1 mb-6">Paso 3 de 3: completa el pago de tu afiliación. Al aprobarse, tu cuenta se crea automáticamente y se enlaza con la membresía.</p>

    <!-- Tarjeta Principal del Formulario Integrado -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-xl mx-auto">
        
        <!-- Resumen del Monto -->
        <div class="bg-slate-50 border-b border-slate-200 p-6 flex justify-between items-center">
            <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">CUOTA DE AFILIACION</span>
                <div class="text-2xl font-black text-slate-900 mt-1">
                    $<?= $montoTotal ?> <span class="text-base font-semibold text-slate-500">MXN</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5"><?= $conceptoPago ?></p>
            </div>
            <span class="px-3 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full">
                Pago seguro
            </span>
        </div>

        <!-- Selector de Métodos (Pestañas) -->
        <div class="flex border-b border-slate-200 bg-slate-50">
            <button type="button" id="tab-mp" onclick="switchStepPayment('mp')" class="flex-1 py-3 text-sm font-bold flex items-center justify-center gap-2 border-r border-slate-200 text-slate-400 transition-all tab-active">
                <svg class="w-6 h-5" viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="50" height="32" rx="5" fill="#009EE3"/>
                    <path d="M8 22l4-10 3 5.5 2.5-3.5 4 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <circle cx="34" cy="16" r="5" fill="white"/>
                </svg>
                Mercado Pago
            </button>
            <button type="button" id="tab-paypal" onclick="switchStepPayment('paypal')" class="flex-1 py-3 text-sm font-bold flex items-center justify-center gap-2 text-slate-400 transition-all">
                <svg class="w-6 h-5" viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="50" height="32" rx="5" fill="#003087"/>
                    <path d="M14 7h8c4 0 6.5 2 6 5.5C27.5 17 25 19 21.5 19h-3.5l-1.5 6H12L14 7z" fill="#009CDE"/>
                </svg>
                PayPal
            </button>
        </div>

        <!-- Checkbox de Autorización Obligatorio del Registro -->
        <div class="p-6 bg-slate-50 border-b border-slate-100">
            <label class="flex items-start gap-3 cursor-pointer select-none">
                <input type="checkbox" id="auth-check" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span class="text-xs text-slate-600 leading-relaxed">
                    Acepto completar mi registro y autorizo que el estado de mi cuenta se actualice con base en la respuesta de la pasarela seleccionada.
                </span>
            </label>
        </div>

        <!-- PANEL 1: MERCADO PAGO -->
        <div id="panel-mp" class="step-payment-panel p-6 block">
            <?php if (!$mpHabilitado): ?>
                <div class="mb-4 p-2.5 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between">
                    <span class="text-xs font-semibold text-amber-800">Modo Demostración Ilustrativa</span>
                    <span class="px-2 py-0.5 text-[10px] uppercase font-bold bg-amber-200 text-amber-900 rounded-full">Muestra</span>
                </div>
            <?php endif; ?>

            <div class="flex gap-2 mb-4 opacity-75">
                <span class="px-2 py-0.5 text-[10px] font-bold border border-slate-200 rounded bg-slate-100 text-slate-600">VISA</span>
                <span class="px-2 py-0.5 text-[10px] font-bold border border-slate-200 rounded bg-slate-100 text-slate-600">MC</span>
                <span class="px-2 py-0.5 text-[10px] font-bold border border-slate-200 rounded bg-slate-100 text-slate-600">AMEX</span>
                <span class="px-2 py-0.5 text-[10px] font-bold border border-slate-200 rounded bg-emerald-50 text-emerald-700 border-emerald-200">OXXO</span>
            </div>

            <form action="procesar_paso.php" method="POST" id="form-mp" class="space-y-4" onsubmit="return validateAuth(event)">
                <input type="hidden" name="metodo_pago" value="mercadopago">
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Número de Tarjeta</label>
                    <input type="text" placeholder="0000 0000 0000 0000" oninput="formatCardNum(this)" maxlength="19" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" required <?= !$mpHabilitado ? 'disabled value="4556 7812 9011 4452"' : '' ?>>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre del Titular</label>
                    <input type="text" placeholder="Como aparece en el plástico" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" required <?= !$mpHabilitado ? 'disabled value="JUAN PÉREZ LOZANO"' : '' ?>>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Expiración</label>
                        <input type="text" placeholder="MM/AA" oninput="formatExp(this)" maxlength="5" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" required <?= !$mpHabilitado ? 'disabled value="12/29"' : '' ?>>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">CVV</label>
                        <input type="password" placeholder="•••" maxlength="4" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" required <?= !$mpHabilitado ? 'disabled value="123"' : '' ?>>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Plazos <span class="ml-1 text-[10px] bg-emerald-100 text-emerald-700 font-bold px-1.5 py-0.5 rounded-full">MSI</span></label>
                    <select class="w-full px-4 py-2 rounded-xl border border-slate-200 text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" <?= !$mpHabilitado ? 'disabled' : '' ?>>
                        <option>1 Solo pago de $1,500.00</option>
                        <option>3 Mensualidades de $500.00 sin intereses</option>
                        <option>6 Mensualidades de $250.00 sin intereses</option>
                    </select>
                </div>

                <button type="submit" class="w-full py-3 bg-[#009EE3] text-white font-bold rounded-xl transition-all shadow-md shadow-blue-100 flex items-center justify-center gap-2" <?= !$mpHabilitado ? 'disabled opacity-60 cursor-not-allowed' : '' ?>>
                    Pagar con Mercado Pago
                </button>
            </form>
        </div>

        <!-- PANEL 2: PAYPAL -->
        <div id="panel-paypal" class="step-payment-panel p-6 hidden">
            <?php if (!$ppHabilitado): ?>
                <div class="mb-4 p-2.5 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between">
                    <span class="text-xs font-semibold text-amber-800">Modo Demostración Ilustrativa</span>
                    <span class="px-2 py-0.5 text-[10px] uppercase font-bold bg-amber-200 text-amber-900 rounded-full">Muestra</span>
                </div>
            <?php endif; ?>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-center mb-4">
                <div class="flex justify-center mb-2">
                    <span class="text-xl font-black italic text-[#003087]">Pay<span class="text-[#009CDE]">Pal</span></span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Serás redirigido a la ventana segura de PayPal para finalizar tu transacción. Tu cuenta quedará vinculada al instante.</p>
            </div>

            <form action="procesar_paso.php" method="POST" id="form-paypal" onsubmit="return validateAuth(event)">
                <input type="hidden" name="metodo_pago" value="paypal">
                
                <div class="field mb-4">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Correo de tu Cuenta PayPal</label>
                    <input type="email" placeholder="correo@ejemplo.com" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all" <?= !$ppHabilitado ? 'disabled' : '' ?>>
                </div>

                <?php if ($ppHabilitado): ?>
                    <div id="paypal-button-container" class="w-full"></div>
                <?php else: ?>
                    <button type="submit" class="w-full py-3 bg-[#003087] text-white font-bold rounded-xl opacity-60 cursor-not-allowed flex items-center justify-center gap-2" disabled>
                        Continuar con PayPal
                    </button>
                <?php endif; ?>
            </form>
        </div>

    </div>
</div>

<script>
    function switchStepPayment(method) {
        document.querySelectorAll('.step-payment-panel').forEach(p => p.classList.replace('block', 'hidden'));
        document.getElementById('tab-mp').classList.remove('tab-active');
        document.getElementById('tab-paypal').classList.remove('tab-active', 'paypal-theme');

        document.getElementById('panel-' + method).classList.replace('hidden', 'block');
        const activeTab = document.getElementById('tab-' + method);
        
        if (method === 'paypal') {
            activeTab.classList.add('tab-active', 'paypal-theme');
        } else {
            activeTab.classList.add('tab-active');
        }
    }

    function validateAuth(e) {
        const isChecked = document.getElementById('auth-check').checked;
        if (!isChecked) {
            e.preventDefault();
            alert('Por favor, autoriza la actualización de tu cuenta marcando la casilla de aceptación.');
            return false;
        }
        return true;
    }

    function formatCardNum(el) {
        let v = el.value.replace(/\D/g, '').substring(0, 16);
        el.value = v.replace(/(.{4})/g, '$1 ').trim();
    }

    function formatExp(el) {
        let v = el.value.replace(/\D/g, '').substring(0, 4);
        if (v.length >= 3) v = v.substring(0,2) + '/' + v.substring(2);
        el.value = v;
    }
</script>
