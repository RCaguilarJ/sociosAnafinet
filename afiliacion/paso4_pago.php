<?php
$datos = $_SESSION['afiliacion']['paso4'] ?? [];
$mensajeError = $_SESSION['afiliacion_error'] ?? ($_SESSION['afiliacion_error_general'] ?? '');
if ($mensajeError !== '') {
    unset($_SESSION['afiliacion_error'], $_SESSION['afiliacion_error_general']);
}

$panelInicial = ($mensajeError !== '' || !empty($datos['referencia_pago'])) ? 'manual' : 'mercadopago';
?>

<div class="animate-fadeIn">
    <h2 class="text-2xl font-bold text-slate-800">Metodo de Pago</h2>
    <p class="mt-1 mb-6 text-sm text-slate-500">Paso 3 de 3: conserva la maqueta visual de Mercado Pago y PayPal, y ademas permite cargar el comprobante manual para revision interna.</p>

    <?php if ($mensajeError !== ''): ?>
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <?php echo htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Cuota de afiliacion</p>
                    <h3 class="mt-2 text-3xl font-bold text-slate-900">$1,500.00 MXN</h3>
                    <p class="mt-2 text-sm text-slate-500">Afiliacion Anafinet · Cuota de ingreso</p>
                </div>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">Pago seguro</span>
            </div>
        </div>

        <div class="grid grid-cols-1 border-b border-slate-200 bg-slate-50 sm:grid-cols-3">
            <button type="button" data-payment-tab="mercadopago" class="payment-tab border-b border-slate-200 px-4 py-4 text-sm font-bold text-slate-500 transition sm:border-b-0 sm:border-r">
                Mercado Pago
            </button>
            <button type="button" data-payment-tab="paypal" class="payment-tab border-b border-slate-200 px-4 py-4 text-sm font-bold text-slate-500 transition sm:border-b-0 sm:border-r">
                PayPal
            </button>
            <button type="button" data-payment-tab="manual" class="payment-tab px-4 py-4 text-sm font-bold text-slate-500 transition">
                Comprobante Manual
            </button>
        </div>

        <div class="p-6">
            <section data-payment-panel="mercadopago" class="payment-panel hidden">
                <div class="mb-5 flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <span class="font-semibold">Maqueta visual</span>
                    <span class="rounded-full bg-amber-200 px-2.5 py-1 text-[11px] font-bold uppercase text-amber-900">No procesa pago</span>
                </div>

                <div class="mb-5 flex gap-2 opacity-80">
                    <span class="rounded border border-slate-200 bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">VISA</span>
                    <span class="rounded border border-slate-200 bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">MC</span>
                    <span class="rounded border border-slate-200 bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">AMEX</span>
                    <span class="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700">SPEI</span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Numero de tarjeta</label>
                        <input type="text" value="4556 7812 9011 4452" disabled class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Nombre del titular</label>
                        <input type="text" value="JUAN PEREZ LOZANO" disabled class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Expiracion</label>
                            <input type="text" value="12/29" disabled class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">CVV</label>
                            <input type="text" value="123" disabled class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Plazos</label>
                        <select disabled class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                            <option>1 solo pago de $1,500.00</option>
                            <option>3 mensualidades de $500.00 sin intereses</option>
                            <option>6 mensualidades de $250.00 sin intereses</option>
                        </select>
                    </div>
                    <button type="button" disabled class="w-full cursor-not-allowed rounded-2xl bg-[#009EE3] px-4 py-4 text-sm font-bold text-white opacity-60">
                        Pagar con Mercado Pago
                    </button>
                </div>
            </section>

            <section data-payment-panel="paypal" class="payment-panel hidden">
                <div class="mb-5 flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <span class="font-semibold">Maqueta visual</span>
                    <span class="rounded-full bg-amber-200 px-2.5 py-1 text-[11px] font-bold uppercase text-amber-900">No procesa pago</span>
                </div>

                <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-5 text-center">
                    <div class="text-2xl font-black italic text-[#003087]">Pay<span class="text-[#009CDE]">Pal</span></div>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Esta es la maqueta de la experiencia de pago con PayPal. El flujo visual se conserva, pero la validacion efectiva en este paso sigue siendo manual.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Correo de la cuenta PayPal</label>
                        <input type="email" value="correo@ejemplo.com" disabled class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                    </div>
                    <button type="button" disabled class="w-full cursor-not-allowed rounded-2xl bg-[#003087] px-4 py-4 text-sm font-bold text-white opacity-60">
                        Continuar con PayPal
                    </button>
                </div>
            </section>

            <section data-payment-panel="manual" class="payment-panel hidden">
                <div class="mb-5 rounded-2xl border border-blue-100 bg-blue-50/70 p-5 text-sm leading-6 text-blue-900">
                    <p class="font-semibold text-blue-950">Comprobacion manual con revision interna</p>
                    <p class="mt-2">Cuando envies tu referencia y el archivo del comprobante, el sistema dejara tu solicitud en estado <strong>Pago reportado</strong> para revision. El usuario master configurado y/o tesoreria podran corroborarlo desde el panel interno.</p>
                </div>

                <form action="<?php echo BASE_URL; ?>/afiliacion/procesar_paso.php?paso=4" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <label for="referencia_pago" class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Referencia o folio</label>
                        <input
                            id="referencia_pago"
                            type="text"
                            name="referencia_pago"
                            required
                            value="<?php echo htmlspecialchars((string)($datos['referencia_pago'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Ej. SPEI 548219 / Deposito membresia junio"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-4 text-base outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                        >
                        <p class="mt-3 text-xs text-slate-500">Captura el folio bancario, la referencia SPEI o una descripcion clara del pago.</p>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <label for="comprobante" class="mb-2 block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Comprobante de pago</label>
                        <label for="comprobante" class="flex cursor-pointer flex-col items-center justify-center rounded-[1.75rem] border border-dashed border-slate-300 bg-white px-6 py-10 text-center transition hover:border-blue-300 hover:bg-blue-50/40">
                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                                <i class="fa-solid fa-cloud-arrow-up text-xl"></i>
                            </span>
                            <span class="mt-4 text-base font-semibold text-slate-800">Haz clic para seleccionar el archivo</span>
                            <span class="mt-2 text-sm text-slate-500">Acepta PDF, JPG, JPEG, PNG o WebP de hasta 5 MB.</span>
                            <span id="comprobanteNombre" class="mt-4 rounded-full bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-600">Ningun archivo seleccionado</span>
                        </label>
                        <input id="comprobante" type="file" name="comprobante" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="sr-only">
                    </div>

                    <div class="rounded-3xl border border-amber-100 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-900">
                        <p class="font-semibold text-amber-950">Antes de enviar</p>
                        <ul class="mt-2 space-y-1">
                            <li>Verifica que el archivo sea legible.</li>
                            <li>El correo de confirmacion llegara al email capturado en tu registro.</li>
                            <li>Una vez validado el pago, tu acceso completo quedara habilitado.</li>
                        </ul>
                    </div>

                    <div class="flex flex-col gap-4 pt-2 md:flex-row">
                        <a href="<?php echo BASE_URL; ?>/afiliacion/index.php?paso=2" class="flex-1 py-4 text-center font-bold text-gray-500 transition-all hover:text-gray-700">
                            Anterior
                        </a>
                        <button type="submit" class="flex-[2] rounded-2xl bg-[#5282B2] py-4 font-bold text-white shadow-lg shadow-blue-200 transition-all hover:-translate-y-0.5 hover:bg-blue-700">
                            Enviar comprobante
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('[data-payment-tab]');
        const panels = document.querySelectorAll('[data-payment-panel]');
        const input = document.getElementById('comprobante');
        const label = document.getElementById('comprobanteNombre');
        const initialTab = <?php echo json_encode($panelInicial, JSON_UNESCAPED_SLASHES); ?>;

        const activateTab = function (target) {
            tabs.forEach(function (tab) {
                const isActive = tab.getAttribute('data-payment-tab') === target;
                tab.classList.toggle('bg-white', isActive);
                tab.classList.toggle('text-slate-900', isActive);
                tab.classList.toggle('text-slate-500', !isActive);
                tab.classList.toggle('shadow-sm', isActive);
            });

            panels.forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-payment-panel') !== target);
            });
        };

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activateTab(tab.getAttribute('data-payment-tab'));
            });
        });

        activateTab(initialTab);

        if (input && label) {
            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                label.textContent = file ? file.name : 'Ningun archivo seleccionado';
            });
        }
    });
</script>
