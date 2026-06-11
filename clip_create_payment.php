<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/confirmar_pago.php');
    exit();
}

if (!$pdo instanceof PDO) {
    $_SESSION['payment_flash_message'] = 'La base de datos no esta disponible para generar el pago en linea.';
    $_SESSION['payment_flash_type'] = 'error';
    header('Location: ' . BASE_URL . '/confirmar_pago.php');
    exit();
}

if (!app_clip_enabled()) {
    $_SESSION['payment_flash_message'] = 'Clip aun no esta configurado en este ambiente.';
    $_SESSION['payment_flash_type'] = 'error';
    header('Location: ' . BASE_URL . '/confirmar_pago.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    ensure_user_payment_columns($pdo);
    app_ensure_membership_payment_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, nombre, email, telefono, estatus FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!is_array($user)) {
        throw new RuntimeException('No fue posible encontrar al usuario autenticado.');
    }

    if (app_is_membership_active_status((string)($user['estatus'] ?? ''))) {
        $_SESSION['payment_flash_message'] = 'Tu membresia ya esta activa. No es necesario generar un nuevo pago.';
        $_SESSION['payment_flash_type'] = 'info';
        header('Location: ' . BASE_URL . '/confirmar_pago.php');
        exit();
    }

    $externalReference = app_clip_membership_payment_reference($userId);
    app_insert_membership_payment_attempt($pdo, $userId, 'clip', $externalReference);

    $checkout = app_create_clip_checkout_link($pdo, $user, $externalReference, [
        'redirection_url' => [
            'success' => app_public_base_url() . '/confirmar_pago.php?provider=clip&clip_return=success&external_reference=' . rawurlencode($externalReference),
            'error' => app_public_base_url() . '/confirmar_pago.php?provider=clip&clip_return=error&external_reference=' . rawurlencode($externalReference),
            'default' => app_public_base_url() . '/confirmar_pago.php?provider=clip&clip_return=default&external_reference=' . rawurlencode($externalReference),
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
} catch (Throwable $e) {
    $message = 'No fue posible iniciar el pago en Clip. Revisa la configuracion e intenta de nuevo.';
    if (app_is_local_request() || app_session_debug_enabled()) {
        $message .= ' Detalle: ' . $e->getMessage();
        if (app_is_local_request()) {
            $message .= ' Revisa tambien que PUBLIC_APP_URL apunte a una URL publica accesible por Clip.';
        }
    }
    $_SESSION['payment_flash_message'] = $message;
    $_SESSION['payment_flash_type'] = 'error';
    header('Location: ' . BASE_URL . '/confirmar_pago.php');
    exit();
}
