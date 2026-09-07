<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión - posPapisV1</title>
    @vite('resources/css/app.css')
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-zinc-950 min-h-screen flex items-center justify-center">

    <div class="w-full max-w-sm bg-white dark:bg-zinc-900 rounded-2xl p-7 shadow-lg relative overflow-hidden">

        <!-- Estado 1: escribiendo PIN -->
        <div id="pin-screen">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                <span class="text-2xl">☀️</span>
            </div>

            <p class="text-center text-lg font-semibold text-gray-900 dark:text-zinc-100">Bienvenido de vuelta</p>
            <p class="text-center text-sm text-gray-500 dark:text-zinc-400 mb-6">Que tengas un gran turno hoy</p>

            <p id="pin-error" class="text-center text-sm text-red-500 dark:text-red-400 mb-3 hidden">PIN incorrecto</p>

            <div class="flex justify-center gap-3 mb-6" id="pin-dots">
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
            </div>

            <div class="grid grid-cols-3 gap-2.5">
                @foreach ([1,2,3,4,5,6,7,8,9] as $n)
                    <button type="button" class="pin-key h-14 rounded-lg bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-lg font-medium text-gray-900 dark:text-zinc-100 hover:bg-gray-100 dark:hover:bg-zinc-700 active:bg-gray-200 dark:active:bg-zinc-600">{{ $n }}</button>
                @endforeach
                <div></div>
                <button type="button" class="pin-key h-14 rounded-lg bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-lg font-medium text-gray-900 dark:text-zinc-100 hover:bg-gray-100 dark:hover:bg-zinc-700 active:bg-gray-200 dark:active:bg-zinc-600">0</button>
                <button type="button" id="pin-backspace" class="h-14 rounded-lg text-gray-400 dark:text-zinc-500 hover:bg-gray-100 dark:hover:bg-zinc-800">⌫</button>
            </div>

            <p class="text-center text-xs text-gray-400 dark:text-zinc-500 mt-5">{{ now()->translatedFormat('l, j \d\e F') }}</p>
        </div>

        <!-- Estado 2: verificando -->
        <div id="checking-screen" class="hidden text-center py-8">
            <p class="text-xs text-gray-400 dark:text-zinc-500 uppercase tracking-wide mb-4">Verificando</p>
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                <div class="w-6 h-6 border-2 border-blue-500 dark:border-blue-400 border-t-transparent rounded-full animate-spin"></div>
            </div>
        </div>

        <!-- Estado 3: éxito -->
        <div id="success-screen" class="hidden text-center py-8">
            <p class="text-xs text-gray-400 dark:text-zinc-500 uppercase tracking-wide mb-4">PIN correcto</p>
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-500/10 flex items-center justify-center">
                <span class="text-2xl text-green-500 dark:text-green-400">✓</span>
            </div>
            <p id="success-name" class="text-lg font-semibold text-gray-900 dark:text-zinc-100">Hola,</p>
            <p id="success-role" class="text-sm text-gray-500 dark:text-zinc-400 mb-5"></p>
            <div class="h-1.5 bg-gray-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                <div id="success-bar" class="h-full bg-green-500 rounded-full" style="width: 0%"></div>
            </div>
            <p class="text-xs text-gray-400 dark:text-zinc-500 mt-3">Entrando a la pantalla de venta…</p>
        </div>

    </div>

    <script>
        const dots = document.querySelectorAll('#pin-dots .dot');
        const pinScreen = document.getElementById('pin-screen');
        const checkingScreen = document.getElementById('checking-screen');
        const successScreen = document.getElementById('success-screen');
        const pinError = document.getElementById('pin-error');
        let pin = '';

        function renderDots() {
            dots.forEach((dot, i) => {
                if (i < pin.length) {
                    dot.classList.add('bg-blue-600', 'border-blue-600');
                    dot.classList.remove('border-gray-300', 'dark:border-zinc-600');
                } else {
                    dot.classList.remove('bg-blue-600', 'border-blue-600');
                    dot.classList.add('border-gray-300', 'dark:border-zinc-600');
                }
            });
        }

        function resetPin() {
            pin = '';
            renderDots();
        }

        document.querySelectorAll('.pin-key').forEach(key => {
            key.addEventListener('click', () => {
                if (pin.length >= 4) return;
                pin += key.textContent.trim();
                renderDots();
                if (pin.length === 4) {
                    submitPin();
                }
            });
        });

        document.getElementById('pin-backspace').addEventListener('click', () => {
            pin = pin.slice(0, -1);
            renderDots();
        });

        function submitPin() {
            pinError.classList.add('hidden');
            pinScreen.classList.add('hidden');
            checkingScreen.classList.remove('hidden');

            fetch('{{ route('login.pin.submit') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ pin }),
            })
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                if (status === 200 && data.success) {
                    checkingScreen.classList.add('hidden');
                    successScreen.classList.remove('hidden');
                    document.getElementById('success-name').textContent = 'Hola, ' + data.name;
                    document.getElementById('success-role').textContent = data.role;
                    setTimeout(() => {
                        document.getElementById('success-bar').style.transition = 'width 0.6s ease';
                        document.getElementById('success-bar').style.width = '100%';
                    }, 50);
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 900);
                } else {
                    checkingScreen.classList.add('hidden');
                    pinScreen.classList.remove('hidden');
                    pinError.classList.remove('hidden');
                    resetPin();
                }
            })
            .catch(() => {
                checkingScreen.classList.add('hidden');
                pinScreen.classList.remove('hidden');
                pinError.classList.remove('hidden');
                resetPin();
            });
        }
    </script>
</body>
</html>