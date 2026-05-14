<?php
require_once 'config.php';

$demoLoginAvailable = app_demo_login_available();
$demoCredentials = app_demo_credentials();
$loginError = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(base_url('assets/tailwind.build.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Anafinet - Login</title>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-gray-100">
        <div class="text-center mb-8">
            <img src="<?php echo htmlspecialchars(base_url('logo.avif'), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Anafinet" class="mx-auto w-40 mb-4">
            <h2 class="text-xl font-bold text-gray-800">Area de Asociados</h2>
            <p class="text-sm text-gray-500">Ingresa tus credenciales para acceder</p>
        </div>

        <?php if ($loginError === '1'): ?>
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                Credenciales incorrectas. Verifica tu email y contrasena.
            </div>
        <?php elseif ($loginError === 'db'): ?>
            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                No fue posible iniciar sesion por un problema de conexion o configuracion del servidor.
            </div>
        <?php endif; ?>

        <?php if ($demoLoginAvailable): ?>
            <div class="bg-blue-50 border border-blue-200 p-3 rounded-lg mb-6 text-xs text-blue-700">
                <strong>Credenciales de prueba:</strong><br>
                Email: <?php echo htmlspecialchars((string)$demoCredentials['email'], ENT_QUOTES, 'UTF-8'); ?>
                | Contraseña: <?php echo htmlspecialchars((string)$demoCredentials['password'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars(base_url('auth.php'), ENT_QUOTES, 'UTF-8'); ?>" method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required class="w-full mt-1 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Contraseña</label>
                <input type="password" name="password" required class="w-full mt-1 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <button type="submit" class="w-full bg-[#5282B2] text-white py-2 rounded-lg font-semibold hover:bg-blue-700 transition shadow-md">
                Iniciar Sesion
            </button>
        </form>

        <div class="mt-6 text-center text-sm">
            <p class="text-gray-400">¿No eres asociado aun?</p>
            <a href="<?php echo htmlspecialchars(base_url('afiliacion/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="text-orange-500 font-bold hover:underline">Solicita tu Afiliacion</a>
        </div>
    </div>

</body>
</html>
