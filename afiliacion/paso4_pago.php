<?php
$datos = $_SESSION['afiliacion']['paso4'] ?? [];
$mensajeError = $_SESSION['afiliacion_error'] ?? ($_SESSION['afiliacion_error_general'] ?? '');
if ($mensajeError !== '') {
    unset($_SESSION['afiliacion_error'], $_SESSION['afiliacion_error_general']);
}

$clipHabilitado = function_exists('app_clip_enabled') ? app_clip_enabled() : false;
$montoPagoFloat = function_exists('app_membership_fee_amount') ? app_membership_fee_amount() : 1000.00;
$montoTotal = number_format($montoPagoFloat, 2, '.', ',');
$conceptoPago = function_exists('app_membership_fee_label')
    ? app_membership_fee_label()
    : 'Membresia Anafinet';
$panelInicial = ($mensajeError !== '' || !empty($datos['referencia_pago'])) ? 'manual' : 'clip';
?>

<div class="animate-fadeIn">
    <h2 class="text-2xl font-bold text-slate-800">Metodo de Pago</h2>
    <p class="mt-1 mb-6 text-sm text-slate-500">Paso 3 de 3: el pago en linea ahora se genera con Clip y la comprobacion manual permanece disponible para revision interna.</p>

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
                    <h3 class="mt-2 text-3xl font-bold text-slate-900">$<?php echo htmlspecialchars($montoTotal, ENT_QUOTES, 'UTF-8'); ?> MXN</h3>
                    <p class="mt-2 text-sm text-slate-500"><?php echo htmlspecialchars($conceptoPago, ENT_QUOTES, 'UTF-8'); ?> · Cuota anual</p>
                </div>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">Pago seguro</span>
            </div>
        </div>

        <div class="grid grid-cols-1 border-b border-slate-200 bg-slate-50 sm:grid-cols-2">
            <button type="button" data-payment-tab="clip" class="payment-tab border-b border-slate-200 px-4 py-4 text-sm font-bold text-slate-500 transition sm:border-b-0 sm:border-r">
                Clip
            </button>
            <button type="button" data-payment-tab="manual" class="payment-tab px-4 py-4 text-sm font-bold text-slate-500 transition">
                Comprobante Manual
            </button>
        </div>

        <div class="p-6">
            <section data-payment-panel="clip" class="payment-panel hidden">
                <?php /* Mercado Pago y PayPal quedaron intencionalmente fuera del frontend de afiliacion. */ ?>
                <div class="mb-5 rounded-2xl border border-violet-100 bg-violet-50/80 p-5 text-sm leading-6 text-violet-900">
                    <p class="font-semibold text-violet-950">Checkout en linea con Clip</p>
                    <p class="mt-2">Genera un link de pago seguro para completar tu afiliacion. Cuando Clip confirme la operacion, el sistema actualizara tu estatus y te llevara a tu portal.</p>
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-slate-50 p-4 sm:p-5 lg:p-6">
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.12fr)_minmax(19rem,22rem)] xl:items-start">
                        <div class="space-y-5">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-violet-100 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-violet-800">Clip</span>
                                <span class="rounded-full bg-white px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500 ring-1 ring-slate-200">Tarjeta</span>
                                <span class="rounded-full bg-white px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500 ring-1 ring-slate-200">Debito</span>
                                <span class="rounded-full bg-white px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500 ring-1 ring-slate-200">Credito</span>
                            </div>

                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Flujo activo</p>
                                <h3 class="mt-2 text-[clamp(2rem,5vw,3rem)] font-bold leading-tight text-slate-900">Pagar con Clip</h3>
                                <p class="mt-3 max-w-2xl text-sm leading-8 text-slate-600 sm:text-[15px]">
                                    El sistema creara tu usuario en estado pendiente y abrira el checkout de Clip para que completes el pago.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:min-h-[168px]">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Importe</p>
                                    <div class="mt-4 space-y-2">
                                        <span class="block text-[clamp(2rem,6vw,2.8rem)] font-black leading-[0.95] tracking-[-0.05em] text-slate-900 tabular-nums">$<?php echo htmlspecialchars($montoTotal, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="block text-sm font-bold uppercase tracking-[0.18em] text-slate-500">MXN</span>
                                    </div>
                                </div>
                                <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:min-h-[168px]">
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Vigencia</p>
                                    <p class="mt-4 text-lg font-semibold leading-7 text-slate-900">Membresia anual</p>
                                </div>
                            </div>
                        </div>

                        <div class="mx-auto w-full max-w-md rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:mx-0 xl:max-w-none">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Resumen</p>
                            <div class="mt-3 text-[clamp(2.5rem,7vw,3.6rem)] font-black leading-none tracking-[-0.05em] text-slate-900 tabular-nums">$<?php echo htmlspecialchars($montoTotal, ENT_QUOTES, 'UTF-8'); ?></div>
                            <p class="mt-4 text-sm leading-8 text-slate-600 sm:text-[15px]">Cuota anual de la membresia. El link de pago se genera al continuar.</p>

                            <?php if (!$clipHabilitado): ?>
                                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                                    Clip aun no esta configurado en este ambiente.
                                </div>
                            <?php endif; ?>

                            <form action="<?php echo BASE_URL; ?>/afiliacion/clip_create_payment.php" method="POST" class="mt-5">
                                <button
                                    type="submit"
                                    class="w-full rounded-2xl bg-[#5b3df5] px-4 py-4 text-sm font-bold text-white shadow-lg shadow-indigo-100 transition hover:bg-indigo-700 <?php echo !$clipHabilitado ? 'cursor-not-allowed opacity-60' : ''; ?>"
                                    <?php echo !$clipHabilitado ? 'disabled' : ''; ?>
                                >
                                    Pagar con Clip
                                </button>
                            </form>
                        </div>
                    </div>
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
