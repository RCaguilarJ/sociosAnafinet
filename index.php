<?php require_once 'config.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <title>Anafinet - Login</title>
</head>
<body class="bg-slate-100 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-gray-100">
        <div class="text-center mb-8">
            <img src="<?php echo BASE_URL; ?>/logo.avif" alt="Logo Anafinet" class="mx-auto w-40 mb-4">
            <h2 class="text-xl font-bold text-gray-800">Área de Asociados</h2>
            <p class="text-sm text-gray-500">Ingresa tus credenciales para acceder</p>
        </div>

        <div class="bg-blue-50 border border-blue-200 p-3 rounded-lg mb-6 text-xs text-blue-700">
            <strong>Credenciales de prueba:</strong><br>
            Email: asociado@anafinet.mx | Contraseña: anafinet2024
        </div>

        <form action="<?php echo BASE_URL; ?>/auth.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required class="w-full mt-1 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Contraseña</label>
                <input type="password" name="password" required class="w-full mt-1 p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            <button type="submit" class="w-full bg-[#5282B2] text-white py-2 rounded-lg font-semibold hover:bg-blue-700 transition shadow-md">
                Iniciar Sesión
            </button>
        </form>

        <div class="mt-6 text-center text-sm">
            <p class="text-gray-400">¿No eres asociado aún?</p>
            <a href="#" class="text-orange-500 font-bold hover:underline">Solicita tu Afiliación</a>
        </div>
    </div>

</body>
</html>




