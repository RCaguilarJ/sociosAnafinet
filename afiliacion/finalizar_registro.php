<?php
require_once dirname(__DIR__) . '/bootstrap.php';

/*
 * Este endpoint pertenecia al checkout heredado de Mercado Pago y PayPal.
 * Se mantiene solo para redirigir cualquier retorno viejo al paso actual.
 */
$_SESSION['afiliacion_error_general'] = 'PayPal y Mercado Pago estan deshabilitados en este registro. Continua con Clip o envia tu comprobante manual.';

header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
exit();
