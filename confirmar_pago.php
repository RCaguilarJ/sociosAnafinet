<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/role_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];
$userStatus = (string) ($_SESSION['user_estatus'] ?? '');
$mensaje = '';

// Variables simuladas o tomadas de la sesión para el costo (puedes adaptarlas según tu BD o bootstrap)
$montoTotal = "1,299.00";
$conceptoPago = "Membresía Anual · Socio Anafinet";

// Verificamos si están configuradas las pasarelas reales en el .env
$mpHabilitado = function_exists('app_mercadopago_enabled') ? app_mercadopago_enabled() : false;
$ppHabilitado = function_exists('app_paypal_enabled') ? app_paypal_enabled() : false;

// Procesamiento del reporte manual (Transferencia Bancaria tradicional que ya tenías)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reportar_transferencia'])) {
    // Tu lógica existente para procesar archivos y guardar en la base de datos va aquí
    $mensaje = "Tu comprobante ha sido enviado con éxito. Un administrador revisará tu pago.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Pago - Anafinet</title>
    <link rel="stylesheet" href="assets/tailwind.build.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-active {
            background-color: #ffffff;
            color: #009EE3;
            border-bottom: 3px solid #009EE3;
        }
        .tab-active.paypal-theme {
            color: #003087;
            border-bottom: 3px solid #003087;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen font-sans antialiased">

    <div class="max-w-md mx-auto px-4 py-12">
        
        <div class="flex justify-center mb-8">
            <img src="logo_anafinet.png" alt="Anafinet Logo" class="h-16 object-contain fallback-image" onerror="this.src='logo.avif'">
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-medium shadow-sm">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl overflow-hidden">
            
            <div class="bg-slate-50 border-b border-slate-200 p-6">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total a pagar</span>
                <div class="text-3xl font-black text-slate-900 mt-1">
                    $<?= $montoTotal ?> <span class="text-lg font-semibold text-slate-500">MXN</span>
                </div>
                <p class="text-sm text-slate-500 mt-0.5"><?= $conceptoPago ?></p>
            </div>

            <div class="flex border-b border-slate-200 bg-slate-50">
                <button type="button" id="tab-mp" onclick="switchPaymentMethod('mp')" class="flex-1 py-3 text-sm font-bold flex items-center justify-center gap-2 border-r border-slate-200 text-slate-400 transition-all tab-active">
                    <svg class="w-6 h-5" viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="50" height="32" rx="5" fill="#009EE3"/>
                        <path d="M8 22l4-10 3 5.5 2.5-3.5 4 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <circle cx="34" cy="16" r="5" fill="white"/>
                    </svg>
                    Mercado Pago
                </button>
                <button type="button" id="tab-paypal" onclick="switchPaymentMethod('paypal')" class="flex-1 py-3 text-sm font-bold flex items-center justify-center gap-2 text-slate-400 transition-all">
                    <svg class="w-6 h-5" viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="50" height="32" rx="5" fill="#003087"/>
                        <path d="M14 7h8c4 0 6.5 2 6 5.5C27.5 17 25 19 21.5 19h-3.5l-1.5 6H12L14 7z" fill="#009CDE"/>
                    </svg>
                    PayPal
                </button>
                <button type="button" id="tab-bank" onclick="switchPaymentMethod('bank')" class="flex-1 py-3 text-sm font-bold flex items-center justify-center gap-2 text-slate-400 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Banco
                </button>
            </div>

            <div id="panel-mp" class="payment-panel p-6 block">
                <?php if (!$mpHabilitado): ?>
                    <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between">
                        <span class="text-xs font-semibold text-amber-800">Modo Demostración Ilustrativa</span>
                        <span class="px-2 py-0.5 text-[10px] uppercase font-bold bg-amber-200 text-amber-900 rounded-full">Próximamente</span>
                    </div>
                <?php endif; ?>

                <div class="flex gap-2 mb-6 opacity-80">
                    <span class="px-2 py-0.5 text-[10px] font-bold border border-slate-200 rounded bg-slate-100 text-slate-600">VISA</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold border border-slate-200 rounded bg-slate-100 text-slate-600">MASTERCARD</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold border border-slate-200 rounded bg-slate-100 text-slate-600">AMEX</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold border border-slate-200 rounded bg-emerald-100 text-emerald-700">OXXO</span>
                </div>

                <form action="mercadopago_create_payment.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Número de Tarjeta</label>
                        <input type="text" placeholder="0000 0000 0000 0000" oninput="formatCardNumber(this)" maxlength="19" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none" required <?= !$mpHabilitado ? 'disabled value="4556 7812 9011 4452"' : '' ?>>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre del Titular</label>
                        <input type="text" placeholder="Como aparece en el plástico" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none" required <?= !$mpHabilitado ? 'disabled value="JUAN PÉREZ LOZANO"' : '' ?>>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Expiración</label>
                            <input type="text" placeholder="MM/AA" oninput="formatExpiryDate(this)" maxlength="5" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none" required <?= !$mpHabilitado ? 'disabled value="12/29"' : '' ?>>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">CVV</label>
                            <input type="password" placeholder="•••" maxlength="4" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none" required <?= !$mpHabilitado ? 'disabled value="123"' : '' ?>>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Plazos de Pago <span class="ml-1 text-[10px] bg-emerald-100 text-emerald-700 font-bold px-1.5 py-0.5 rounded-full">MSI</span></label>
                        <select class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none" <?= !$mpHabilitado ? 'disabled' : '' ?>>
                            <option>1 Pago líquido de $1,299.00</option>
                            <option>3 Mensualidades de $433.00 sin intereses</option>
                            <option>6 Mensualidades de $216.50 sin intereses</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full py-3 mt-4 bg-[#009EE3] hover:bg-[#0087c2] text-white font-bold rounded-xl transition-all shadow-md shadow-blue-200 flex items-center justify-center gap-2" <?= !$mpHabilitado ? 'disabled opacity-60 cursor-not-allowed' : '' ?>>
                        Pagar con Mercado Pago
                    </button>
                </form>
            </div>

            <div id="panel-paypal" class="payment-panel p-6 hidden">
                <?php if (!$ppHabilitado): ?>
                    <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-center justify-between">
                        <span class="text-xs font-semibold text-amber-800">Modo Demostración Ilustrativa</span>
                        <span class="px-2 py-0.5 text-[10px] uppercase font-bold bg-amber-200 text-amber-900 rounded-full">Próximamente</span>
                    </div>
                <?php endif; ?>

                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center mb-6">
                    <div class="flex justify-center mb-2">
                        <span class="text-xl font-black italic text-[#003087]">Pay<span class="text-[#009CDE]">Pal</span></span>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">Al procesar, se desplegará la ventana nativa de PayPal para que ingreses con tus credenciales institucionales sin comprometer tu tarjeta.</p>
                </div>

                <?php if ($ppHabilitado): ?>
                    <div id="paypal-button-container" class="w-full min-h-[150px]"></div>
                <?php else: ?>
                    <button type="button" class="w-full py-3 bg-[#003087] text-white font-bold rounded-xl opacity-60 cursor-not-allowed flex items-center justify-center gap-2">
                        Pagar Seguro con PayPal
                    </button>
                <?php endif; ?>
            </div>

            <div id="panel-bank" class="payment-panel p-6 hidden">
                <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-xl text-xs space-y-2 mb-6">
                    <p class="font-bold text-sm">Datos Oficiales para Depósito/Transferencia:</p>
                    <p><strong>Banco:</strong> Banamex</p>
                    <p><strong>Cuenta Corporativa:</strong> 1234567890</p>
                    <p><strong>Clabe Interbancaria:</strong> 002180012345678901</p>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="reportar_transferencia" value="1">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Subir Comprobante (PDF o Imagen)</label>
                        <input type="file" name="comprobante" accept="image/*,application/pdf" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" required>
                    </div>
                    <button type="submit" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition-all shadow-md">
                        Enviar Reporte Manual
                    </button>
                </form>
            </div>

            <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 text-center">
                <div class="flex items-center justify-center gap-1.5 text-xs text-slate-400 font-medium">
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Conexión Cifrada SSL de Alta Seguridad 256-bits
                </div>
            </div>
        </div>

        <p class="text-center text-xs text-slate-400 font-medium mt-6">
            © 2026 Anafinet A.C. · Todos los derechos reservados.
        </p>
    </div>

    <script>
        function switchPaymentMethod(method) {
            // Ocultamos todos los paneles
            document.querySelectorAll('.payment-panel').forEach(panel => panel.classList.replace('block', 'hidden'));
            // Quitamos clases activas de los botones de pestañas
            document.querySelectorAll('.flex border-b button').forEach(button => {
                button.classList.remove('tab-active', 'paypal-theme');
            });
            document.getElementById('tab-mp').classList.remove('tab-active');
            document.getElementById('tab-paypal').classList.remove('tab-active', 'paypal-theme');
            document.getElementById('tab-bank').classList.remove('tab-active');

            // Activamos el panel y pestaña correspondiente
            document.getElementById('panel-' + method).classList.replace('hidden', 'block');
            const tabActive = document.getElementById('tab-' + method);
            
            if (method === 'paypal') {
                tabActive.classList.add('tab-active', 'paypal-theme');
            } else {
                tabActive.classList.add('tab-active');
            }
        }

        function formatCardNumber(input) {
            let value = input.value.replace(/\D/g, '').substring(0, 16);
            input.value = value.replace(/(.{4})/g, '$1 ').trim();
        }

        function formatExpiryDate(input) {
            let value = input.value.replace(/\D/g, '').substring(0, 4);
            if (value.length >= 3) {
                input.value = value.substring(0, 2) + '/' + value.substring(2);
            } else {
                input.value = value;
            }
        }
    </script>

    <?php if ($ppHabilitado): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode(getenv('PAYPAL_CLIENT_ID')) ?>&currency=MXN"></script>
    <script>
        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{ amount: { value: '1299.00' } }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    window.location.href = "confirmar_pago.php?paypal_success=1&order_id=" + data.orderID;
                });
            }
        }).render('#paypal-button-container');
    </script>
    <?php endif; ?>

</body>
</html>