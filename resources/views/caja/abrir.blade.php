<x-layouts.guest title="Abrir caja - posPapisV1">

    <x-card padding="p-7" class="w-full max-w-sm shadow-lg">

        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Abrir caja</p>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ auth()->user()->name }} · {{ now()->translatedFormat('l, j \d\e F') }}</p>
        </div>

        <p id="form-error" class="text-center text-sm text-red-500 dark:text-red-400 mb-3 hidden"></p>

        {{-- Una pregunta (como en OaxacaWare): se propone el fondo que se dejó
             en la caja al cerrar el turno anterior. Si es otro, se escribe. --}}
        @if ($fondoSugerido !== null)
            <div id="question-step" class="mb-5">
                <p class="text-center text-base text-neutral-700 dark:text-neutral-300 mb-4">
                    ¿Empiezas con <span class="font-semibold text-neutral-900 dark:text-neutral-100">${{ number_format($fondoSugerido, 2) }}</span> de fondo?
                </p>
                <x-button id="confirm-suggested-btn" class="mb-2">
                    Sí, iniciar con ${{ number_format($fondoSugerido, 2) }}
                </x-button>
                <button type="button" id="other-amount-btn" class="w-full h-11 rounded-lg border border-neutral-200 dark:border-neutral-700 text-sm font-medium text-neutral-700 dark:text-neutral-300">
                    No, es otra cantidad
                </button>
            </div>
        @endif

        <div id="amount-step" class="{{ $fondoSugerido !== null ? 'hidden' : '' }}">
            <label class="text-sm text-neutral-500 dark:text-neutral-400 block mb-1.5">Fondo inicial en efectivo</label>
            <div class="relative mb-2">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 dark:text-neutral-500">$</span>
                <input type="number" id="opening-amount" step="0.01" min="0" value="{{ $fondoSugerido ?? 0 }}" inputmode="decimal"
                    class="w-full h-12 pl-7 pr-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-lg font-semibold">
            </div>
            <p class="text-xs text-neutral-400 dark:text-neutral-500 mb-5">Cuenta el efectivo físico antes de continuar</p>

            <x-button id="submit-btn" class="mb-3">
                Iniciar turno
            </x-button>
        </div>

        @if ($ultimaCerrada)
            <div class="bg-neutral-50 dark:bg-neutral-800 rounded-lg p-3 mb-5 text-sm">
                <div class="flex justify-between mb-1">
                    <span class="text-neutral-500 dark:text-neutral-400">Última apertura</span>
                    <span class="text-neutral-900 dark:text-neutral-100">{{ $ultimaCerrada->opened_at->diffForHumans() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-neutral-500 dark:text-neutral-400">Fondo que se dejó al cerrar</span>
                    <span class="text-neutral-900 dark:text-neutral-100">${{ number_format($ultimaCerrada->fund_counted_amount ?? $ultimaCerrada->opening_amount ?? 0, 2) }}</span>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="block w-full text-center text-xs text-neutral-400 dark:text-neutral-500 hover:text-neutral-600 dark:hover:text-neutral-300 py-1">
                Cerrar sesión
            </button>
        </form>
    </x-card>

    <script>
        const errorEl = document.getElementById('form-error');

        function abrir(amount) {
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
            })
            .catch(() => {
                errorEl.textContent = 'No se pudo abrir la caja por la conexión. Intenta de nuevo.';
                errorEl.classList.remove('hidden');
            });
        }

        document.getElementById('submit-btn').addEventListener('click', () => {
            abrir(document.getElementById('opening-amount').value);
        });

        @if ($fondoSugerido !== null)
            document.getElementById('confirm-suggested-btn').addEventListener('click', () => abrir(@json((float) $fondoSugerido)));
            document.getElementById('other-amount-btn').addEventListener('click', () => {
                document.getElementById('question-step').classList.add('hidden');
                document.getElementById('amount-step').classList.remove('hidden');
                document.getElementById('opening-amount').select();
            });
        @endif
    </script>
</x-layouts.guest>
