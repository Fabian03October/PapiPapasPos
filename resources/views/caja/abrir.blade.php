<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abrir caja - posPapisV1</title>
    @vite('resources/css/app.css')
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-zinc-950 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-white dark:bg-zinc-900 rounded-2xl p-7 shadow-lg">

        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-lg font-semibold text-gray-900 dark:text-zinc-100">Abrir caja</p>
            <p class="text-sm text-gray-500 dark:text-zinc-400">{{ auth()->user()->name }} · {{ now()->translatedFormat('l, j \d\e F') }}</p>
        </div>

        <p id="form-error" class="text-center text-sm text-red-500 dark:text-red-400 mb-3 hidden"></p>

        <label class="text-sm text-gray-500 dark:text-zinc-400 block mb-1.5">Fondo inicial en efectivo</label>
        <div class="relative mb-2">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-zinc-500">$</span>
            <input type="number" id="opening-amount" step="0.01" min="0" value="0"
                class="w-full h-13 pl-7 pr-3 py-3 rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 text-lg font-semibold">
        </div>
        <p class="text-xs text-gray-400 dark:text-zinc-500 mb-5">Cuenta el efectivo físico antes de continuar</p>

        @if ($ultimaCerrada)
            <div class="bg-gray-50 dark:bg-zinc-800 rounded-lg p-3 mb-5 text-sm">
                <div class="flex justify-between mb-1">
                    <span class="text-gray-500 dark:text-zinc-400">Última apertura</span>
                    <span class="text-gray-900 dark:text-zinc-100">{{ $ultimaCerrada->opened_at->diffForHumans() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500 dark:text-zinc-400">Cierre anterior</span>
                    <span class="text-gray-900 dark:text-zinc-100">${{ number_format($ultimaCerrada->counted_amount ?? 0, 2) }}</span>
                </div>
            </div>
        @endif

        <button id="submit-btn" class="w-full bg-blue-600 text-white rounded-lg py-3 text-sm font-medium mb-3">
            Iniciar turno
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="block w-full text-center text-xs text-gray-400 dark:text-zinc-500 hover:text-gray-600 dark:hover:text-zinc-300 py-1">
                Cerrar sesión
            </button>
        </form>
    </div>

    <script>
        document.getElementById('submit-btn').addEventListener('click', () => {
            const amount = document.getElementById('opening-amount').value;
            const errorEl = document.getElementById('form-error');
            errorEl.classList.add('hidden');

            fetch('{{ route('caja.abrir.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ opening_amount: amount }),
            })
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                if (status === 200 && data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorEl.textContent = data.message || 'Ocurrió un error';
                    errorEl.classList.remove('hidden');
                }
            });
        });
    </script>
</body>
</html>