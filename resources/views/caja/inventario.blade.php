<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conteo de inventario - posPapisV1</title>
    @vite('resources/css/app.css')
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-zinc-950 min-h-screen">

    <div class="grid grid-cols-1 md:grid-cols-[190px_1fr] gap-4 p-4 md:h-screen md:overflow-hidden">

        @include('partials.sidebar', ['active' => 'caja', 'pageTitle' => 'Conteo de inventario'])

        <div class="overflow-y-auto">
            <div class="max-w-5xl">

                <a href="{{ route('caja.resumen') }}" class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-zinc-400 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Regresar
                </a>

                <div class="bg-white dark:bg-zinc-900 rounded-2xl p-5 mb-4">
                    <p class="text-sm font-semibold text-gray-900 dark:text-zinc-100 mb-1">Conteo físico de inventario</p>
                    <p class="text-xs text-gray-400 dark:text-zinc-500 mb-4">
                        Obligatorio antes de cerrar caja. Cuenta cada insumo y anota lo que realmente hay — cualquiera puede hacerlo.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3" id="ingredients-list">
                        @foreach ($ingredients as $ingredient)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-800/50 px-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-zinc-100 truncate">{{ $ingredient->name }}</p>
                                    <p class="text-xs text-gray-400 dark:text-zinc-500">{{ $ingredient->unit }}</p>
                                </div>
                                <input type="number" step="0.01" min="0" placeholder="0.00"
                                    class="count-input w-24 shrink-0 h-10 px-3 rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-gray-900 dark:text-zinc-100 text-sm text-right"
                                    data-ingredient-id="{{ $ingredient->id }}">
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="sticky bottom-0 max-w-5xl bg-white dark:bg-zinc-900 rounded-2xl p-5 flex items-center justify-between gap-3 shadow-[0_-4px_12px_rgba(0,0,0,0.04)] dark:shadow-none">
                    <p class="text-xs text-gray-400 dark:text-zinc-500" id="pending-label">
                        Faltan {{ $ingredients->count() }} insumos por contar
                    </p>
                    <button id="submit-count-btn" disabled
                        class="bg-blue-600 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg px-6 py-3 text-sm font-medium whitespace-nowrap">
                        Guardar conteo y continuar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Popup de confirmación de conteo -->
    <div id="mermas-screen" class="hidden fixed inset-0 z-50 items-center justify-center bg-black/50 p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl p-7 shadow-lg max-w-sm w-full text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center">
                <span class="text-2xl text-blue-500 dark:text-blue-400">✓</span>
            </div>
            <p class="text-lg font-semibold text-gray-900 dark:text-zinc-100 mb-1">Inventario actualizado</p>
            <p class="text-sm text-gray-500 dark:text-zinc-400 mb-6">El stock se ajustó según tu conteo. Cualquier diferencia queda registrada para revisión del encargado.</p>
            <a id="mermas-continue-btn" href="#" class="block text-center bg-blue-600 text-white rounded-lg py-3 px-6 text-sm font-medium">Continuar</a>
        </div>
    </div>

    <script>
        @include('partials.sidebar-scripts')

        const inputs = document.querySelectorAll('.count-input');
        const pendingLabel = document.getElementById('pending-label');
        const submitBtn = document.getElementById('submit-count-btn');

        function updatePending() {
            const pending = Array.from(inputs).filter(i => i.value.trim() === '').length;
            pendingLabel.textContent = pending === 0 ? 'Todos los insumos contados' : `Faltan ${pending} insumos por contar`;
            submitBtn.disabled = pending > 0;
        }

        inputs.forEach(input => input.addEventListener('input', updatePending));
        updatePending();

        submitBtn.addEventListener('click', () => {
            const counts = {};
            inputs.forEach(input => {
                counts[input.dataset.ingredientId] = parseFloat(input.value) || 0;
            });

            submitBtn.disabled = true;

            fetch('{{ route("caja.cerrar.inventario.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ counts }),
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'No se pudo guardar el conteo');
                    submitBtn.disabled = false;
                    return;
                }

                document.getElementById('mermas-continue-btn').href = data.redirect;
                const mermasScreen = document.getElementById('mermas-screen');
                mermasScreen.classList.remove('hidden');
                mermasScreen.classList.add('flex');
            });
        });
    </script>
</body>
</html>
