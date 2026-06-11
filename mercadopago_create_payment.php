<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Mercado Pago quedo deshabilitado en este flujo.
 * El cobro en linea activo es Clip y la alternativa manual sigue disponible.
 */
$_SESSION['payment_flash_message'] = 'Mercado Pago esta deshabilitado temporalmente. Usa Clip o registra tu comprobante manual.';
$_SESSION['payment_flash_type'] = 'info';

header('Location: ' . BASE_URL . '/confirmar_pago.php');
exit();
