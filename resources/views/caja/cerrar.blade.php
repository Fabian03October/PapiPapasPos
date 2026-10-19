{{--
    Cierre de turno con DOS conteos (pedido del cliente), cada uno con una
    pregunta como en OaxacaWare:
      1. Fondo: el efectivo con el que se abrió (se queda para el siguiente turno).
      2. Ventas: ventas en efectivo + ingresos − gastos (el dinero que se entrega).
    Si cuadra, un toque; si no, se cuentan billetes y monedas y, si hay
    diferencia, se escribe el motivo.
--}}
<x-layouts.app title="Cerrar caja - posPapisV1" active="caja" page-title="Cerrar caja">

    <div class="flex-1 min-h-0 overflow-y-auto">
        <div class="max-w-4xl pb-8">

            <a href="{{ route('caja.resumen') }}" class="flex items-center gap-1.5 text-sm text-neutral-500 dark:text-neutral-400 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Regresar
            </a>

            @foreach ([
                ['key' => 'fund', 'n' => 1, 'title' => 'Fondo de caja', 'expected' => $fundExpected,
                    'question' => '¿Tienes el fondo de <b>$' . number_format($fundExpected, 2) . '</b>?',
                    'detail' => 'El efectivo con el que se abrió el turno. Se queda en la caja para el siguiente.'],
                ['key' => 'sales', 'n' => 2, 'title' => 'Dinero de las ventas', 'expected' => $salesExpected,
                    'question' => '¿Tienes <b>$' . number_format($salesExpected, 2) . '</b> de ventas en efectivo?',
                    'detail' => 'Ventas en efectivo $' . number_format($cashSales, 2)
                        . ($ingresos > 0 ? ' + ingresos $' . number_format($ingresos, 2) : '')
                        . ($gastos > 0 ? ' − gastos $' . number_format($gastos, 2) : '')
                        . '. Es el dinero que se entrega.'],
            ] as $step)
                <x-card class="mb-4 count-step" data-key="{{ $step['key'] }}" data-expected="{{ $step['expected'] }}">
                    <p class="text-xs font-semibold text-primary-600 dark:text-primary-400 mb-1">{{ $step['n'] }} de 2 · {{ $step['title'] }}</p>
                    <p class="text-base text-neutral-900 dark:text-neutral-100 mb-1">{!! $step['question'] !!}</p>
                    <p class="text-xs text-neutral-400 dark:text-neutral-500 mb-4">{{ $step['detail'] }}</p>

                    <div class="grid grid-cols-2 gap-2 step-question">
                        <button type="button" class="step-yes h-11 rounded-lg bg-primary-600 text-white text-sm font-medium">Sí, cuadra</button>
                        <button type="button" class="step-count h-11 rounded-lg border border-neutral-200 dark:border-neutral-700 text-sm font-medium text-neutral-700 dark:text-neutral-300">No, voy a contar</button>
                    </div>

                    <p class="step-ok hidden text-sm font-medium text-green-600 dark:text-green-400">✓ Cuadra · ${{ number_format($step['expected'], 2) }}
                        <button type="button" class="step-reset ml-2 text-xs text-neutral-400 underline">cambiar</button>
                    </p>

                    <div class="step-counting hidden">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-4">
                            <div>
                                <p class="text-xs text-neutral-400 dark:text-neutral-500 mb-2">Billetes</p>
                                <div class="grid grid-cols-[repeat(auto-fill,minmax(4.5rem,1fr))] gap-2 bills-grid"></div>
                            </div>
                            <div>
                                <p class="text-xs text-neutral-400 dark:text-neutral-500 mb-2">Monedas</p>
                                <div class="grid grid-cols-[repeat(auto-fill,minmax(4.5rem,1fr))] gap-2 coins-grid"></div>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-4">
                            <div>
                                <p class="text-xs text-neutral-400 dark:text-neutral-500">Esperado</p>
                                <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">${{ number_format($step['expected'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-neutral-400 dark:text-neutral-500">Contado</p>
                                <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 counted-display">$0.00</p>
                            </div>
                            <div class="difference-box rounded-lg px-4 py-2 text-center bg-neutral-100 dark:bg-neutral-800">
                                <p class="text-xs text-neutral-500 dark:text-neutral-400 difference-label">Diferencia</p>
                                <p class="text-base font-semibold text-neutral-700 dark:text-neutral-300 difference-amount">$0.00</p>
                            </div>
                            <button type="button" class="step-reset text-xs text-neutral-400 underline">mejor sí cuadra</button>
                        </div>
                    </div>
                </x-card>
            @endforeach

            <x-card>
                <div class="flex flex-wrap items-center gap-4 mb-3">
                    <div>
                        <p class="text-xs text-neutral-400 dark:text-neutral-500">Total en caja (fondo + ventas)</p>
                        <p class="text-xl font-semibold text-neutral-900 dark:text-neutral-100" id="total-display">—</p>
                    </div>
                </div>
                <div id="reason-row" class="hidden mb-3">
                    <label for="difference-reason" class="text-sm text-neutral-700 dark:text-neutral-300 block mb-1">¿Por qué no cuadra? <span class="text-neutral-400">· obligatorio</span></label>
                    <input type="text" id="difference-reason" maxlength="255" placeholder="Ej. se dio cambio de más a un cliente"
                        class="w-full h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
                </div>
                <p id="close-error" class="hidden text-sm text-red-500 dark:text-red-400 mb-3"></p>
                <x-button id="confirm-close-btn" disabled class="opacity-50">
                    Cerrar turno
                </x-button>
            </x-card>
        </div>
    </div>

    <!-- Popup de éxito al cerrar -->
    <x-modal id="success-screen" max-width="sm" body-class="p-7 text-center">
        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-green-50 dark:bg-green-500/10 flex items-center justify-center">
            <span class="text-2xl text-green-500 dark:text-green-400">✓</span>
        </div>
        <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-1">Caja cerrada</p>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">El turno quedó registrado y se imprimió el corte.</p>
        <a href="{{ route('venta.index') }}" class="inline-block bg-primary-600 text-white rounded-lg py-3 px-6 text-sm font-medium">Continuar</a>
    </x-modal>

    <script>
        const bills = [1000, 500, 200, 100, 50, 20];
        const coins = [10, 5, 2, 1, 0.5];
        const money = n => '$' + n.toFixed(2);
        const TONES = {
            ok: { box: 'bg-green-50 dark:bg-green-500/10', text: 'text-green-600 dark:text-green-400' },
            over: { box: 'bg-primary-50 dark:bg-primary-500/10', text: 'text-primary-600 dark:text-primary-400' },
            short: { box: 'bg-red-50 dark:bg-red-500/10', text: 'text-red-600 dark:text-red-400' },
        };
        // Por paso: null = sin contestar, 'yes' = cuadra, 'count' = contando.
        const state = { fund: null, sales: null };

        function denomCard(value) {
            const label = value >= 1 ? '$' + value : '$' + value.toFixed(2);
            return `
                <div class="bg-neutral-50 dark:bg-neutral-800 rounded-lg p-2 text-center">
                    <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mb-1">${label}</p>
                    <input type="number" min="0" value="0" data-value="${value}" inputmode="numeric"
                        class="denom-input w-full h-9 text-center rounded-md border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 text-sm">
                </div>
            `;
        }

        const steps = Array.from(document.querySelectorAll('.count-step'));

        function counted(step) {
            let total = 0;
            step.querySelectorAll('.denom-input').forEach(input => {
                total += parseFloat(input.dataset.value) * (parseInt(input.value) || 0);
            });
            return Math.round(total * 100) / 100;
        }

        function stepAmount(step) {
            const key = step.dataset.key;
            if (state[key] === 'yes') return parseFloat(step.dataset.expected);
            if (state[key] === 'count') return counted(step);
            return null;
        }

        function stepDiff(step) {
            const amount = stepAmount(step);
            return amount === null ? 0 : Math.round((amount - parseFloat(step.dataset.expected)) * 100) / 100;
        }

        function render() {
            steps.forEach(step => {
                const key = step.dataset.key;
                step.querySelector('.step-question').classList.toggle('hidden', state[key] !== null);
                step.querySelector('.step-ok').classList.toggle('hidden', state[key] !== 'yes');
                step.querySelector('.step-counting').classList.toggle('hidden', state[key] !== 'count');

                if (state[key] === 'count') {
                    const diff = stepDiff(step);
                    step.querySelector('.counted-display').textContent = money(counted(step));
                    const box = step.querySelector('.difference-box');
                    const label = step.querySelector('.difference-label');
                    const amount = step.querySelector('.difference-amount');
                    // Nombres de clase completos: Tailwind solo genera los que ve escritos.
                    const tone = TONES[diff === 0 ? 'ok' : (diff > 0 ? 'over' : 'short')];
                    box.className = 'difference-box rounded-lg px-4 py-2 text-center ' + tone.box;
                    label.className = 'text-xs difference-label ' + tone.text;
                    amount.className = 'text-base font-semibold difference-amount ' + tone.text;
                    label.textContent = diff === 0 ? 'Cuadra perfecto' : (diff > 0 ? 'Sobra' : 'Falta');
                    amount.textContent = money(Math.abs(diff));
                }
            });

            const answered = steps.every(step => state[step.dataset.key] !== null);
            const hasDiff = steps.some(step => stepDiff(step) !== 0);
            document.getElementById('reason-row').classList.toggle('hidden', !hasDiff);
            document.getElementById('total-display').textContent = answered
                ? money(steps.reduce((sum, step) => sum + stepAmount(step), 0))
                : '—';

            const btn = document.getElementById('confirm-close-btn');
            btn.disabled = !answered;
            btn.classList.toggle('opacity-50', !answered);
        }

        steps.forEach(step => {
            const key = step.dataset.key;
            step.querySelector('.bills-grid').innerHTML = bills.map(denomCard).join('');
            step.querySelector('.coins-grid').innerHTML = coins.map(denomCard).join('');
            step.querySelector('.step-yes').addEventListener('click', () => { state[key] = 'yes'; render(); });
            step.querySelector('.step-count').addEventListener('click', () => { state[key] = 'count'; render(); });
            step.querySelectorAll('.step-reset').forEach(b => b.addEventListener('click', () => {
                state[key] = state[key] === 'count' ? 'yes' : null;
                render();
            }));
            step.querySelectorAll('.denom-input').forEach(input => input.addEventListener('input', render));
        });

        function payload(step) {
            const key = step.dataset.key;
            return {
                matches: state[key] === 'yes',
                denominations: state[key] === 'count'
                    ? Array.from(step.querySelectorAll('.denom-input')).map(input => ({
                        value: parseFloat(input.dataset.value),
                        qty: parseInt(input.value) || 0,
                    }))
                    : [],
            };
        }

        const errorEl = document.getElementById('close-error');

        document.getElementById('confirm-close-btn').addEventListener('click', () => {
            const btn = document.getElementById('confirm-close-btn');
            if (btn.disabled) return;

            const reason = document.getElementById('difference-reason').value.trim();
            if (steps.some(step => stepDiff(step) !== 0) && reason.length < 3) {
                errorEl.textContent = 'Escribe por qué no cuadra.';
                errorEl.classList.remove('hidden');
                return;
            }

            btn.disabled = true;
            errorEl.classList.add('hidden');

            fetch('{{ route("caja.cerrar.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    fund: payload(steps.find(s => s.dataset.key === 'fund')),
                    sales: payload(steps.find(s => s.dataset.key === 'sales')),
                    difference_reason: reason || null,
                }),
            })
            .then(res => res.json())
            .then(async data => {
                if (!data.success) {
                    errorEl.textContent = data.message || 'No se pudo cerrar la caja.';
                    errorEl.classList.remove('hidden');
                    btn.disabled = false;
                    return;
                }

                const successScreen = document.getElementById('success-screen');
                successScreen.classList.remove('hidden');
                successScreen.classList.add('flex');

                // El corte sale por la impresora Bluetooth si este dispositivo
                // la usa; si no, por la impresión de Chrome de siempre.
                try {
                    if (window.ImpresoraBT && ImpresoraBT.isEnabled()) {
                        await ImpresoraBT.imprimirCorte(data.session_id);
                    } else if (typeof PrintDocs !== 'undefined') {
                        PrintDocs.printUrl(`/caja/${data.session_id}/reporte-cierre`);
                    }
                } catch (e) {
                    Toast.show('La caja se cerró, pero el corte no se imprimió: ' + e.message, 'error');
                }
            })
            .catch(() => {
                errorEl.textContent = 'No se pudo cerrar la caja por la conexión. Intenta de nuevo.';
                errorEl.classList.remove('hidden');
                btn.disabled = false;
            });
        });

        render();
    </script>
</x-layouts.app>
