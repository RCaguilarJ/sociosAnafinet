<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$hasSignupSession = isset($_SESSION['afiliacion']['paso1'], $_SESSION['afiliacion']['paso2']);
$isCallback = $_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['callback'] ?? '') === '1');

if (!$hasSignupSession) {
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
    exit();
}

if (!$isCallback && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
}

if (!($pdo instanceof PDO)) {
    $_SESSION['afiliacion_error_general'] = 'El registro no esta disponible temporalmente porque no hay conexion con la base de datos.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
}

if (!app_clip_enabled()) {
    $_SESSION['afiliacion_error_general'] = 'Clip aun no esta configurado en este ambiente.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
}

$p1 = $_SESSION['afiliacion']['paso1'];
$p2 = $_SESSION['afiliacion']['paso2'];
$rolSolicitado = trim((string)($p1['rol_solicitado'] ?? 'Asociado'));
$estatusInicial = 'Pendiente de pago';
$email = trim((string)($p1['email'] ?? ''));

$resolveUser = static function (PDO $pdo, array $p1, array $p2, string $rolSolicitado, string $estatusInicial, string $email): array {
    $stmtExisting = $pdo->prepare('SELECT id, nombre, estatus FROM usuarios WHERE email = ? LIMIT 1');
    $stmtExisting->execute([$email]);
    $existingUser = $stmtExisting->fetch();

    if (is_array($existingUser)) {
        $retryUserId = (int)($_SESSION['afiliacion_created_user_id'] ?? 0);
        if ($retryUserId !== (int)$existingUser['id']) {
            throw new RuntimeException('duplicate_email');
        }

        return [
            'id' => (int)$existingUser['id'],
            'nombre' => (string)($existingUser['nombre'] ?? $p1['nombre']),
            'estatus' => (string)($existingUser['estatus'] ?? $estatusInicial),
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
    $passwordTemp = password_hash((string)($p1['rfc'] ?? ''), PASSWORD_BCRYPT);

    $stmt->execute([
        (string)$p1['nombre'],
        $email,
        $passwordTemp,
        (string)($p1['rfc'] ?? ''),
        (string)($p1['curp'] ?? ''),
        (string)($p1['telefono'] ?? ''),
        (string)($p2['calle'] ?? ''),
        (string)($p2['numero'] ?? ''),
        (string)($p2['colonia'] ?? ''),
        (string)($p2['cp'] ?? ''),
        (string)($p2['ciudad'] ?? ''),
        (string)($p2['estado'] ?? ''),
        '',
        '',
        '',
        '',
        $rolSolicitado,
        $estatusInicial,
    ]);

    $_SESSION['afiliacion_created_user_id'] = (int)$pdo->lastInsertId();

    return [
        'id' => (int)$_SESSION['afiliacion_created_user_id'],
        'nombre' => (string)$p1['nombre'],
        'estatus' => $estatusInicial,
        'created' => true,
    ];
};

try {
    ensure_user_payment_columns($pdo);
    app_ensure_membership_payment_schema($pdo);

    if ($isCallback) {
        $user = $resolveUser($pdo, $p1, $p2, $rolSolicitado, $estatusInicial, $email);
        $userId = (int)$user['id'];
        $externalReference = trim((string)($_GET['external_reference'] ?? ($_SESSION['afiliacion_clip_external_reference'] ?? '')));
        if ($externalReference === '') {
            $externalReference = app_clip_membership_signup_reference();
        }

        $existingPayment = app_get_membership_payment_by_external_reference($pdo, $externalReference);
        $paymentRequestId = trim((string)($existingPayment['provider_order_id'] ?? ($_GET['payment_request_id'] ?? '')));
        if ($paymentRequestId === '') {
            throw new RuntimeException('No se recibio la referencia del checkout de Clip.');
        }

        $syncedPayment = app_sync_clip_payment_request($pdo, $userId, $externalReference, $paymentRequestId);
        $paymentStatus = (string)($syncedPayment['payment_status'] ?? '');

        if ($paymentStatus === 'approved') {
            $_SESSION['payment_flash_message'] = 'Tu pago en Clip fue aprobado y tu membresia ya quedo activa. Tambien recibiras un correo con la confirmacion de tu pago.';
            $_SESSION['payment_flash_type'] = 'success';
        } elseif ($paymentStatus === 'processing' || $paymentStatus === 'checkout_created') {
            $_SESSION['payment_flash_message'] = 'Tu pago en Clip esta en proceso. Te enviaremos un correo con la confirmacion y podras darle seguimiento desde tu portal.';
            $_SESSION['payment_flash_type'] = 'info';
        } else {
            $_SESSION['payment_flash_message'] = 'El pago en Clip no pudo confirmarse. Puedes intentarlo de nuevo desde tu portal.';
            $_SESSION['payment_flash_type'] = 'error';
        }

        $stmtUser = $pdo->prepare('SELECT estatus FROM usuarios WHERE id = ? LIMIT 1');
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch();
        $userStatus = is_array($userRow) ? (string)($userRow['estatus'] ?? $estatusInicial) : $estatusInicial;

        unset(
            $_SESSION['afiliacion'],
            $_SESSION['afiliacion_error'],
            $_SESSION['afiliacion_error_general'],
            $_SESSION['afiliacion_created_user_id'],
            $_SESSION['afiliacion_clip_external_reference']
        );

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_rol'] = 'Asociado';
        $_SESSION['user_estatus'] = $userStatus;

        header('Location: ' . BASE_URL . '/confirmar_pago.php?registro=1');
        exit();
    }

    $user = $resolveUser($pdo, $p1, $p2, $rolSolicitado, $estatusInicial, $email);
    $userId = (int)$user['id'];

    if (app_is_membership_active_status((string)($user['estatus'] ?? ''))) {
        $_SESSION['afiliacion_error_general'] = 'La membresia de este usuario ya esta activa.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
        exit();
    }

    $externalReference = (string)($_SESSION['afiliacion_clip_external_reference'] ?? '');
    if ($externalReference === '') {
        $externalReference = app_clip_membership_signup_reference();
        $_SESSION['afiliacion_clip_external_reference'] = $externalReference;
    }

    $existingPayment = app_get_membership_payment_by_external_reference($pdo, $externalReference);
    if (!is_array($existingPayment)) {
        app_insert_membership_payment_attempt($pdo, $userId, 'clip', $externalReference);
    }

    $checkout = app_create_clip_checkout_link($pdo, [
        'id' => $userId,
        'nombre' => (string)$user['nombre'],
        'email' => $email,
        'telefono' => (string)($p1['telefono'] ?? ''),
    ], $externalReference, [
        'redirection_url' => [
            'success' => app_public_base_url() . '/afiliacion/clip_create_payment.php?callback=1&clip_return=success&external_reference=' . rawurlencode($externalReference),
            'error' => app_public_base_url() . '/afiliacion/clip_create_payment.php?callback=1&clip_return=error&external_reference=' . rawurlencode($externalReference),
            'default' => app_public_base_url() . '/afiliacion/clip_create_payment.php?callback=1&clip_return=default&external_reference=' . rawurlencode($externalReference),
        ],
    ]);

    $checkoutUrl = trim((string)($checkout['payment_request_url'] ?? ''));
    $paymentRequestId = trim((string)($checkout['payment_request_id'] ?? ''));
    if ($checkoutUrl === '' || $paymentRequestId === '') {
        throw new RuntimeException('Clip no devolvio un checkout valido.');
    }

    app_update_membership_payment_attempt($pdo, $externalReference, [
        'provider_order_id' => $paymentRequestId,
        'checkout_url' => $checkoutUrl,
        'status' => strtolower((string)($checkout['status'] ?? 'checkout_created')),
        'raw_payload' => $checkout,
    ]);

    header('Location: ' . $checkoutUrl);
    exit();
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        $_SESSION['afiliacion_error'] = 'El correo capturado ya esta registrado. Inicia sesion o utiliza otro correo para continuar.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }

    $message = 'No fue posible iniciar el pago con Clip en este momento.';
    if (app_is_local_request() || app_session_debug_enabled()) {
        $message .= ' Detalle: ' . $e->getMessage();
    }
    $_SESSION['afiliacion_error_general'] = $message;
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
} catch (Throwable $e) {
    if ($e->getMessage() === 'duplicate_email') {
        $_SESSION['afiliacion_error'] = 'El correo capturado ya esta registrado. Inicia sesion o utiliza otro correo para continuar.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }

    $message = 'No fue posible iniciar o validar el pago con Clip en este momento.';
    if (app_is_local_request() || app_session_debug_enabled()) {
        $message .= ' Detalle: ' . $e->getMessage();
        if (app_is_local_request()) {
            $message .= ' Revisa tambien que PUBLIC_APP_URL apunte a una URL publica accesible por Clip.';
        }
    }
    $_SESSION['afiliacion_error_general'] = $message;
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
}
