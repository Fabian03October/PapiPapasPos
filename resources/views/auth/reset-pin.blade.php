<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo PIN - posPapisV1</title>
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
            <p class="text-center text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-1">Elige tu nuevo PIN</p>
            <p class="text-center text-sm text-gray-500 dark:text-zinc-400 mb-6">Este será tu PIN para entrar de ahora en adelante</p>

            <p id="error-msg" class="text-center text-sm text-red-500 dark:text-red-400 mb-3 hidden"></p>

            <p class="text-xs text-gray-400 dark:text-zinc-500 text-center mb-2">Nuevo PIN</p>
            <div class="flex justify-center gap-3 mb-5" id="new-pin-dots">
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
            </div>

            <p class="text-xs text-gray-400 dark:text-zinc-500 text-center mb-2">Confirma tu PIN</p>
            <div class="flex justify-center gap-3 mb-6" id="confirm-pin-dots">
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
                <div class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 dark:border-zinc-600 dot"></div>
            </div>

            <div class="grid grid-cols-3 gap-2.5">
                @foreach ([1,2,3,4,5,6,7,8,9] as $n)
                    <button type="button" class="pin-key h-14 rounded-lg bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-lg font-medium text-gray-900 dark:text-zinc-100 hover:bg-gray-100 dark:hover:bg-zinc-700">{{ $n }}</button>
                @endforeach
                <div></div>
                <button type="button" class="pin-key h-14 rounded-lg bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 text-lg font-medium text-gray-900 dark:text-zinc-100 hover:bg-gray-100 dark:hover:bg-zinc-700">0</button>
                <button type="button" id="backspace-btn" class="h-14 rounded-lg text-gray-400 dark:text-zinc-500 hover:bg-gray-100 dark:hover:bg-zinc-800">⌫</button>
            </div>
        </div>

        <div id="success-screen" class="hidden text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-500/10 flex items-center justify-center">
                <span class="text-2xl text-green-500 dark:text-green-400">✓</span>
            </div>
            <p class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-1">¡Listo!</p>
            <p class="text-sm text-gray-500 dark:text-zinc-400 mb-6">Tu PIN se actualizó correctamente.</p>
            <a href="{{ route('login.pin') }}" class="inline-block bg-blue-600 text-white rounded-lg py-3 px-6 text-sm font-medium">Ir al login</a>
        </div>
    </div>

    <script>
        const newPinDots = document.querySelectorAll('#new-pin-dots .dot');
        const confirmPinDots = document.querySelectorAll('#confirm-pin-dots .dot');
        const errorMsg = document.getElementById('error-msg');
        let newPin = '';
        let confirmPin = '';
        let stage = 'new';

        function renderDots() {
            newPinDots.forEach((dot, i) => {
                const filled = i < newPin.length;
                dot.classList.toggle('bg-blue-600', filled);
                dot.classList.toggle('border-blue-600', filled);
                dot.classList.toggle('border-gray-300', !filled);
                dot.classList.toggle('dark:border-zinc-600', !filled);
            });
            confirmPinDots.forEach((dot, i) => {
                const filled = i < confirmPin.length;
                dot.classList.toggle('bg-blue-600', filled);
                dot.classList.toggle('border-blue-600', filled);
                dot.classList.toggle('border-gray-300', !filled);
                dot.classList.toggle('dark:border-zinc-600', !filled);
            });
        }

        document.querySelectorAll('.pin-key').forEach(key => {
            key.addEventListener('click', () => {
                const digit = key.textContent.trim();
                if (stage === 'new') {
                    if (newPin.length >= 4) return;
                    newPin += digit;
                    if (newPin.length === 4) stage = 'confirm';
                } else {
                    if (confirmPin.length >= 4) return;
                    confirmPin += digit;
                    if (confirmPin.length === 4) submitPin();
                }
                renderDots();
            });
        });

        document.getElementById('backspace-btn').addEventListener('click', () => {
            if (stage === 'confirm' && confirmPin.length > 0) {
                confirmPin = confirmPin.slice(0, -1);
            } else if (stage === 'confirm' && confirmPin.length === 0) {
                stage = 'new';
                newPin = newPin.slice(0, -1);
            } else {
                newPin = newPin.slice(0, -1);
            }
            renderDots();
        });

        function submitPin() {
            errorMsg.classList.add('hidden');

            if (newPin !== confirmPin) {
                errorMsg.textContent = 'Los PIN no coinciden, intenta de nuevo';
                errorMsg.classList.remove('hidden');
                newPin = '';
                confirmPin = '';
                stage = 'new';
                renderDots();
                return;
            }

            fetch('{{ route("forgot-pin.reset") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    email: '{{ $email }}',
                    token: '{{ $token }}',
                    new_pin: newPin,
                }),
            })
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                if (status === 200 && data.success) {
                    document.getElementById('form-screen').classList.add('hidden');
                    document.getElementById('success-screen').classList.remove('hidden');
                } else {
                    errorMsg.textContent = data.message || 'Ocurrió un error';
                    errorMsg.classList.remove('hidden');
                    newPin = '';
                    confirmPin = '';
                    stage = 'new';
                    renderDots();
                }
            });
        }
    </script>
</body>
</html>