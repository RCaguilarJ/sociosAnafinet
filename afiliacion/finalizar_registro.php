<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$hasSignupSession = isset($_SESSION['afiliacion']['paso1'], $_SESSION['afiliacion']['paso2']);
$gateway = strtolower(trim((string) ($_GET['gateway'] ?? $_POST['gateway'] ?? '')));
$isGatewayCallback = $_SERVER['REQUEST_METHOD'] === 'GET' && in_array($gateway, ['mercadopago', 'paypal'], true);

if ((!$isGatewayCallback && $_SERVER['REQUEST_METHOD'] !== 'POST') || !$hasSignupSession) {
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
    exit();
}

if (!($pdo instanceof PDO)) {
    $_SESSION['afiliacion_error_general'] = 'El registro no esta disponible temporalmente porque no hay conexion con la base de datos. Intenta nuevamente mas tarde.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=3');
    exit();
}

$p1 = $_SESSION['afiliacion']['paso1'];
$p2 = $_SESSION['afiliacion']['paso2'];
$rolSolicitado = trim((string) ($p1['rol_solicitado'] ?? 'Asociado'));
$estatusInicial = 'Pendiente de pago';
$email = trim((string) ($p1['email'] ?? ''));

$resolveUser = static function (PDO $pdo, array $p1, array $p2, string $rolSolicitado, string $estatusInicial, string $email): array {
    $stmtExisting = $pdo->prepare("SELECT id, nombre, estatus FROM usuarios WHERE email = ? LIMIT 1");
    $stmtExisting->execute([$email]);
    $existingUser = $stmtExisting->fetch();

    if (is_array($existingUser)) {
        $retryUserId = (int) ($_SESSION['afiliacion_created_user_id'] ?? 0);
        if ($retryUserId !== (int) $existingUser['id']) {
            throw new RuntimeException('duplicate_email');
        }

        return [
            'id' => (int) $existingUser['id'],
            'nombre' => (string) ($existingUser['nombre'] ?? $p1['nombre']),
            'estatus' => (string) ($existingUser['estatus'] ?? $estatusInicial),
            'created' => false,
        ];
    }

    $sql = "INSERT INTO usuarios (
        nombre, email, password, rfc, curp, telefono,
        calle, numero, colonia, cp, ciudad, estado,
        empresa, puesto, especialidad, cedula_profesional,
        rol, rol_solicitado, estatus
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Asociado', ?, ?)";

    $stmt = $pdo->prepare($sql);
    $passwordTemp = password_hash((string) ($p1['rfc'] ?? ''), PASSWORD_BCRYPT);

    $stmt->execute([
        $p1['nombre'],
        $email,
        $passwordTemp,
        $p1['rfc'],
        $p1['curp'],
        $p1['telefono'],
        $p2['calle'],
        $p2['numero'],
        $p2['colonia'],
        $p2['cp'],
        $p2['ciudad'],
        $p2['estado'],
        '',
        '',
        '',
        '',
        $rolSolicitado,
        $estatusInicial,
    ]);

    $_SESSION['afiliacion_created_user_id'] = (int) $pdo->lastInsertId();

    return [
        'id' => (int) $_SESSION['afiliacion_created_user_id'],
        'nombre' => (string) $p1['nombre'],
        'estatus' => $estatusInicial,
        'created' => true,
    ];
};

try {
    ensure_user_payment_columns($pdo);
    app_ensure_membership_payment_schema($pdo);

    if ($isGatewayCallback) {
        $user = $resolveUser($pdo, $p1, $p2, $rolSolicitado, $estatusInicial, $email);
        $userId = (int) $user['id'];
        $externalReference = trim((string) ($_GET['external_reference'] ?? ($_SESSION['afiliacion_payment_external_reference'] ?? '')));
        if ($externalReference === '') {
            $externalReference = app_membership_signup_reference();
        }

        $existingPayment = app_get_membership_payment_by_external_reference($pdo, $externalReference);
        if (!is_array($existingPayment)) {
            app_insert_membership_payment_attempt($pdo, $userId, $gateway, $externalReference);
        }

        if ($gateway === 'paypal') {
            $orderId = trim((string) ($_GET['order_id'] ?? ''));
            if ($orderId === '') {
                throw new RuntimeException('No se recibio la orden de PayPal para completar el registro.');
            }

            $syncedPayment = app_sync_paypal_order($pdo, $userId, $externalReference, $orderId);
            $_SESSION['payment_flash_message'] = $syncedPayment['payment_status'] === 'approved'
                ? 'Tu pago en PayPal fue aprobado y tu membresia ya quedo activa. Tambien recibiras un correo con la confirmacion de tu pago.'
                : 'Tu pago en PayPal quedo en proceso. Te enviaremos un correo con la confirmacion y podras revisar el estado desde tu portal.';
            $_SESSION['payment_flash_type'] = $syncedPayment['payment_status'] === 'approved' ? 'success' : 'info';
        } else {
            $mercadoPagoPaymentId = trim((string) ($_GET['payment_id'] ?? $_GET['collection_id'] ?? ''));
            $returnState = trim((string) ($_GET['mp_return'] ?? ''));

            if ($mercadoPagoPaymentId !== '' && app_mercadopago_enabled()) {
                $syncedPayment = app_sync_mercadopago_payment($pdo, $mercadoPagoPaymentId);
                $paymentStatus = (string) ($syncedPayment['payment_status'] ?? '');

                if ($paymentStatus === 'approved') {
                    $_SESSION['payment_flash_message'] = 'Tu pago en Mercado Pago fue aprobado y tu membresia ya quedo activa. Tambien recibiras un correo con la confirmacion de tu pago.';
                    $_SESSION['payment_flash_type'] = 'success';
                } elseif ($paymentStatus === 'processing' || $returnState === 'pending') {
                    $_SESSION['payment_flash_message'] = 'Tu pago en Mercado Pago esta en proceso. Te enviaremos un correo con la confirmacion y podras darle seguimiento desde tu portal.';
                    $_SESSION['payment_flash_type'] = 'info';
                } else {
                    $_SESSION['payment_flash_message'] = 'El pago en Mercado Pago no pudo confirmarse. Puedes intentarlo de nuevo desde tu portal.';
                    $_SESSION['payment_flash_type'] = 'error';
                }
            } else {
                $status = $returnState === 'pending' ? 'processing' : 'failed';
                $userStatus = $status === 'processing' ? 'Pago en proceso' : 'Pendiente de pago';

                app_update_membership_payment_attempt($pdo, $externalReference, [
                    'provider_order_id' => trim((string) ($_GET['preference_id'] ?? '')),
                    'status' => $status,
                    'raw_payload' => $_GET,
                ]);

                $userUpdate = $pdo->prepare('UPDATE usuarios SET estatus = ? WHERE id = ?');
                $userUpdate->execute([$userStatus, $userId]);

                $_SESSION['payment_flash_message'] = $status === 'processing'
                    ? 'Tu pago en Mercado Pago quedo en proceso. Te enviaremos un correo con la confirmacion y podras darle seguimiento desde tu portal.'
                    : 'No fue posible confirmar el pago en Mercado Pago. Puedes intentarlo de nuevo desde tu portal.';
                $_SESSION['payment_flash_type'] = $status === 'processing' ? 'info' : 'error';
            }
        }

        $stmtUser = $pdo->prepare('SELECT estatus FROM usuarios WHERE id = ? LIMIT 1');
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch();
        $userStatus = is_array($userRow) ? (string) ($userRow['estatus'] ?? $estatusInicial) : $estatusInicial;

        unset($_SESSION['afiliacion'], $_SESSION['afiliacion_error'], $_SESSION['afiliacion_error_general'], $_SESSION['afiliacion_payment_external_reference'], $_SESSION['afiliacion_created_user_id']);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_rol'] = 'Asociado';
        $_SESSION['user_estatus'] = $userStatus;

        header('Location: ' . BASE_URL . '/confirmar_pago.php?registro=1');
        exit();
    }

    $user = $resolveUser($pdo, $p1, $p2, $rolSolicitado, $estatusInicial, $email);

    unset($_SESSION['afiliacion'], $_SESSION['afiliacion_error'], $_SESSION['afiliacion_error_general'], $_SESSION['afiliacion_payment_external_reference'], $_SESSION['afiliacion_created_user_id']);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['nombre'];
    $_SESSION['user_rol'] = 'Asociado';
    $_SESSION['user_estatus'] = $user['estatus'];

    header('Location: ' . BASE_URL . '/confirmar_pago.php?registro=1');
    exit();
} catch (PDOException $e) {
    if ((string) $e->getCode() === '23000') {
        $_SESSION['afiliacion_error'] = 'El correo capturado ya esta registrado. Inicia sesion o utiliza otro correo para continuar.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }

    $_SESSION['afiliacion_error_general'] = 'No fue posible completar el registro en este momento. Intenta nuevamente mas tarde.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=3');
    exit();
} catch (Throwable $e) {
    if ($e->getMessage() === 'duplicate_email') {
        $_SESSION['afiliacion_error'] = 'El correo capturado ya esta registrado. Inicia sesion o utiliza otro correo para continuar.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }

    $_SESSION['afiliacion_error_general'] = 'No fue posible validar el pago en linea en este momento. Intenta nuevamente mas tarde.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=3');
    exit();
}
