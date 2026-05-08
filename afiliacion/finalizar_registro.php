<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['afiliacion']['paso1'], $_SESSION['afiliacion']['paso2'])) {
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
    exit();
}

if (!($pdo instanceof PDO)) {
    $_SESSION['afiliacion_error_general'] = 'El registro no está disponible temporalmente porque no hay conexión con la base de datos. Intenta nuevamente más tarde.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=3');
    exit();
}

$p1 = $_SESSION['afiliacion']['paso1'];
$p2 = $_SESSION['afiliacion']['paso2'];
$rolSolicitado = trim((string) ($p1['rol_solicitado'] ?? 'Asociado'));
$empresa = '';
$puesto = '';
$especialidad = '';
$cedula = '';
$estatus = 'Pendiente de pago';
$email = trim((string) ($p1['email'] ?? ''));

try {
    $stmtExisting = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $stmtExisting->execute([$email]);
    if ($stmtExisting->fetch()) {
        $_SESSION['afiliacion_error'] = 'El correo capturado ya está registrado. Inicia sesión o utiliza otro correo para continuar.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }

    $sql = "INSERT INTO usuarios (
        nombre, email, password, rfc, curp, telefono,
        calle, numero, colonia, cp, ciudad, estado,
        empresa, puesto, especialidad, cedula_profesional,
        rol, rol_solicitado, estatus
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Asociado', ?, ?)";

    $stmt = $pdo->prepare($sql);
    $passwordTemp = password_hash($p1['rfc'], PASSWORD_BCRYPT);

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
        $empresa,
        $puesto,
        $especialidad,
        $cedula,
        $rolSolicitado,
        $estatus,
    ]);

    $newUserId = (int) $pdo->lastInsertId();

    unset($_SESSION['afiliacion'], $_SESSION['afiliacion_error'], $_SESSION['afiliacion_error_general']);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['user_name'] = $p1['nombre'];
    $_SESSION['user_rol'] = 'Asociado';
    $_SESSION['user_estatus'] = $estatus;

    header('Location: ' . BASE_URL . '/confirmar_pago.php?registro=1');
    exit();
} catch (PDOException $e) {
    if ((string) $e->getCode() === '23000') {
        $_SESSION['afiliacion_error'] = 'El correo capturado ya está registrado. Inicia sesión o utiliza otro correo para continuar.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }

    $_SESSION['afiliacion_error_general'] = 'No fue posible completar el registro en este momento. Intenta nuevamente más tarde.';
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=3');
    exit();
}
