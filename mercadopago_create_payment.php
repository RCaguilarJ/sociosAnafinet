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

if (!app_mercadopago_enabled()) {
    $_SESSION['payment_flash_message'] = 'Mercado Pago aun no esta configurado en este ambiente.';
    $_SESSION['payment_flash_type'] = 'error';
    header('Location: ' . BASE_URL . '/confirmar_pago.php');
    exit();
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

try {
    ensure_user_payment_columns($pdo);
    app_ensure_membership_payment_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, nombre, email, estatus FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!is_array($user)) {
        throw new RuntimeException('No fue posible encontrar al usuario autenticado.');
    }

    if (app_is_membership_active_status((string) ($user['estatus'] ?? ''))) {
        $_SESSION['payment_flash_message'] = 'Tu membresia ya esta activa. No es necesario generar un nuevo pago.';
        $_SESSION['payment_flash_type'] = 'info';
        header('Location: ' . BASE_URL . '/confirmar_pago.php');
        exit();
    }

    $externalReference = app_membership_payment_reference($userId);
    app_insert_membership_payment_attempt($pdo, $userId, 'mercadopago', $externalReference);

    $preference = app_create_mercadopago_preference($pdo, $user, $externalReference);
    $checkoutUrl = (string) (
        app_mercadopago_use_sandbox()
            ? ($preference['sandbox_init_point'] ?? $preference['init_point'] ?? '')
            : ($preference['init_point'] ?? $preference['sandbox_init_point'] ?? '')
    );

    if ($checkoutUrl === '') {
        throw new RuntimeException('Mercado Pago no devolvio una URL de checkout.');
    }

    app_update_membership_payment_attempt($pdo, $externalReference, [
        'provider_order_id' => isset($preference['id']) ? (string) $preference['id'] : null,
        'checkout_url' => $checkoutUrl,
        'status' => 'checkout_created',
        'raw_payload' => $preference,
    ]);

    header('Location: ' . $checkoutUrl);
    exit();
} catch (Throwable $e) {
    $_SESSION['payment_flash_message'] = 'No fue posible iniciar el pago en Mercado Pago. Revisa la configuracion e intenta de nuevo.';
    $_SESSION['payment_flash_type'] = 'error';
    header('Location: ' . BASE_URL . '/confirmar_pago.php');
    exit();
}
