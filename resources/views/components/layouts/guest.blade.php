@props(['title' => 'posPapisV1'])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <script>
        // Si la sesión se venció mientras estaban en una pantalla del POS,
        // el login carga dentro del marco: se abre en la ventana completa.
        if (window.top !== window.self) window.top.location.href = window.location.href;
    </script>
    @vite('resources/css/app.css')
    <script>
        const t = localStorage.theme;
        if (t === 'dark' || ((t === 'system' || !t) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-neutral-100 dark:bg-neutral-950 min-h-dvh flex items-center justify-center p-4">
    {{ $slot }}
</body>
</html>
