<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
    exit();
}

$paso = isset($_GET['paso']) ? (int) $_GET['paso'] : 1;
if (!in_array($paso, [1, 2, 4], true)) {
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
    exit();
}

$emailComValido = static function (string $email): bool {
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $emailLower = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
    return substr($emailLower, -4) === '.com';
};

if (!isset($_SESSION['afiliacion'])) {
    $_SESSION['afiliacion'] = [];
}

$_SESSION['afiliacion']["paso{$paso}"] = $_POST;

if ($paso === 1) {
    $email = (string)($_POST['email'] ?? '');
    if (!$emailComValido($email)) {
        $_SESSION['afiliacion_error'] = 'El correo debe ser valido y terminar en .com.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }

    unset($_SESSION['afiliacion_error'], $_SESSION['afiliacion_error_general']);
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=2');
    exit();
}

if ($paso === 2) {
    unset($_SESSION['afiliacion_error'], $_SESSION['afiliacion_error_general']);
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
}

if (!isset($_SESSION['afiliacion']['paso1'], $_SESSION['afiliacion']['paso2'])) {
    $_SESSION['afiliacion_error_general'] = 'Completa primero tus datos personales y de contacto.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
    exit();
}

if (!($pdo instanceof PDO)) {
    $_SESSION['afiliacion_error_general'] = 'El registro no esta disponible temporalmente porque no hay conexion con la base de datos.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
}

$paso1 = $_SESSION['afiliacion']['paso1'];
$paso2 = $_SESSION['afiliacion']['paso2'];
$referenciaPago = trim((string)($_POST['referencia_pago'] ?? ''));
$comprobante = $_FILES['comprobante'] ?? [];

$crearUsuarioAfiliacion = static function (PDO $pdo, array $paso1, array $paso2): array {
    $email = trim((string)($paso1['email'] ?? ''));
    $stmtExisting = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmtExisting->execute([$email]);
    if ($stmtExisting->fetch()) {
        throw new RuntimeException('duplicate_email');
    }

    $rolSolicitado = trim((string)($paso1['rol_solicitado'] ?? 'Asociado'));
    $passwordTemp = password_hash((string)($paso1['rfc'] ?? ''), PASSWORD_BCRYPT);
    $estatusInicial = 'Pendiente de pago';

    $sql = "INSERT INTO usuarios (
        nombre, email, password, rfc, curp, telefono,
        calle, numero, colonia, cp, ciudad, estado,
        empresa, puesto, especialidad, cedula_profesional,
        rol, rol_solicitado, estatus
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Asociado', ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        (string)($paso1['nombre'] ?? ''),
        $email,
        $passwordTemp,
        (string)($paso1['rfc'] ?? ''),
        (string)($paso1['curp'] ?? ''),
        (string)($paso1['telefono'] ?? ''),
        (string)($paso2['calle'] ?? ''),
        (string)($paso2['numero'] ?? ''),
        (string)($paso2['colonia'] ?? ''),
        (string)($paso2['cp'] ?? ''),
        (string)($paso2['ciudad'] ?? ''),
        (string)($paso2['estado'] ?? ''),
        '',
        '',
        '',
        '',
        $rolSolicitado,
        $estatusInicial,
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'nombre' => (string)($paso1['nombre'] ?? 'Asociado'),
        'email' => $email,
    ];
};

try {
    ensure_user_payment_columns($pdo);
    app_ensure_membership_payment_schema($pdo);

    $createdUserId = 0;
    $user = $crearUsuarioAfiliacion($pdo, $paso1, $paso2);
    $userId = (int)$user['id'];
    $createdUserId = $userId;

    $storeResult = app_store_manual_payment_report($pdo, $userId, $referenciaPago, is_array($comprobante) ? $comprobante : []);
    if (!($storeResult['ok'] ?? false)) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$userId]);
        $_SESSION['afiliacion_error'] = (string)($storeResult['message'] ?? 'No fue posible guardar el comprobante.');
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
        exit();
    }

    app_send_manual_payment_received_notifications(
        $pdo,
        $userId,
        $referenciaPago,
        (string)($storeResult['proof_url'] ?? '')
    );

    unset($_SESSION['afiliacion'], $_SESSION['afiliacion_error'], $_SESSION['afiliacion_error_general']);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = (string)$user['nombre'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['user_rol'] = 'Asociado';
    $_SESSION['user_estatus'] = (string)($storeResult['status'] ?? 'Pago reportado');
    $_SESSION['payment_flash_message'] = (string)($storeResult['message'] ?? 'Tu confirmacion manual fue guardada correctamente.');
    $_SESSION['payment_flash_type'] = 'success';
    $_SESSION['payment_flash_source'] = 'manual_confirmation';

    header('Location: ' . BASE_URL . '/confirmar_pago.php?registro=1');
    exit();
} catch (PDOException $e) {
    if (($createdUserId ?? 0) > 0) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([(int)$createdUserId]);
    }

    if ((string)$e->getCode() === '23000') {
        $_SESSION['afiliacion_error'] = 'El correo capturado ya esta registrado. Inicia sesion o utiliza otro correo para continuar.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }

    $_SESSION['afiliacion_error_general'] = 'No fue posible completar tu registro en este momento.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
} catch (Throwable $e) {
    if (($createdUserId ?? 0) > 0) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([(int)$createdUserId]);
    }

    if ($e->getMessage() === 'duplicate_email') {
        $_SESSION['afiliacion_error'] = 'El correo capturado ya esta registrado. Inicia sesion o utiliza otro correo para continuar.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }

    $_SESSION['afiliacion_error_general'] = 'No fue posible guardar la confirmacion manual en este momento.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
}
