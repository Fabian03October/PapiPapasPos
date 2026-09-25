<x-layouts.app title="Corte de caja - posPapisV1" active="caja" page-title="Corte de caja">

    <div class="flex-1 min-h-0 overflow-y-auto">
        <div class="max-w-6xl">

            <div class="flex items-center justify-between mb-4">
                <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 hidden md:block">Corte de caja</p>
                <p class="text-xs text-neutral-400 dark:text-neutral-500">
                    Abierta por {{ $session->user->name }} · {{ $session->opened_at->translatedFormat('j \d\e F, g:i a') }}
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-4">

                <!-- Movimientos -->
                <div class="flex flex-col gap-4">

                    <x-card>
                        <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mb-3">Nuevo movimiento</p>

                        <div class="grid grid-cols-1 sm:grid-cols-[110px_1fr_1fr_auto] gap-2">
                            <select id="movement-type" class="h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
                                <option value="ingreso">Ingreso</option>
                                <option value="gasto">Gasto</option>
                            </select>
                            <input type="number" id="movement-amount" step="0.01" placeholder="0.00"
                                class="h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
                            <input type="text" id="movement-reason" placeholder="Ej. compra de hielo"
                                class="h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
                            <button id="submit-movement-btn" class="h-11 px-5 bg-primary-600 text-white rounded-lg text-sm font-medium whitespace-nowrap">
                                Agregar
                            </button>
                        </div>
                    </x-card>

                    <x-card>
                        <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mb-3">Movimientos del turno</p>

                        <!-- Tabla: desktop -->
                        <div class="hidden lg:block overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-neutral-400 dark:text-neutral-500 border-b border-neutral-100 dark:border-neutral-800">
                                        <th class="pb-2 font-medium">Hora</th>
                                        <th class="pb-2 font-medium">Motivo</th>
                                        <th class="pb-2 font-medium">Tipo</th>
                                        <th class="pb-2 font-medium text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody id="movements-tbody">
                                    <tr>
                                        <td class="py-2.5 text-neutral-500 dark:text-neutral-400 whitespace-nowrap">{{ $session->opened_at->format('g:i a') }}</td>
                                        <td class="py-2.5 font-medium text-neutral-900 dark:text-neutral-100">Fondo inicial</td>
                                        <td class="py-2.5 text-neutral-500 dark:text-neutral-400">Apertura</td>
                                        <td class="py-2.5 text-right font-medium text-green-600 dark:text-green-400">+${{ number_format($session->opening_amount, 2) }}</td>
                                    </tr>
                                    @foreach ($session->movements as $movement)
                                        <tr>
                                            <td class="py-2.5 text-neutral-500 dark:text-neutral-400 whitespace-nowrap">{{ $movement->created_at->format('g:i a') }}</td>
                                            <td class="py-2.5 font-medium text-neutral-900 dark:text-neutral-100">{{ $movement->reason }}</td>
                                            <td class="py-2.5 text-neutral-500 dark:text-neutral-400">{{ $movement->type === 'ingreso' ? 'Ingreso' : 'Gasto' }}</td>
                                            <td class="py-2.5 text-right font-medium {{ $movement->type === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $movement->type === 'ingreso' ? '+' : '-' }}${{ number_format($movement->amount, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Tarjetas: celular/tablet -->
                        <div class="lg:hidden flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-neutral-100 dark:border-neutral-800 px-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100 truncate">Fondo inicial</p>
                                    <p class="text-xs text-neutral-400 dark:text-neutral-500">Apertura · {{ $session->opened_at->format('g:i a') }}</p>
                                </div>
                                <p class="text-sm font-medium text-green-600 dark:text-green-400 shrink-0">+${{ number_format($session->opening_amount, 2) }}</p>
                            </div>
                            @foreach ($session->movements as $movement)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-neutral-100 dark:border-neutral-800 px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100 truncate">{{ $movement->reason }}</p>
                                        <p class="text-xs text-neutral-400 dark:text-neutral-500">{{ $movement->type === 'ingreso' ? 'Ingreso' : 'Gasto' }} · {{ $movement->created_at->format('g:i a') }}</p>
                                    </div>
                                    <p class="text-sm font-medium shrink-0 {{ $movement->type === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $movement->type === 'ingreso' ? '+' : '-' }}${{ number_format($movement->amount, 2) }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                </div>

                <!-- Resumen -->
                <div class="flex flex-col gap-4">
                    <x-card>
                        <p class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 mb-3">Resumen del turno</p>

                        <div class="flex flex-col gap-2.5 text-sm">
                            <div class="flex justify-between">
                                <span class="text-neutral-500 dark:text-neutral-400">Fondo inicial</span>
                                <span class="text-neutral-900 dark:text-neutral-100">${{ number_format($session->opening_amount, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-500 dark:text-neutral-400">Ventas en efectivo</span>
                                <span class="text-neutral-900 dark:text-neutral-100">${{ number_format($cashSales, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-500 dark:text-neutral-400">Ingresos</span>
                                <span class="text-green-600 dark:text-green-400">+${{ number_format($ingresos, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-500 dark:text-neutral-400">Gastos</span>
                                <span class="text-red-600 dark:text-red-400">-${{ number_format($gastos, 2) }}</span>
                            </div>
                            <div class="border-t border-neutral-100 dark:border-neutral-800 pt-2.5 flex justify-between">
                                <span class="font-medium text-neutral-900 dark:text-neutral-100">Efectivo esperado</span>
                                <span class="font-semibold text-neutral-900 dark:text-neutral-100">${{ number_format($expected, 2) }}</span>
                            </div>
                        </div>
                    </x-card>

                    <a href="{{ route('caja.cerrar.inventario') }}" class="flex items-center justify-center gap-2 w-full bg-red-600 text-white rounded-xl py-3.5 text-sm font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        Cerrar caja
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('submit-movement-btn').addEventListener('click', () => {
            const type = document.getElementById('movement-type').value;
            const amount = document.getElementById('movement-amount').value;
            const reason = document.getElementById('movement-reason').value.trim();

            if (!amount || !reason) return;

            fetch('{{ route("caja.movimientos.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ type, amount, reason }),
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) window.location.reload();
            });
        });
    </script>
</x-layouts.app>
