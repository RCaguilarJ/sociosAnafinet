<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/mail_helpers.php'; // Cargamos las funciones de correo[cite: 3]

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
    exit();
}

$paso = isset($_GET['paso']) ? (int) $_GET['paso'] : 1;

// Agregamos el paso 4 a la lista de pasos permitidos
if (!in_array($paso, [1, 2, 4], true)) {
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
    exit();
}

$email_com_valido = function (string $email): bool {
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

$_SESSION['afiliacion']["paso$paso"] = $_POST;

// --- PROCESAR PASO 1 ---[cite: 2]
if ($paso === 1) {
    $email = $_POST['email'] ?? '';
    if (!$email_com_valido($email)) {
        $_SESSION['afiliacion_error'] = 'El correo debe ser válido y terminar en .com.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=1');
        exit();
    }
    unset($_SESSION['afiliacion_error']);
    
    $siguiente = $paso + 1;
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=' . $siguiente);
    exit();
}

// --- PROCESAR PASO 2 ---
if ($paso === 2) {
    header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
    exit();
}

// --- PROCESAR PASO 4 (Subida de Pago Manual y Envío de Correos) ---
if ($paso === 4) {
    // 1. Validar que se haya adjuntado un archivo sin errores
    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['afiliacion_error'] = 'Es obligatorio subir un archivo válido de comprobante.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
        exit();
    }

    $fileTmpPath = $_FILES['comprobante']['tmp_name'];
    $fileName = $_FILES['comprobante']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Extensiones aceptadas
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($fileExtension, $allowedExtensions, true)) {
        $_SESSION['afiliacion_error'] = 'Formato no permitido. Solo se aceptan archivos PDF, JPG o PNG.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
        exit();
    }

    // Definir ruta física del directorio de cargas
    $uploadDir = dirname(__DIR__) . '/uploads/comprobantes/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Renombrar el archivo de forma única por seguridad
    $newFileName = 'comprobante_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
    $destPath = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmpPath, $destPath)) {
        // Recuperar la información guardada en la sesión[cite: 2]
        $nombreUsuario = $_SESSION['afiliacion']['paso1']['nombre'] ?? 'Usuario Registrado';
        $emailUsuario  = $_SESSION['afiliacion']['paso1']['email'] ?? '';

        // Actualizar el estatus en la base de datos si el ID de usuario ya existe en sesión[cite: 5]
        if (isset($pdo) && !empty($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("UPDATE usuarios SET estatus = 'Pago reportado' WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }

        // --- FLUJO DE CORREOS AUTOMÁTICOS ---[cite: 3]

        // Correo A: Notificación interna a tesoreria@anafinet.mx[cite: 3]
        $introTesoreria = "<p>Se ha recibido un nuevo comprobante de pago manual en el sistema de afiliaciones.</p>";
        $summaryTesoreria = app_mail_payment_summary_rows([
            'Socio/Usuario' => $nombreUsuario,
            'Email de Contacto' => $emailUsuario,
            'Nombre del Archivo' => $newFileName,
            'Estatus Inicial' => 'Pago reportado (Validación Pendiente)'
        ]);
        
        $htmlTesoreria = app_mail_wrap_layout(
            'Aviso de Administración',
            'Nuevo Comprobante Recibido',
            $introTesoreria,
            $summaryTesoreria,
            '', // Sin botón de acción
            'Por favor, ingresa al panel maestro para auditar el documento.'
        );
        
        app_send_html_email(
            'tesoreria@anafinet.mx',
            'NOTIFICACIÓN: Pago manual registrado por ' . $nombreUsuario,
            $htmlTesoreria,
            null,
            ['from_email' => 'noreply@anafinet.mx', 'from_name' => 'Notificaciones Anafinet']
        );

        // Correo B: Notificación de confirmación al Socio desde noreply@anafinet.mx[cite: 3]
        if ($emailUsuario !== '') {
            $introSocio = "<p>Estimado/a <strong>" . app_mail_html_escape($nombreUsuario) . "</strong>,</p>"
                        . "<p>Tu comprobante de pago ha sido cargado con éxito en nuestros servidores. En este momento se encuentra en fila de revisión.</p>";
            
            $htmlSocio = app_mail_wrap_layout(
                'Procesando Solicitud',
                'Hemos recibido tu comprobante',
                $introSocio,
                '', // Sin resumen de pago
                '', // Sin botón
                'Nuestro equipo de validación (Master) verificará el documento. Te notificaremos vía correo en cuanto tu cuenta sea activada.'
            );

            app_send_html_email(
                $emailUsuario,
                'Tu pago está siendo procesado - Anafinet',
                $htmlSocio,
                null,
                ['from_email' => 'noreply@anafinet.mx', 'from_name' => 'Anafinet']
            );
        }

        unset($_SESSION['afiliacion_error']);
        header('Location: ' . BASE_URL . '/afiliacion/solicitud_en_proceso.php');
        exit();
    } else {
        $_SESSION['afiliacion_error'] = 'Error crítico al intentar guardar el archivo en el servidor.';
        header('Location: ' . BASE_URL . '/afiliacion/index.php?paso=4');
        exit();
    }
}