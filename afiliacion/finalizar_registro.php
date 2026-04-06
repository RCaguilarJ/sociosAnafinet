<?php
require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['afiliacion'])) {
    $p0 = $_SESSION['afiliacion']['paso1'];
    $p1 = $_SESSION['afiliacion']['paso2'];
    $p2 = $_SESSION['afiliacion']['paso3'];
    $p3 = $_SESSION['afiliacion']['paso4'];
    $rolSolicitado = trim((string)($p0['rol_solicitado'] ?? ''));

    try {
        $sql = "INSERT INTO usuarios (
            nombre, email, password, rfc, curp, telefono, 
            calle, numero, colonia, cp, ciudad, estado, 
            empresa, puesto, especialidad, cedula_profesional,
            rol, rol_solicitado, estatus
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Asociado', ?, 'Pendiente')";

        $stmt = $pdo->prepare($sql);
        
        // Usamos el RFC como contraseña temporal (puedes cambiar esta lógica)
        $password_temp = password_hash($p1['rfc'], PASSWORD_BCRYPT);

        $stmt->execute([
            $p1['nombre'], $p1['email'], $password_temp, $p1['rfc'], $p1['curp'], $p1['telefono'],
            $p2['calle'], $p2['numero'], $p2['colonia'], $p2['cp'], $p2['ciudad'], $p2['estado'],
            $p3['empresa'], $p3['puesto'], $p3['especialidad'], $p3['cedula'],
            $rolSolicitado
        ]);

        // Limpiar sesión de registro
        unset($_SESSION['afiliacion']);

        // Redirigir a una página de éxito
        header("Location: ../index.php?registro=exito");

    } catch (PDOException $e) {
        die("Error al registrar: " . $e->getMessage());
    }
}
