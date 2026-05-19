<?php
if (!isset($_SESSION['afiliacion']['paso1'])) {
    header('Location: index.php?paso=1');
    exit();
}

if (!isset($_SESSION['afiliacion']['paso2'])) {
    header('Location: index.php?paso=2');
    exit();
}

$mensajeGeneral = $_SESSION['afiliacion_error_general'] ?? '';
if ($mensajeGeneral !== '') {
    unset($_SESSION['afiliacion_error_general']);
}

$monto_afiliacion = number_format(app_membership_fee_amount(), 2, '.', '');
$moneda_afiliacion = app_membership_fee_currency();
$concepto_afiliacion = app_membership_fee_label();
$paypal_client_id = app_paypal_client_id();
$mercadopago_public_key = app_mercadopago_public_key();
$mercadopago_preference_id = '';
$erroresPasarela = [];

if (!isset($_SESSION['afiliacion_payment_external_reference']) || trim((string) $_SESSION['afiliacion_payment_external_reference']) === '') {
    $_SESSION['afiliacion_payment_external_reference'] = app_membership_signup_reference();
}

$afiliacionExternalReference = (string) $_SESSION['afiliacion_payment_external_reference'];
$paypalHabilitado = app_paypal_enabled();
$mercadoPagoHabilitado = app_mercadopago_enabled() && $mercadopago_public_key !== '';

if ($mercadoPagoHabilitado && $pdo instanceof PDO) {
    try {
        $baseUrl = app_public_base_url();
        $backUrls = [
            'success' => $baseUrl . '/afiliacion/finalizar_registro.php?gateway=mercadopago&mp_return=success&external_reference=' . rawurlencode($afiliacionExternalReference),
            'failure' => $baseUrl . '/afiliacion/finalizar_registro.php?gateway=mercadopago&mp_return=failure&external_reference=' . rawurlencode($afiliacionExternalReference),
            'pending' => $baseUrl . '/afiliacion/finalizar_registro.php?gateway=mercadopago&mp_return=pending&external_reference=' . rawurlencode($afiliacionExternalReference),
        ];

        $preference = app_create_mercadopago_preference(
            $pdo,
            [
                'nombre' => (string) ($_SESSION['afiliacion']['paso1']['nombre'] ?? ''),
                'email' => (string) ($_SESSION['afiliacion']['paso1']['email'] ?? ''),
            ],
            $afiliacionExternalReference,
            ['back_urls' => $backUrls]
        );

        $mercadopago_preference_id = (string) ($preference['id'] ?? '');
        if ($mercadopago_preference_id === '') {
            $mercadoPagoHabilitado = false;
            $erroresPasarela[] = 'Mercado Pago no devolvio una preferencia valida para este registro.';
        }
    } catch (Throwable $e) {
        $mercadoPagoHabilitado = false;
        $erroresPasarela[] = 'No fue posible preparar Mercado Pago en este momento.';
    }
}

if (!$paypalHabilitado) {
    $erroresPasarela[] = 'PayPal aun no esta configurado en este ambiente.';
}

$pasarelaInicial = $mercadoPagoHabilitado ? 'mercadopago' : ($paypalHabilitado ? 'paypal' : '');
?>

<div class="animate-fadeIn">
    <h2 class="text-2xl font-bold text-gray-800 mb-2">Metodo de Pago</h2>
    <p class="text-gray-500 text-sm mb-8">Paso 3 de 3: completa el pago de tu afiliacion. Al aprobarse, tu cuenta se crea automaticamente y se enlaza con la membresia.</p>

    <?php if ($mensajeGeneral !== ''): ?>
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <?php echo htmlspecialchars($mensajeGeneral, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($erroresPasarela) && !$mercadoPagoHabilitado && !$paypalHabilitado): ?>
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <p class="font-semibold">Las pasarelas en linea no estan disponibles por configuracion.</p>
            <p class="mt-1">Puedes continuar con el registro y reportar el pago manualmente desde tu portal.</p>
        </div>
    <?php endif; ?>

    <div class="w-full max-w-md mx-auto bg-white rounded-lg border border-gray-200 p-6">
        <div class="mb-5 flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-400">Cuota de afiliacion</p>
                <h3 class="mt-1 text-2xl font-bold text-gray-900">$<?php echo htmlspecialchars(number_format((float) $monto_afiliacion, 2), ENT_QUOTES, 'UTF-8'); ?> <span class="text-sm font-medium text-gray-500"><?php echo htmlspecialchars($moneda_afiliacion, ENT_QUOTES, 'UTF-8'); ?></span></h3>
                <p class="mt-1 text-sm text-gray-500"><?php echo htmlspecialchars($concepto_afiliacion, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                Pago seguro
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-5">
            <button
                type="button"
                id="selectorMercadoPago"
                data-metodo="mercadopago"
                class="rounded-lg border px-4 py-3 text-sm font-semibold transition-all"
                <?php echo !$mercadoPagoHabilitado ? 'disabled' : ''; ?>
            >
                Mercado Pago
            </button>
            <button
                type="button"
                id="selectorPayPal"
                data-metodo="paypal"
                class="rounded-lg border px-4 py-3 text-sm font-semibold transition-all"
                <?php echo !$paypalHabilitado ? 'disabled' : ''; ?>
            >
                PayPal
            </button>
        </div>

        <div class="mb-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <label class="flex items-start gap-3">
                <input id="aceptaPagoInline" type="checkbox" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-xs leading-relaxed text-gray-600">
                    Acepto completar mi registro y autorizo que el estado de mi cuenta se actualice con base en la respuesta de la pasarela seleccionada.
                </span>
            </label>
            <p id="paymentGatewayFeedback" class="mt-3 hidden rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"></p>
        </div>

        <div id="paymentGatewayPanels" class="space-y-4">
            <div id="mercadopagoPanel" class="<?php echo $pasarelaInicial === 'mercadopago' ? 'block' : 'hidden'; ?>">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <p class="mb-3 text-xs font-medium uppercase tracking-[0.18em] text-gray-400">Mercado Pago</p>
                    <div id="walletPaymentBrick_container" class="min-h-[56px]"></div>
                </div>
            </div>

            <div id="paypalPanel" class="<?php echo $pasarelaInicial === 'paypal' ? 'block' : 'hidden'; ?>">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <p class="mb-3 text-xs font-medium uppercase tracking-[0.18em] text-gray-400">PayPal</p>
                    <div id="paypal-button-container" class="min-h-[44px]"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row gap-4 pt-8">
        <a href="<?php echo BASE_URL; ?>/afiliacion/index.php?paso=2" class="flex-1 text-center py-4 text-gray-500 font-bold hover:text-gray-700 transition-all">
            Anterior
        </a>

        <?php if (!$mercadoPagoHabilitado && !$paypalHabilitado): ?>
            <form action="<?php echo BASE_URL; ?>/afiliacion/finalizar_registro.php" method="POST" class="flex-[2]">
                <button type="submit" class="w-full bg-green-600 text-white py-4 rounded-2xl font-bold shadow-lg shadow-green-100 hover:bg-green-700 hover:-translate-y-0.5 transition-all">
                    Crear cuenta y continuar
                </button>
            </form>
        <?php else: ?>
            <div class="flex-[2] rounded-2xl border border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-500">
                Selecciona una pasarela y aprueba el pago para finalizar tu registro.
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://sdk.mercadopago.com/js/v2"></script>
<?php if ($paypal_client_id !== '' && $paypalHabilitado): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode($paypal_client_id); ?>&currency=<?php echo urlencode($moneda_afiliacion); ?>"></script>
<?php endif; ?>
<script>
    (function () {
        const gatewayFeedback = document.getElementById('paymentGatewayFeedback');
        const aceptaPagoInline = document.getElementById('aceptaPagoInline');
        const gatewayPanels = document.getElementById('paymentGatewayPanels');
        const paypalPanel = document.getElementById('paypalPanel');
        const mercadoPagoPanel = document.getElementById('mercadopagoPanel');
        const selectorMercadoPago = document.getElementById('selectorMercadoPago');
        const selectorPayPal = document.getElementById('selectorPayPal');

        const estadoPasarelas = {
            mercadopago: <?php echo $mercadoPagoHabilitado ? 'true' : 'false'; ?>,
            paypal: <?php echo $paypalHabilitado ? 'true' : 'false'; ?>
        };

        let metodoActivo = <?php echo json_encode($pasarelaInicial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        let mercadoPagoInicializado = false;
        let paypalInicializado = false;

        const estilosTabs = {
            activo: ['border-blue-600', 'bg-blue-50', 'text-blue-700', 'shadow-sm'],
            inactivo: ['border-gray-200', 'bg-white', 'text-gray-500', 'hover:border-gray-300', 'hover:text-gray-700'],
            deshabilitado: ['border-gray-200', 'bg-gray-100', 'text-gray-400', 'cursor-not-allowed']
        };

        const mostrarFeedback = function (mensaje) {
            if (!gatewayFeedback) {
                return;
            }

            if (!mensaje) {
                gatewayFeedback.textContent = '';
                gatewayFeedback.classList.add('hidden');
                return;
            }

            gatewayFeedback.textContent = mensaje;
            gatewayFeedback.classList.remove('hidden');
        };

        const aplicarEstilosSelector = function (elemento, activo, disponible) {
            if (!elemento) {
                return;
            }

            elemento.classList.remove(...estilosTabs.activo, ...estilosTabs.inactivo, ...estilosTabs.deshabilitado);

            if (!disponible) {
                elemento.classList.add(...estilosTabs.deshabilitado);
                return;
            }

            elemento.classList.add(...(activo ? estilosTabs.activo : estilosTabs.inactivo));
        };

        const actualizarBloqueoPasarelas = function () {
            if (!gatewayPanels || !aceptaPagoInline) {
                return;
            }

            const bloqueado = !aceptaPagoInline.checked;
            gatewayPanels.classList.toggle('pointer-events-none', bloqueado);
            gatewayPanels.classList.toggle('opacity-60', bloqueado);

            if (bloqueado) {
                mostrarFeedback('Debes aceptar la autorizacion para habilitar la pasarela.');
            } else {
                mostrarFeedback('');
            }
        };

        window.alternarPasarela = function (metodo) {
            if (!estadoPasarelas[metodo]) {
                return;
            }

            metodoActivo = metodo;
            mercadoPagoPanel.classList.toggle('hidden', metodo !== 'mercadopago');
            mercadoPagoPanel.classList.toggle('block', metodo === 'mercadopago');
            paypalPanel.classList.toggle('hidden', metodo !== 'paypal');
            paypalPanel.classList.toggle('block', metodo === 'paypal');

            aplicarEstilosSelector(selectorMercadoPago, metodo === 'mercadopago', estadoPasarelas.mercadopago);
            aplicarEstilosSelector(selectorPayPal, metodo === 'paypal', estadoPasarelas.paypal);
            mostrarFeedback('');

            if (metodo === 'mercadopago') {
                inicializarMercadoPago();
            }

            if (metodo === 'paypal') {
                inicializarPayPal();
            }
        };

        const inicializarMercadoPago = async function () {
            if (mercadoPagoInicializado || !estadoPasarelas.mercadopago || typeof window.MercadoPago !== 'function') {
                return;
            }

            try {
                const mp = new window.MercadoPago(<?php echo json_encode($mercadopago_public_key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>, {
                    locale: 'es-MX'
                });
                const bricksBuilder = mp.bricks();

                window.walletPaymentBrickController = await bricksBuilder.create('wallet', 'walletPaymentBrick_container', {
                    initialization: {
                        preferenceId: <?php echo json_encode($mercadopago_preference_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                    }
                });

                mercadoPagoInicializado = true;
            } catch (error) {
                mostrarFeedback('No fue posible inicializar Mercado Pago en este momento.');
            }
        };

        const inicializarPayPal = function () {
            if (paypalInicializado || !estadoPasarelas.paypal || !window.paypal || typeof window.paypal.Buttons !== 'function') {
                return;
            }

            window.paypal.Buttons({
                style: {
                    layout: 'vertical',
                    shape: 'rect',
                    label: 'paypal'
                },
                onClick: function (data, actions) {
                    if (!aceptaPagoInline.checked) {
                        mostrarFeedback('Debes aceptar la autorizacion antes de continuar con PayPal.');
                        return actions.reject();
                    }

                    mostrarFeedback('');
                    return actions.resolve();
                },
                createOrder: function (data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            description: <?php echo json_encode($concepto_afiliacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                            custom_id: <?php echo json_encode($afiliacionExternalReference, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                            amount: {
                                currency_code: <?php echo json_encode($moneda_afiliacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                                value: <?php echo json_encode($monto_afiliacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                            }
                        }]
                    });
                },
                onApprove: function (data) {
                    const targetUrl = <?php echo json_encode(BASE_URL . '/afiliacion/finalizar_registro.php', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    const query = new URLSearchParams({
                        gateway: 'paypal',
                        order_id: data.orderID,
                        external_reference: <?php echo json_encode($afiliacionExternalReference, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                    });

                    window.location.href = targetUrl + '?' + query.toString();
                },
                onError: function () {
                    mostrarFeedback('No fue posible iniciar PayPal. Intenta de nuevo o usa Mercado Pago.');
                }
            }).render('#paypal-button-container');

            paypalInicializado = true;
        };

        selectorMercadoPago.addEventListener('click', function () {
            window.alternarPasarela('mercadopago');
        });

        selectorPayPal.addEventListener('click', function () {
            window.alternarPasarela('paypal');
        });

        aceptaPagoInline.addEventListener('change', actualizarBloqueoPasarelas);

        aplicarEstilosSelector(selectorMercadoPago, metodoActivo === 'mercadopago', estadoPasarelas.mercadopago);
        aplicarEstilosSelector(selectorPayPal, metodoActivo === 'paypal', estadoPasarelas.paypal);
        actualizarBloqueoPasarelas();

        if (metodoActivo) {
            window.alternarPasarela(metodoActivo);
        }
    })();
</script>
