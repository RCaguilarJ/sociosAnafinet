<?php
require_once dirname(__DIR__) . '/bootstrap.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/tailwind.build.css">
    <title>Afiliación en Proceso</title>
    <style>
        body {
            background:
                radial-gradient(circle at top left, rgba(82, 130, 178, 0.14), transparent 26%),
                radial-gradient(circle at bottom right, rgba(249, 115, 22, 0.10), transparent 22%),
                linear-gradient(180deg, #f6fbff 0%, #eef5fb 100%);
        }

        .status-shell {
            position: relative;
            overflow: hidden;
            background: #EFF6FB;
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 24px 64px rgba(15, 23, 42, 0.10);
        }

        .status-shell::before,
        .status-shell::after {
            content: "";
            position: absolute;
            border-radius: 9999px;
            pointer-events: none;
        }

        .status-shell::before {
            width: 220px;
            height: 220px;
            top: -110px;
            right: -80px;
            background: radial-gradient(circle, rgba(82, 130, 178, 0.18) 0%, rgba(82, 130, 178, 0) 72%);
        }

        .status-shell::after {
            width: 180px;
            height: 180px;
            bottom: -90px;
            left: -60px;
            background: radial-gradient(circle, rgba(249, 115, 22, 0.12) 0%, rgba(249, 115, 22, 0) 72%);
        }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden px-3 py-4 sm:px-5 sm:py-6 lg:px-8 lg:py-8">
    <main class="mx-auto w-full max-w-6xl">
        <section class="status-shell rounded-[2rem] p-5 sm:p-7 lg:p-10">
            <div class="relative z-10 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.9fr)] xl:items-center">
                <div class="min-w-0">
                    <img src="<?php echo BASE_URL; ?>/logo_anafinet.png" alt="Anafinet" class="h-auto w-full max-w-[180px] sm:max-w-[240px] lg:max-w-[320px]">

                    <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-amber-200 bg-white/80 px-4 py-2 text-sm font-semibold text-amber-700">
                        <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                        Solicitud recibida
                    </div>

                    <div class="mt-5 flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-md shadow-slate-200 ring-1 ring-slate-200 sm:h-18 sm:w-18">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#5282B2]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2" />
                            <circle cx="12" cy="12" r="9" />
                        </svg>
                    </div>

                    <h1 class="mt-6 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl lg:text-[2.8rem] lg:leading-[1.05]">
                        Su afiliación está en proceso
                    </h1>

                    <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-600 sm:text-lg">
                        Hemos recibido su solicitud correctamente. Le notificaremos cuando su estatus esté listo y su validación haya concluido.
                    </p>

                    <div class="mt-6 rounded-2xl border border-blue-100 bg-white/75 px-4 py-4 text-sm leading-relaxed text-slate-600 sm:px-5">
                        Mientras tanto, nuestro equipo revisará la información capturada. No necesita reenviar su solicitud.
                    </div>

                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <a href="<?php echo BASE_URL; ?>/index.php" class="inline-flex w-full items-center justify-center rounded-2xl bg-[#5282B2] px-6 py-4 text-center font-bold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700 hover:-translate-y-0.5 sm:w-auto sm:min-w-[220px]">
                            Volver al inicio
                        </a>
                        <a href="<?php echo BASE_URL; ?>/" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white/80 px-6 py-4 text-center font-bold text-slate-700 transition hover:bg-white sm:w-auto sm:min-w-[220px]">
                            Ir al portal
                        </a>
                    </div>
                </div>

                <div class="grid gap-4">
                    <div class="rounded-3xl border border-white/80 bg-white/82 p-5 shadow-sm sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Estado actual</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900">En revisión</p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">Su registro fue enviado y ya está en cola de validación.</p>
                    </div>

                    <div class="rounded-3xl border border-white/80 bg-white/82 p-5 shadow-sm sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Siguiente paso</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900">Verificación interna</p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">Nuestro equipo revisará la información capturada para activar su cuenta.</p>
                    </div>

                    <div class="rounded-3xl border border-white/80 bg-white/82 p-5 shadow-sm sm:p-6">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Notificación</p>
                        <p class="mt-3 text-xl font-semibold text-slate-900">Por correo</p>
                        <p class="mt-2 text-sm leading-7 text-slate-500">Recibirá una notificación cuando su estatus esté listo.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
