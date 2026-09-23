<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas del turno - posPapisV1</title>
    @vite('resources/css/app.css')
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-zinc-950 min-h-screen">

    <div class="grid grid-cols-1 md:grid-cols-[190px_1fr] gap-4 p-4 md:h-screen">

        @include('partials.sidebar', ['active' => 'historial', 'pageTitle' => 'Ventas del turno'])

        <div class="flex flex-col overflow-y-auto">

            @if (! $session)
                <div class="bg-white dark:bg-zinc-900 rounded-xl p-10 text-center text-sm text-gray-500 dark:text-zinc-400 max-w-2xl mx-auto w-full mt-6">
                    No hay una caja abierta en este momento. Abre un turno para ver su historial de ventas.
                </div>
            @else
                <div class="max-w-3xl mx-auto w-full">

                    <div class="mb-4">
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-zinc-100">Ventas del turno</h1>
                        <p class="text-sm text-gray-500 dark:text-zinc-500">
                            Caja abierta desde las {{ $session->opened_at->format('H:i') }}
                            · {{ $sales->count() }} {{ Str::plural('venta', $sales->count()) }}
                            · ${{ number_format($sales->where('status', '!=', 'cancelada')->sum('total'), 2) }} en total
                        </p>
                    </div>

                    @if ($sales->isEmpty())
                        <div class="bg-white dark:bg-zinc-900 rounded-xl p-10 text-center text-sm text-gray-500 dark:text-zinc-400">
                            Todavía no hay ventas en este turno.
                        </div>
                    @else
                        <div class="flex flex-col gap-2 pb-8">
                            @foreach ($sales as $sale)
                                <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-800">
                                    <button type="button" class="sale-toggle w-full grid grid-cols-[1fr_auto_auto] md:grid-cols-[100px_90px_1fr_140px_auto] items-center gap-3 md:gap-4 px-4 py-3.5 text-left">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-zinc-100">#{{ $sale->folio }}</p>
                                            <p class="text-xs text-gray-500 dark:text-zinc-500 md:hidden">{{ $sale->created_at->format('H:i') }}</p>
                                        </div>

                                        <p class="hidden md:block text-sm text-gray-500 dark:text-zinc-400">{{ $sale->created_at->format('H:i') }}</p>

                                        <div class="hidden md:flex items-center gap-2 text-sm text-gray-500 dark:text-zinc-400 min-w-0">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $sale->payment_method === 'efectivo' ? 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400' : 'bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400' }}">
                                                {{ $sale->payment_method === 'efectivo' ? '💵 Efectivo' : '💳 Tarjeta' }}
                                            </span>
                                            @if ($sale->customer)
                                                <span class="truncate">{{ $sale->customer->name }}</span>
                                            @endif
                                        </div>

                                        <div class="flex items-center justify-end md:justify-start gap-2">
                                            @if ($sale->status === 'cancelada')
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300">Cancelada</span>
                                            @elseif ($sale->items->whereNotNull('cancelled_at')->isNotEmpty())
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300">Con cancelación parcial</span>
                                            @elseif ($sale->incidents->where('type', 'reposicion')->where('status', 'aprobada')->isNotEmpty())
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-gray-100 dark:bg-zinc-800 text-gray-600 dark:text-zinc-300">Con reposición</span>
                                            @elseif ($sale->incidents->where('status', 'pendiente')->isNotEmpty())
                                                <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-400">Pendiente</span>
                                            @endif
                                        </div>

                                        <div class="flex items-center justify-end gap-3">
                                            <span class="text-sm font-semibold {{ $sale->status === 'cancelada' ? 'text-gray-400 dark:text-zinc-600 line-through' : 'text-gray-900 dark:text-zinc-100' }}">
                                                ${{ number_format($sale->total, 2) }}
                                            </span>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 dark:text-zinc-500 chevron transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </div>
                                    </button>

                                    <div class="hidden border-t border-gray-100 dark:border-zinc-800/70 px-4 py-3.5">
                                        <p class="md:hidden text-xs text-gray-500 dark:text-zinc-500 mb-2">
                                            {{ $sale->payment_method === 'efectivo' ? '💵 Efectivo' : '💳 Tarjeta' }}
                                            @if ($sale->customer) · {{ $sale->customer->name }} @endif
                                        </p>

                                        <div class="flex flex-col gap-1.5 mb-1">
                                            @foreach ($sale->items as $item)
                                                <div class="flex items-center justify-between gap-3 py-1">
                                                    <div class="min-w-0">
                                                        <p class="text-sm {{ $item->cancelled_at ? 'text-gray-400 dark:text-zinc-600 line-through' : 'text-gray-700 dark:text-zinc-300' }}">
                                                            <span class="font-medium">{{ $item->qty }}x</span> {{ $item->product->name }}
                                                            @if ($item->modifiers->isNotEmpty())
                                                                <span class="text-xs text-gray-400 dark:text-zinc-500">
                                                                    ({{ $item->modifiers->pluck('modifier.name')->filter()->join(', ') }})
                                                                </span>
                                                            @endif
                                                        </p>
                                                    </div>

                                                    @if ($item->cancelled_at)
                                                        <span class="text-xs text-gray-400 dark:text-zinc-600 shrink-0">Cancelado</span>
                                                    @elseif ($sale->status !== 'cancelada')
                                                        <div class="flex items-center gap-1 shrink-0">
                                                            <button type="button" class="item-incident-btn w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-800"
                                                                data-item-id="{{ $item->id }}" data-type="cancelacion"
                                                                data-label="{{ $item->qty }}x {{ $item->product->name }}"
                                                                data-price="{{ number_format($item->line_total, 2) }}"
                                                                title="Cancelar este producto">
                                                                ✕
                                                            </button>
                                                            <button type="button" class="item-incident-btn w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-800"
                                                                data-item-id="{{ $item->id }}" data-type="reposicion"
                                                                data-label="{{ $item->qty }}x {{ $item->product->name }}"
                                                                data-price="{{ number_format($item->line_total, 2) }}"
                                                                title="Reponer este producto">
                                                                ↻
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        @if ($sale->incidents->isNotEmpty())
                                            <div class="flex flex-col gap-1 mt-2 bg-gray-50 dark:bg-zinc-800/60 rounded-lg p-3">
                                                @foreach ($sale->incidents as $incident)
                                                    <p class="text-xs text-gray-600 dark:text-zinc-400">
                                                        <span class="font-semibold">{{ $incident->type === 'cancelacion' ? 'Cancelación' : 'Reposición' }}</span>
                                                        — {{ ucfirst($incident->status) }}
                                                        @if ($incident->authorizedBy) · autorizó {{ $incident->authorizedBy->name }} @endif
                                                        : {{ $incident->reason }}
                                                    </p>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>

    <!-- Modal de incidencia (minimalista) -->
    <div id="incident-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl w-full max-w-sm overflow-hidden">

            <div id="incident-header" class="px-5 pt-5 pb-4 border-b border-gray-100 dark:border-zinc-800">
                <button id="incident-modal-close" type="button" class="float-right w-8 h-8 rounded-full flex items-center justify-center text-gray-400 dark:text-zinc-500 hover:bg-gray-100 dark:hover:bg-zinc-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <p id="incident-modal-title" class="text-base font-semibold text-gray-900 dark:text-zinc-100">Cancelar producto</p>
                <p id="incident-modal-subtitle" class="text-sm text-gray-500 dark:text-zinc-500 mt-0.5">Se necesita autorización</p>
            </div>

            <div class="p-5">
                <div class="flex items-center justify-between bg-gray-50 dark:bg-zinc-800 rounded-xl px-4 py-3 mb-4">
                    <span id="incident-item-label" class="text-sm text-gray-700 dark:text-zinc-300"></span>
                    <span id="incident-item-price" class="text-sm font-semibold text-gray-900 dark:text-zinc-100"></span>
                </div>

                <textarea id="incident-reason" rows="2" placeholder="Razón (ej. se le cayó un cabello)" class="w-full mb-4 px-3 py-2 rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 text-sm"></textarea>

                <div id="incident-code-step">
                    <p class="text-xs text-center text-gray-500 dark:text-zinc-500 mb-2">Código temporal del manager</p>
                    <input type="text" id="incident-code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="······" class="w-full h-16 rounded-xl border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 text-3xl font-semibold text-center tracking-[0.5em]">
                </div>

                <button id="incident-submit" class="w-full bg-gray-900 hover:bg-black text-white dark:bg-zinc-100 dark:hover:bg-white dark:text-zinc-900 rounded-xl py-3.5 text-sm font-semibold mt-4">
                    Confirmar
                </button>

                <button id="incident-pending-link" type="button" class="w-full text-center text-xs text-gray-400 dark:text-zinc-500 underline mt-3 hidden">
                    ¿El manager no está disponible? Dejar pendiente de aprobación
                </button>
                <p id="incident-pending-note" class="text-center text-xs text-gray-500 dark:text-zinc-400 mt-3 hidden">
                    Se resolverá físicamente ahora; el manager lo autorizará después desde donde esté.
                </p>
            </div>
        </div>
    </div>

    <script>
        @include('partials.sidebar-scripts')

        document.querySelectorAll('.sale-toggle').forEach(btn => {
            const body = btn.parentElement.querySelector('div.border-t');
            btn.addEventListener('click', () => {
                body.classList.toggle('hidden');
                btn.querySelector('.chevron').classList.toggle('rotate-180');
            });
        });

        const incidentModal = document.getElementById('incident-modal');
        const incidentHeader = document.getElementById('incident-header');
        const incidentTitle = document.getElementById('incident-modal-title');
        const incidentSubtitle = document.getElementById('incident-modal-subtitle');
        const incidentItemLabel = document.getElementById('incident-item-label');
        const incidentItemPrice = document.getElementById('incident-item-price');
        const incidentReason = document.getElementById('incident-reason');
        const incidentCodeStep = document.getElementById('incident-code-step');
        const incidentCode = document.getElementById('incident-code');
        const incidentSubmit = document.getElementById('incident-submit');
        const incidentPendingLink = document.getElementById('incident-pending-link');
        const incidentPendingNote = document.getElementById('incident-pending-note');

        let currentItemId = null;
        let currentType = null;
        let mode = 'codigo';


        function resetModalMode() {
            mode = 'codigo';
            incidentCodeStep.classList.remove('hidden');
            incidentCode.value = '';
            incidentPendingNote.classList.add('hidden');
            incidentSubmit.textContent = 'Confirmar';
        }

        document.querySelectorAll('.item-incident-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                currentItemId = btn.dataset.itemId;
                currentType = btn.dataset.type;

                incidentTitle.textContent = currentType === 'cancelacion' ? 'Cancelar producto' : 'Reponer producto';
                incidentSubtitle.textContent = currentType === 'cancelacion'
                    ? 'Se devuelve el insumo al inventario'
                    : 'Se entrega un producto nuevo, sin costo';
                incidentItemLabel.textContent = btn.dataset.label;
                incidentItemPrice.textContent = '$' + btn.dataset.price;
                incidentReason.value = '';

                incidentPendingLink.classList.toggle('hidden', currentType === 'cancelacion');
                resetModalMode();

                incidentModal.classList.remove('hidden');
                incidentModal.classList.add('flex');
            });
        });

        incidentPendingLink.addEventListener('click', () => {
            mode = 'pendiente';
            incidentCodeStep.classList.add('hidden');
            incidentPendingNote.classList.remove('hidden');
            incidentSubmit.textContent = 'Enviar solicitud';
        });

        document.getElementById('incident-modal-close').addEventListener('click', () => {
            incidentModal.classList.add('hidden');
            incidentModal.classList.remove('flex');
        });

                function showToast(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-lg shadow-lg text-sm font-medium flex items-center gap-2 max-w-[90vw] ${isError ? 'bg-gray-900 dark:bg-zinc-100 text-white dark:text-zinc-900' : 'bg-green-600 text-white'}`;
            toast.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    ${isError
                        ? '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />'
                        : '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />'}
                </svg>
                <span>${message}</span>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        incidentSubmit.addEventListener('click', () => {
            if (incidentReason.value.trim().length < 5) {
                showToast('Escribe una razón más detallada.', true);
                return;
            }

            if (mode === 'codigo' && incidentCode.value.trim().length < 4) {
                showToast('Ingresa el código temporal.', true);
                return;
            }

            fetch('{{ route("venta.incidencias.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    sale_item_ids: [currentItemId],
                    type: currentType,
                    reason: incidentReason.value.trim(),
                    mode: mode,
                    code: incidentCode.value.trim(),
                }),
            })
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                if (status === 200 && data.success) {
                    incidentModal.classList.add('hidden');
                    incidentModal.classList.remove('flex');
                    showToast(data.message);
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast(data.message || 'Ocurrió un error.', true);
                }
            });
        });
    </script>
</body>
</html>