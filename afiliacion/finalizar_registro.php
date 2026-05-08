<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['afiliacion']['paso1'], $_SESSION['afiliacion']['paso2'])) {
    header('Location: index.php?paso=1');
    exit();
}

$p1 = $_SESSION['afiliacion']['paso1'];
$p2 = $_SESSION['afiliacion']['paso2'];
$rolSolicitado = trim((string) ($p1['rol_solicitado'] ?? 'Asociado'));
$empresa = '';
$puesto = '';
$especialidad = '';
$cedula = '';

try {
    $sql = "INSERT INTO usuarios (
        nombre, email, password, rfc, curp, telefono,
        calle, numero, colonia, cp, ciudad, estado,
        empresa, puesto, especialidad, cedula_profesional,
        rol, rol_solicitado, estatus
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Asociado', ?, 'Pendiente')";

    $stmt = $pdo->prepare($sql);
    $password_temp = password_hash($p1['rfc'], PASSWORD_BCRYPT);

    $stmt->execute([
        $p1['nombre'],
        $p1['email'],
        $password_temp,
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
    ]);

    unset($_SESSION['afiliacion'], $_SESSION['afiliacion_error']);
    header('Location: ../index.php?registro=exito');
    exit();
} catch (PDOException $e) {
    die('Error al registrar: ' . $e->getMessage());
}
