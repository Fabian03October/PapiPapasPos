<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrar caja - posPapisV1</title>
    @vite('resources/css/app.css')
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-zinc-950 min-h-screen">

    <div class="grid grid-cols-1 md:grid-cols-[190px_1fr] gap-4 p-4 md:h-screen md:overflow-hidden">

        @include('partials.sidebar', ['active' => 'caja', 'pageTitle' => 'Cerrar caja'])

        <div class="overflow-y-auto">
            <div class="max-w-2xl">

                <a href="{{ route('caja.resumen') }}" class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-zinc-400 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Regresar
                </a>

                <div class="bg-white dark:bg-zinc-900 rounded-2xl p-5 mb-4">
                    <p class="text-sm font-semibold text-gray-900 dark:text-zinc-100 mb-1">Conteo de efectivo</p>
                    <p class="text-xs text-gray-400 dark:text-zinc-500 mb-4">Cuenta cuántas piezas de cada tipo tienes</p>

                    <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-zinc-500 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                        </svg>
                        Billetes
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-4" id="bills-grid"></div>

                    <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-zinc-500 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Monedas
                    </div>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2" id="coins-grid"></div>
                </div>

                <div class="bg-white dark:bg-zinc-900 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="flex-1 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-400 dark:text-zinc-500">Esperado</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-zinc-100">${{ number_format($expected, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 dark:text-zinc-500">Contado</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-zinc-100" id="counted-display">$0.00</p>
                        </div>
                    </div>
                    <div id="difference-box" class="rounded-lg px-4 py-2 text-center bg-gray-100 dark:bg-zinc-800">
                        <p class="text-xs text-gray-500 dark:text-zinc-400" id="difference-label">Diferencia</p>
                        <p class="text-base font-semibold text-gray-700 dark:text-zinc-300" id="difference-amount">$0.00</p>
                    </div>
                    <button id="confirm-close-btn" class="bg-blue-600 text-white rounded-lg px-6 py-3 text-sm font-medium whitespace-nowrap">
                        Confirmar y cerrar caja
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Popup de éxito al cerrar -->
    <div id="success-screen" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl p-7 shadow-lg text-center max-w-sm w-full">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-500/10 flex items-center justify-center">
                <span class="text-2xl text-green-500 dark:text-green-400">✓</span>
            </div>
            <p class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-1">Caja cerrada</p>
            <p class="text-sm text-gray-500 dark:text-zinc-400 mb-6">El turno quedó registrado correctamente.</p>
            <a href="{{ route('venta.index') }}" class="inline-block bg-blue-600 text-white rounded-lg py-3 px-6 text-sm font-medium">Continuar</a>
        </div>
    </div>

    <script>
        @include('partials.sidebar-scripts')

        const expected = {{ $expected }};
        const bills = [1000, 500, 200, 100, 50, 20];
        const coins = [10, 5, 2, 1, 0.5];

        function denomCard(value) {
            const label = value >= 1 ? '$' + value : '$' + value.toFixed(2);
            return `
                <div class="bg-gray-50 dark:bg-zinc-800 rounded-lg p-2 text-center">
                    <p class="text-sm font-semibold text-gray-900 dark:text-zinc-100 mb-1">${label}</p>
                    <input type="number" min="0" value="0" data-value="${value}"
                        class="denom-input w-full h-9 text-center rounded-md border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-gray-900 dark:text-zinc-100 text-sm">
                    <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1 denom-subtotal" data-for="${value}">$0.00</p>
                </div>
            `;
        }

        document.getElementById('bills-grid').innerHTML = bills.map(denomCard).join('');
        document.getElementById('coins-grid').innerHTML = coins.map(denomCard).join('');

        const countedDisplay = document.getElementById('counted-display');
        const diffBox = document.getElementById('difference-box');
        const diffLabel = document.getElementById('difference-label');
        const diffAmount = document.getElementById('difference-amount');

        function recalculate() {
            let total = 0;

            document.querySelectorAll('.denom-input').forEach(input => {
                const value = parseFloat(input.dataset.value);
                const qty = parseInt(input.value) || 0;
                const subtotal = value * qty;
                total += subtotal;

                document.querySelector(`.denom-subtotal[data-for="${value}"]`).textContent = '$' + subtotal.toFixed(2);
            });

            countedDisplay.textContent = '$' + total.toFixed(2);

            const diff = total - expected;

            if (diff === 0) {
                diffBox.className = 'rounded-lg px-4 py-2 text-center bg-green-50 dark:bg-green-500/10';
                diffLabel.className = 'text-xs text-green-600 dark:text-green-400';
                diffAmount.className = 'text-base font-semibold text-green-600 dark:text-green-400';
                diffLabel.textContent = 'Cuadra perfecto';
            } else if (diff > 0) {
                diffBox.className = 'rounded-lg px-4 py-2 text-center bg-blue-50 dark:bg-blue-500/10';
                diffLabel.className = 'text-xs text-blue-600 dark:text-blue-400';
                diffAmount.className = 'text-base font-semibold text-blue-600 dark:text-blue-400';
                diffLabel.textContent = 'Sobra';
            } else {
                diffBox.className = 'rounded-lg px-4 py-2 text-center bg-red-50 dark:bg-red-500/10';
                diffLabel.className = 'text-xs text-red-600 dark:text-red-400';
                diffAmount.className = 'text-base font-semibold text-red-600 dark:text-red-400';
                diffLabel.textContent = 'Falta';
            }

            diffAmount.textContent = '$' + Math.abs(diff).toFixed(2);
        }

        document.querySelectorAll('.denom-input').forEach(input => {
            input.addEventListener('input', recalculate);
        });

        document.getElementById('confirm-close-btn').addEventListener('click', () => {
            const denominations = Array.from(document.querySelectorAll('.denom-input')).map(input => ({
                value: parseFloat(input.dataset.value),
                qty: parseInt(input.value) || 0,
            }));

            fetch('{{ route("caja.cerrar.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ denominations }),
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const successScreen = document.getElementById('success-screen');
                    successScreen.classList.remove('hidden');
                    successScreen.classList.add('flex');
                }
            });
        });
    </script>
</body>
</html>