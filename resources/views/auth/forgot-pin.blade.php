<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Olvidé mi PIN - posPapisV1</title>
    @vite('resources/css/app.css')
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-zinc-950 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-white dark:bg-zinc-900 rounded-2xl p-7 shadow-lg">

        <div id="form-screen">
            <p class="text-center text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-1">¿Olvidaste tu PIN?</p>
            <p class="text-center text-sm text-gray-500 dark:text-zinc-400 mb-6">Escribe tu correo y te mandamos un enlace para elegir uno nuevo.</p>

            <label class="text-sm text-gray-500 dark:text-zinc-400 block mb-1.5">Correo</label>
            <input type="email" id="email-input" placeholder="tucorreo@ejemplo.com"
                class="w-full h-12 px-3 rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 text-sm mb-4">

            <button id="submit-btn" class="w-full bg-blue-600 text-white rounded-lg py-3 text-sm font-medium">
                Enviar enlace
            </button>

            <a href="{{ route('login.pin') }}" class="block text-center text-sm text-gray-500 dark:text-zinc-400 mt-4">
                ← Regresar al login
            </a>
        </div>

        <div id="success-screen" class="hidden text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-500/10 flex items-center justify-center">
                <span class="text-2xl text-green-500 dark:text-green-400">✓</span>
            </div>
            <p class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-1">Revisa tu correo</p>
            <p class="text-sm text-gray-500 dark:text-zinc-400 mb-6">Si ese correo existe en el sistema, te mandamos un enlace para restablecer tu PIN.</p>
            <a href="{{ route('login.pin') }}" class="text-sm text-blue-600 dark:text-blue-400">← Regresar al login</a>
        </div>
    </div>

    <script>
        document.getElementById('submit-btn').addEventListener('click', () => {
            const email = document.getElementById('email-input').value.trim();
            if (!email) return;

            fetch('{{ route("forgot-pin.send") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ email }),
            })
            .then(res => res.json())
            .then(() => {
                document.getElementById('form-screen').classList.add('hidden');
                document.getElementById('success-screen').classList.remove('hidden');
            });
        });
    </script>
</body>
</html>