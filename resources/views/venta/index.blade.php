<x-layouts.app title="Vender - posPapisV1" active="vender" page-title="Vender">

    <div class="flex flex-col lg:flex-row gap-4 min-h-0 flex-1">

        <div class="flex-1 min-h-0 flex flex-col overflow-hidden">

            <div class="flex gap-2 mb-3 overflow-x-auto pb-1" id="category-tabs">
                <button class="category-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap bg-primary-600 text-white" data-category="all">
                    Todos
                </button>
                @foreach ($categories as $category)
                <button class="category-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-800" data-category="{{ $category->id }}">
                    {{ $category->name }}
                </button>
                @endforeach
            </div>

            <div class="relative mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input type="text" id="product-search" placeholder="Buscar producto" class="w-full h-11 pl-9 pr-3 rounded-lg border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100 text-sm placeholder:text-neutral-400 dark:placeholder:text-neutral-500">
            </div>

            <div class="overflow-y-auto flex-1 min-h-0">
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3" id="product-grid">
                    @foreach ($products as $categoryId => $categoryProducts)
                    @foreach ($categoryProducts as $product)
                    <button type="button" class="product-card bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-800 rounded-xl p-3 text-center hover:border-primary-300 dark:hover:border-primary-500/50 hover:shadow-sm transition" data-category="{{ $categoryId }}" data-name="{{ strtolower($product->name) }}" data-id="{{ $product->id }}">
                        <div class="w-full aspect-square bg-neutral-100 dark:bg-neutral-800 rounded-lg mb-2 flex items-center justify-center text-neutral-400 dark:text-neutral-600 overflow-hidden">
                            @if ($product->image_url)
                            <img src="{{ $product->image_url }}" alt="" loading="lazy" class="w-full h-full object-cover">
                            @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0012.016 15a4.486 4.486 0 00-3.198 1.318M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z" />
                            </svg>
                            @endif
                        </div>
                        <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $product->name }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-500 mt-0.5">${{ number_format($product->base_price, 2) }}</p>
                    </button>
                    @endforeach
                    @endforeach
                </div>

                <p id="no-products-msg" class="text-center text-sm text-neutral-400 dark:text-neutral-600 mt-10 hidden">
                    No hay productos en esta categoría
                </p>
            </div>
        </div>

        <div class="bg-neutral-50 dark:bg-neutral-900 rounded-2xl p-4 lg:w-[280px] shrink-0 flex flex-col min-h-0 max-h-[45vh] lg:max-h-none">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-medium flex items-center gap-2 text-neutral-900 dark:text-neutral-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.976-4.766 2.53-7.352.104-.487-.263-.898-.762-.898H5.106M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                    Carrito
                </p>
                <button id="cart-clear" type="button" class="text-xs text-red-500 dark:text-red-400 hidden">Vaciar</button>
            </div>
            <button type="button" id="undo-last-link" class="hidden -mt-1 mb-2 text-left text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">↶ Deshacer última venta</button>

            <div id="selected-customer-badge" class="hidden items-center justify-between bg-primary-50 dark:bg-primary-500/10 rounded-lg px-3 py-2 mb-3">
                <span class="text-sm text-primary-700 dark:text-primary-400 flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span id="selected-customer-name"></span>
                </span>
                <button type="button" id="customer-edit-btn" class="ml-auto mr-2 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline" title="Corregir datos del cliente">✎ Editar</button>
                <a href="{{ route('venta.customers.clear') }}" class="text-primary-400 dark:text-primary-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <div class="flex-1 overflow-y-auto min-h-0" id="cart-items-container">
                <div class="h-full flex items-center justify-center text-center" id="cart-empty">
                    <p class="text-sm text-neutral-400 dark:text-neutral-600">Toca un producto<br>para agregarlo</p>
                </div>
                <div class="hidden flex-col gap-1" id="cart-items"></div>
            </div>

            <div class="border-t border-neutral-200 dark:border-neutral-800 pt-3 mt-3 shrink-0">
                <button id="sale-discount-btn" type="button" class="hidden w-full mb-2 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline text-left">
                    🏷 Descuento o cortesía a toda la venta
                </button>
                <div id="sale-discount-chip" class="hidden items-center justify-between gap-2 mb-2 bg-green-50 dark:bg-green-500/10 rounded-lg px-3 py-2">
                    <button type="button" id="sale-discount-edit" class="text-xs text-green-700 dark:text-green-400 text-left min-w-0 truncate"></button>
                    <button type="button" id="sale-discount-remove" class="text-green-700 dark:text-green-400 shrink-0" title="Quitar descuento">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex justify-between text-lg font-semibold mb-3 text-neutral-900 dark:text-neutral-100">
                    <span>Total</span>
                    <span id="cart-total">$0.00</span>
                </div>
                <x-button id="cart-checkout" disabled>
                    Cobrar
                </x-button>
            </div>
        </div>

    </div>

    <!-- Popup de modificadores -->
    <x-modal id="modifier-modal" max-width="md">
        <x-slot:header>
            <div>
                <p id="modal-product-name" class="text-lg font-semibold text-neutral-900 dark:text-neutral-100"></p>
                <p id="modal-product-price" class="text-sm text-neutral-500 dark:text-neutral-400"></p>
            </div>
            <button id="modal-close" type="button" class="w-8 h-8 rounded-full flex items-center justify-center text-neutral-400 dark:text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </x-slot:header>

        <div id="modal-loading" class="text-center py-10 text-sm text-neutral-400 dark:text-neutral-600">Cargando…</div>

        <div id="modal-body" class="hidden space-y-4">
            <div id="modal-variants-section" class="hidden">
                <p class="text-sm font-medium mb-2 text-neutral-900 dark:text-neutral-100">Opciones <span class="text-neutral-400 dark:text-neutral-500 font-normal">· elige 1</span></p>
                <div id="modal-variants" class="flex gap-2 flex-wrap"></div>
            </div>

            <div id="modal-groups" class="space-y-1"></div>

            <div>
                <label class="text-sm font-medium text-neutral-900 dark:text-neutral-100 block mb-2">Notas para cocina <span class="text-neutral-400 dark:text-neutral-500 font-normal">· opcional</span></label>
                <input type="text" id="modal-notes" placeholder="Ej. sin popote" class="w-full h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
            </div>
        </div>

        <x-slot:footer>
            <button id="modal-add-btn" class="w-full bg-primary-600 text-white rounded-lg h-12 text-sm font-medium flex items-center justify-between px-4">
                <span>Agregar al carrito</span>
                <span id="modal-add-total">$0.00</span>
            </button>
        </x-slot:footer>
    </x-modal>

    <!-- Popup de cobro -->
    <x-modal id="payment-modal" max-width="sm">
        <x-slot:header>
            <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Cobrar</p>
            <button id="payment-modal-close" type="button" class="w-8 h-8 rounded-full flex items-center justify-center text-neutral-400 dark:text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </x-slot:header>

        <div class="bg-neutral-50 dark:bg-neutral-800 rounded-lg p-3 mb-4">
            <div class="flex justify-between text-sm text-neutral-500 dark:text-neutral-400 mb-1">
                <span>Subtotal</span>
                <span id="payment-subtotal">$0.00</span>
            </div>
            <div id="payment-discount-lines" class="flex flex-col gap-0.5 mb-1"></div>
            <div id="payment-gift-row" class="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400 mb-1 hidden bg-amber-50 dark:bg-amber-500/10 rounded p-2">
                <span>🎁</span>
                <span id="payment-gift-text">Regalo por fidelidad</span>
            </div>
            <div class="flex justify-between text-lg font-semibold text-neutral-900 dark:text-neutral-100 border-t border-neutral-200 dark:border-neutral-700 pt-2 mt-1">
                <span>Total</span>
                <span id="payment-total">$0.00</span>
            </div>
            <button id="payment-sale-discount-btn" type="button" class="mt-2 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                🏷 Descuento o cortesía a toda la venta
            </button>
        </div>

        <!-- Pregunta de canje de premio: solo aparece si hay uno disponible -->
        <div id="reward-prompt" class="hidden bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-lg p-3 mb-4">
            <p id="reward-prompt-text" class="text-sm text-amber-700 dark:text-amber-400 mb-1 font-medium"></p>
            <p id="reward-prompt-expires" class="text-xs text-amber-600 dark:text-amber-500 mb-2 hidden"></p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-2">¿El cliente quiere canjearlo ahora?</p>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" id="reward-answer-yes" class="reward-answer-btn h-10 rounded-lg border text-sm font-medium border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300">
                    Sí, canjear
                </button>
                <button type="button" id="reward-answer-no" class="reward-answer-btn h-10 rounded-lg border text-sm font-medium border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300">
                    No, después
                </button>
            </div>
        </div>

        {{-- Cobrar con un toque (como OaxacaWare): cada botón cobra en ese
             momento, sin "Confirmar". Se habilitan cuando ya se calculó el
             total (promociones, descuentos) y, si hay premio de lealtad,
             cuando ya se contestó si lo canjea. --}}
        <p id="pay-wait" class="hidden text-center text-xs text-amber-600 dark:text-amber-400 mb-2">Contesta primero si el cliente canjea su premio.</p>

        <div id="pay-buttons">
            <button type="button" id="pay-exact" class="pay-btn w-full min-h-14 rounded-xl bg-green-600 text-white text-base font-semibold mb-2 flex items-center justify-center gap-2">
                💵 Efectivo exacto <span id="pay-exact-amount"></span>
            </button>
            <div id="pay-bills" class="grid grid-cols-3 gap-2 mb-2"></div>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <button type="button" id="pay-card" class="pay-btn min-h-12 rounded-xl border-2 border-primary-600 text-primary-600 dark:text-primary-400 text-sm font-semibold">💳 Tarjeta</button>
                <button type="button" id="pay-transfer" class="pay-btn min-h-12 rounded-xl border-2 border-primary-600 text-primary-600 dark:text-primary-400 text-sm font-semibold">🏦 Transferencia</button>
            </div>
            <button type="button" id="pay-other" class="pay-btn w-full h-10 rounded-lg text-sm text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800">Otro monto en efectivo</button>
        </div>

        <div id="pay-other-section" class="hidden">
            <p class="text-sm font-medium mb-2 text-neutral-900 dark:text-neutral-100">Monto recibido</p>
            <div class="relative mb-2">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 dark:text-neutral-500">$</span>
                <input type="number" id="amount-received" step="0.01" inputmode="decimal" class="w-full h-12 pl-7 pr-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-lg font-semibold">
            </div>
            <div class="flex justify-between items-center bg-green-50 dark:bg-green-500/10 rounded-lg p-3 mb-3">
                <span id="change-label" class="text-sm font-medium text-green-700 dark:text-green-400">Cambio</span>
                <span id="change-amount" class="text-lg font-semibold text-green-700 dark:text-green-400">$0.00</span>
            </div>
            <button type="button" id="pay-other-ok" class="pay-btn w-full min-h-12 rounded-xl bg-green-600 text-white text-base font-semibold mb-2">Cobrar</button>
            <button type="button" id="pay-other-back" class="w-full h-9 text-xs text-neutral-500 dark:text-neutral-400">← Volver a las formas de pago</button>
        </div>

        <p id="pay-saving" class="hidden text-center text-sm text-neutral-500 dark:text-neutral-400 mt-2">Guardando la venta…</p>
    </x-modal>

    <!-- Venta registrada + Deshacer (la orden regresa al carrito para corregirla) -->
    <div id="last-sale" class="hidden fixed top-4 left-1/2 -translate-x-1/2 z-[56] items-center gap-3 rounded-xl bg-green-600 text-white px-4 py-3 shadow-lg max-w-[94vw] flex-wrap">
        <div id="last-sale-main" class="flex items-center gap-3 flex-wrap">
            <span class="text-sm">✓ Venta <b id="last-sale-folio"></b> · <span id="last-sale-detail"></span></span>
            <button type="button" id="last-sale-undo" class="min-h-10 px-4 rounded-lg text-sm font-semibold bg-white/20">↶ Deshacer</button>
            <button type="button" id="last-sale-close" class="min-h-10 px-2 text-sm opacity-80" aria-label="Cerrar">✕</button>
        </div>
        <div id="last-sale-reasons" class="hidden flex items-center gap-2 flex-wrap">
            <span class="text-sm font-medium">¿Por qué?</span>
            <button type="button" class="last-sale-reason min-h-10 px-3 rounded-lg text-sm font-medium bg-white/20" data-reason="Error de captura">Error de captura</button>
            <button type="button" class="last-sale-reason min-h-10 px-3 rounded-lg text-sm font-medium bg-white/20" data-reason="El cliente cambió de opinión">El cliente cambió de opinión</button>
            <button type="button" class="last-sale-reason min-h-10 px-3 rounded-lg text-sm font-medium bg-white/20" data-reason="Método de pago equivocado">Método de pago equivocado</button>
            <button type="button" id="last-sale-cancel" class="min-h-10 px-3 rounded-lg text-sm opacity-80">No deshacer</button>
        </div>
    </div>

    <!-- "Deshacer" al quitar un renglón o vaciar el carrito -->
    <div id="cart-undo" class="hidden fixed bottom-20 left-1/2 -translate-x-1/2 z-[55] items-center gap-3 rounded-xl bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900 px-4 py-2 shadow-lg max-w-[92vw]">
        <span id="cart-undo-text" class="text-sm"></span>
        <button type="button" id="cart-undo-btn" class="h-9 px-3 rounded-lg text-sm font-bold underline">Deshacer</button>
    </div>

    <!-- Aviso: la impresora Bluetooth se desconectó y quedó un ticket sin imprimir -->
    <div id="bt-pending" class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-[55] items-center gap-3 bg-amber-50 dark:bg-amber-500/15 border border-amber-200 dark:border-amber-500/30 rounded-xl px-4 py-3 shadow-lg max-w-[92vw]">
        <p id="bt-pending-text" class="text-sm text-amber-800 dark:text-amber-300">🖨 El ticket no se ha impreso.</p>
        <button id="bt-pending-print" type="button" class="shrink-0 h-9 px-3 rounded-lg bg-amber-600 text-white text-sm font-medium">Imprimir aquí</button>
        <button id="bt-pending-dismiss" type="button" class="shrink-0 text-amber-700 dark:text-amber-400 text-sm">Omitir</button>
    </div>

    <!-- Corregir datos del cliente desde la venta -->
    <x-modal id="customer-modal" max-width="sm">
        <x-slot:header>
            <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Datos del cliente</p>
            <button id="customer-modal-close" type="button" class="w-8 h-8 rounded-full flex items-center justify-center text-neutral-400 dark:text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 shrink-0">✕</button>
        </x-slot:header>

        <div class="flex flex-col gap-3">
            <label class="text-sm text-neutral-600 dark:text-neutral-400">Nombre
                <input type="text" id="customer-name-input" maxlength="255" class="mt-1 w-full h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
            </label>
            <label class="text-sm text-neutral-600 dark:text-neutral-400">Teléfono (10 dígitos)
                <input type="tel" id="customer-phone-input" maxlength="10" inputmode="numeric" class="mt-1 w-full h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
            </label>
            <label class="text-sm text-neutral-600 dark:text-neutral-400">Cumpleaños <span class="text-neutral-400">· opcional</span>
                <input type="date" id="customer-birthdate-input" max="{{ now()->toDateString() }}" class="mt-1 w-full h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
            </label>
            <p id="customer-modal-error" class="hidden text-sm text-red-500 dark:text-red-400"></p>
        </div>

        <x-slot:footer>
            <button id="customer-save-btn" type="button" class="w-full bg-primary-600 text-white rounded-lg h-12 text-sm font-medium">Guardar</button>
        </x-slot:footer>
    </x-modal>

    <!-- Popup de descuento / cortesía manual (a un producto o a toda la venta) -->
    <x-modal id="discount-modal" max-width="sm">
        <x-slot:header>
            <div>
                <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Descuento o cortesía</p>
                <p id="discount-modal-target" class="text-sm text-neutral-500 dark:text-neutral-400"></p>
            </div>
            <button id="discount-modal-close" type="button" class="w-8 h-8 rounded-full flex items-center justify-center text-neutral-400 dark:text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800 shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </x-slot:header>

        <div class="grid grid-cols-3 gap-2 mb-4">
            <button type="button" data-discount-type="percent" class="discount-type-btn h-11 rounded-lg border text-sm font-medium">% Porcentaje</button>
            <button type="button" data-discount-type="amount" class="discount-type-btn h-11 rounded-lg border text-sm font-medium">$ Monto</button>
            <button type="button" data-discount-type="courtesy" class="discount-type-btn h-11 rounded-lg border text-sm font-medium">🎁 Cortesía</button>
        </div>

        <div id="discount-value-section" class="mb-4">
            <div class="relative">
                <span id="discount-value-prefix" class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 dark:text-neutral-500"></span>
                <input type="number" id="discount-value" step="0.01" min="0" inputmode="decimal" class="w-full h-11 pl-7 pr-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-lg font-semibold">
            </div>
        </div>

        <p class="text-sm font-medium mb-2 text-neutral-900 dark:text-neutral-100">Motivo <span class="text-neutral-400 dark:text-neutral-500 font-normal">· obligatorio</span></p>
        <div class="flex gap-2 flex-wrap mb-2">
            @foreach (['Cliente frecuente', 'Error en el pedido', 'Cortesía de la casa', 'Empleado', 'Queja del cliente'] as $reason)
                <button type="button" class="discount-reason-chip px-3 py-1.5 rounded-full text-xs border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300">{{ $reason }}</button>
            @endforeach
        </div>
        <input type="text" id="discount-reason" maxlength="255" placeholder="Escribe o elige un motivo" class="w-full h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">

        <x-slot:footer>
            <div class="flex gap-2">
                <button id="discount-remove-btn" type="button" class="hidden h-12 px-4 rounded-lg border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 text-sm font-medium">Quitar</button>
                <button id="discount-apply-btn" type="button" class="flex-1 bg-primary-600 text-white rounded-lg h-12 text-sm font-medium">Aplicar</button>
            </div>
        </x-slot:footer>
    </x-modal>

    <script>
        // --- Categorías + búsqueda ---
        const tabs = document.querySelectorAll('.category-tab');
        const productCards = document.querySelectorAll('.product-card');
        const searchInput = document.getElementById('product-search');
        const noProductsMsg = document.getElementById('no-products-msg');
        let activeCategory = 'all';

        function applyFilters() {
            const search = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            productCards.forEach(card => {
                const matchesCategory = activeCategory === 'all' || card.dataset.category === activeCategory;
                const matchesSearch = card.dataset.name.includes(search);
                const visible = matchesCategory && matchesSearch;
                card.classList.toggle('hidden', !visible);
                if (visible) visibleCount++;
            });

            noProductsMsg.classList.toggle('hidden', visibleCount > 0);
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => {
                    t.classList.remove('bg-primary-600', 'text-white');
                    t.classList.add('bg-white', 'dark:bg-neutral-900', 'text-neutral-600', 'dark:text-neutral-400', 'border', 'border-neutral-200', 'dark:border-neutral-800');
                });
                tab.classList.add('bg-primary-600', 'text-white');
                tab.classList.remove('bg-white', 'dark:bg-neutral-900', 'text-neutral-600', 'dark:text-neutral-400', 'border', 'border-neutral-200', 'dark:border-neutral-800');

                activeCategory = tab.dataset.category;
                applyFilters();
            });
        });

        searchInput.addEventListener('input', applyFilters);

        // --- CARRITO ---
        let cart = [];
        let cartItemIdCounter = 1;
        let autoGiftCartId = null; // cartId del premio de fidelidad que el sistema agregó solo
        let selectedCustomer = @if(session('selected_customer_id')) { id: {{ session('selected_customer_id') }}, name: @json(session('selected_customer_name')) } @else null @endif;

        const cartEmpty = document.getElementById('cart-empty');
        const cartItemsEl = document.getElementById('cart-items');
        const cartTotalEl = document.getElementById('cart-total');
        const cartCheckoutBtn = document.getElementById('cart-checkout');
        const cartClearBtn = document.getElementById('cart-clear');
        const selectedCustomerBadge = document.getElementById('selected-customer-badge');
        const selectedCustomerNameEl = document.getElementById('selected-customer-name');

        function updateCustomerBadge() {
            if (selectedCustomer) {
                selectedCustomerBadge.classList.remove('hidden');
                selectedCustomerBadge.classList.add('flex');
                selectedCustomerNameEl.textContent = selectedCustomer.name;
            } else {
                selectedCustomerBadge.classList.add('hidden');
                selectedCustomerBadge.classList.remove('flex');
            }
        }

        function formatMoney(n) {
            return '$' + n.toFixed(2);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        // --- Descuentos manuales del cajero ---
        // Mismo cálculo que ManualDiscountService (el servidor es quien manda
        // al cobrar; esto es solo para que el carrito muestre el total real).
        let saleDiscount = null; // { type, value, reason } a toda la venta

        function discountAmount(discount, base) {
            if (!discount || base <= 0) return 0;
            let amount = 0;
            if (discount.type === 'courtesy') amount = base;
            else if (discount.type === 'percent') amount = base * Math.min(100, discount.value) / 100;
            else amount = Math.min(discount.value, base);
            return Math.round(Math.min(amount, base) * 100) / 100;
        }

        function discountLabel(discount) {
            const label = discount.type === 'courtesy' ? 'Cortesía'
                : discount.type === 'percent' ? 'Desc. ' + discount.value + '%'
                : 'Desc. ' + formatMoney(discount.value);
            return label + ' (' + discount.reason + ')';
        }

        function cartTotals() {
            const itemsNet = cart.reduce((sum, item) => sum + item.lineTotal - discountAmount(item.discount, item.lineTotal), 0);
            return itemsNet - discountAmount(saleDiscount, itemsNet);
        }

        function renderSaleDiscount() {
            const btn = document.getElementById('sale-discount-btn');
            const chip = document.getElementById('sale-discount-chip');
            btn.classList.toggle('hidden', cart.length === 0 || saleDiscount !== null);
            chip.classList.toggle('hidden', saleDiscount === null);
            chip.classList.toggle('flex', saleDiscount !== null);
            if (saleDiscount) {
                document.getElementById('sale-discount-edit').textContent = '🏷 ' + discountLabel(saleDiscount);
            }
            document.getElementById('payment-sale-discount-btn').textContent = saleDiscount
                ? '🏷 Cambiar o quitar el descuento a la venta'
                : '🏷 Descuento o cortesía a toda la venta';
        }

        function renderCart() {
            if (cart.length === 0) {
                cartEmpty.classList.remove('hidden');
                cartEmpty.classList.add('flex');
                cartItemsEl.classList.add('hidden');
                cartItemsEl.classList.remove('flex');
                cartClearBtn.classList.add('hidden');
                cartCheckoutBtn.disabled = true;
            } else {
                cartEmpty.classList.add('hidden');
                cartEmpty.classList.remove('flex');
                cartItemsEl.classList.remove('hidden');
                cartItemsEl.classList.add('flex');
                cartClearBtn.classList.remove('hidden');
                cartCheckoutBtn.disabled = false;
            }

            cartItemsEl.innerHTML = cart.map(item => {
                const detailsParts = [];
                if (item.variantName) detailsParts.push(item.variantName);
                if (item.modifierNames.length) detailsParts.push(item.modifierNames.join(', '));
                if (item.notes) detailsParts.push('📝 ' + item.notes);
                const details = detailsParts.join(' · ');
                const itemDiscount = discountAmount(item.discount, item.lineTotal);
                const isAutoGift = item.cartId === autoGiftCartId;

                // Tocar el renglón reabre sus opciones (aderezos, extras,
                // notas) para editarlo; − y + cambian la cantidad.
                return `
                    <div class="border-b border-neutral-200 dark:border-neutral-800 py-2">
                    <div class="flex items-start gap-2">
                        <button type="button" data-cart-id="${item.cartId}" class="cart-edit-btn flex-1 min-w-0 text-left" ${isAutoGift ? 'disabled' : ''} title="Tocar para cambiar opciones o notas">
                            <p class="text-sm text-neutral-900 dark:text-neutral-100 truncate">${escapeHtml(item.name)}</p>
                            ${details ? `<p class="text-xs text-neutral-500 dark:text-neutral-500 truncate">${escapeHtml(details)}</p>` : ''}
                            ${itemDiscount > 0 ? `<p class="text-xs text-green-600 dark:text-green-400 truncate">${escapeHtml(discountLabel(item.discount))}</p>` : ''}
                        </button>
                        <div class="text-right whitespace-nowrap">
                            ${itemDiscount > 0 ? `<p class="text-xs text-neutral-400 dark:text-neutral-500 line-through">${formatMoney(item.lineTotal)}</p>` : ''}
                            <p class="text-sm text-neutral-900 dark:text-neutral-100">${formatMoney(item.lineTotal - itemDiscount)}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-1.5">
                        ${isAutoGift ? `<span class="text-xs text-neutral-400">x${item.qty}</span>` : `
                        <button type="button" data-cart-id="${item.cartId}" data-step="-1" class="cart-qty-btn w-8 h-8 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 text-base leading-none" aria-label="Uno menos">−</button>
                        <span class="w-6 text-center text-sm font-semibold text-neutral-900 dark:text-neutral-100">${item.qty}</span>
                        <button type="button" data-cart-id="${item.cartId}" data-step="1" class="cart-qty-btn w-8 h-8 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 text-base leading-none" aria-label="Uno más">+</button>`}
                        <span class="flex-1"></span>
                        ${isAutoGift ? '' : `
                        <button data-cart-id="${item.cartId}" class="cart-discount-btn shrink-0 text-sm ${item.discount ? 'opacity-100' : 'opacity-40 hover:opacity-100'}" title="Descuento o cortesía">🏷</button>`}
                        <button data-cart-id="${item.cartId}" class="cart-remove-btn text-red-400 dark:text-red-500 shrink-0" title="Quitar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                    </div>
                `;
            }).join('');

            if (cart.length === 0) saleDiscount = null;
            cartTotalEl.textContent = formatMoney(cartTotals());
            renderSaleDiscount();

            document.querySelectorAll('.cart-discount-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const item = cart.find(i => i.cartId === Number(btn.dataset.cartId));
                    if (item) openDiscountModal(item);
                });
            });

            document.querySelectorAll('.cart-edit-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const item = cart.find(i => i.cartId === Number(btn.dataset.cartId));
                    if (item && item.cartId !== autoGiftCartId) openModal(item.productId, item);
                });
            });

            document.querySelectorAll('.cart-qty-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const item = cart.find(i => i.cartId === Number(btn.dataset.cartId));
                    if (!item) return;
                    if (item.qty + Number(btn.dataset.step) < 1) {
                        removeFromCart(item.cartId);
                        return;
                    }
                    item.qty += Number(btn.dataset.step);
                    item.lineTotal = item.unitPrice * item.qty;
                    renderCart();
                });
            });

            document.querySelectorAll('.cart-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => removeFromCart(Number(btn.dataset.cartId)));
            });
        }

        // --- "Deshacer" al quitar un renglón o vaciar el carrito ---
        // (los clientes cambian de opinión y el cajero se equivoca de botón).
        const cartUndo = document.getElementById('cart-undo');
        let cartUndoTimer = null;
        let cartUndoSnapshot = null;

        function snapshotCart() {
            return { cart: cart.map(item => ({ ...item })), autoGiftCartId, saleDiscount };
        }

        function offerCartUndo(text, snapshot) {
            cartUndoSnapshot = snapshot;
            document.getElementById('cart-undo-text').textContent = text;
            cartUndo.classList.remove('hidden');
            cartUndo.classList.add('flex');
            clearTimeout(cartUndoTimer);
            cartUndoTimer = setTimeout(hideCartUndo, 6000);
        }

        function hideCartUndo() {
            cartUndo.classList.add('hidden');
            cartUndo.classList.remove('flex');
            cartUndoSnapshot = null;
        }

        document.getElementById('cart-undo-btn').addEventListener('click', () => {
            if (!cartUndoSnapshot) return;
            cart = cartUndoSnapshot.cart;
            autoGiftCartId = cartUndoSnapshot.autoGiftCartId;
            saleDiscount = cartUndoSnapshot.saleDiscount;
            hideCartUndo();
            renderCart();
        });

        function removeFromCart(cartId) {
            const item = cart.find(i => i.cartId === cartId);
            if (!item) return;
            const snapshot = snapshotCart();
            cart = cart.filter(i => i.cartId !== cartId);
            if (autoGiftCartId === cartId) autoGiftCartId = null;
            renderCart();
            offerCartUndo('Se quitó ' + item.name, snapshot);
        }

        cartClearBtn.addEventListener('click', () => {
            if (cart.length) offerCartUndo('Se vació el carrito', snapshotCart());
            cart = [];
            autoGiftCartId = null;
            saleDiscount = null;
            renderCart();
        });

        // --- POPUP DE DESCUENTO ---
        const discountModal = document.getElementById('discount-modal');
        const discountValueSection = document.getElementById('discount-value-section');
        const discountValueInput = document.getElementById('discount-value');
        const discountValuePrefix = document.getElementById('discount-value-prefix');
        const discountReasonInput = document.getElementById('discount-reason');
        const discountRemoveBtn = document.getElementById('discount-remove-btn');
        let discountTarget = null; // un item del carrito, o 'sale' para toda la venta
        let discountType = 'percent';

        function setDiscountType(type) {
            discountType = type;
            document.querySelectorAll('.discount-type-btn').forEach(btn => {
                const active = btn.dataset.discountType === type;
                btn.classList.toggle('border-2', active);
                btn.classList.toggle('border-primary-600', active);
                btn.classList.toggle('bg-primary-50', active);
                btn.classList.toggle('dark:bg-primary-500/10', active);
                btn.classList.toggle('text-primary-600', active);
                btn.classList.toggle('dark:text-primary-400', active);
                btn.classList.toggle('border-neutral-200', !active);
                btn.classList.toggle('dark:border-neutral-700', !active);
                btn.classList.toggle('text-neutral-700', !active);
                btn.classList.toggle('dark:text-neutral-300', !active);
            });
            discountValueSection.classList.toggle('hidden', type === 'courtesy');
            discountValuePrefix.textContent = type === 'percent' ? '%' : '$';
        }

        function openDiscountModal(target) {
            discountTarget = target;
            const current = target === 'sale' ? saleDiscount : target.discount;

            document.getElementById('discount-modal-target').textContent = target === 'sale'
                ? 'A toda la venta'
                : target.name + ' x' + target.qty + ' · ' + formatMoney(target.lineTotal);

            setDiscountType(current ? current.type : 'percent');
            discountValueInput.value = current && current.value ? current.value : '';
            discountReasonInput.value = current ? current.reason : '';
            discountRemoveBtn.classList.toggle('hidden', !current);
            document.getElementById('discount-apply-btn').textContent =
                target === 'sale' && !isPaymentOpen() ? 'Aplicar y cobrar' : 'Aplicar';

            discountModal.classList.remove('hidden');
            discountModal.classList.add('flex');
        }

        function closeDiscountModal() {
            discountModal.classList.add('hidden');
            discountModal.classList.remove('flex');
            discountTarget = null;
        }

        function isPaymentOpen() {
            return !paymentModal.classList.contains('hidden');
        }

        function setDiscount(discount) {
            const target = discountTarget;
            if (target === 'sale') saleDiscount = discount;
            else if (target) target.discount = discount;
            closeDiscountModal();
            renderCart();

            // El descuento a toda la venta casi siempre es lo último antes de
            // cobrar: se va directo al cobro (o, si ya se estaba cobrando, se
            // recalcula ahí mismo) para no tener que volver a darle "Cobrar".
            if (isPaymentOpen()) {
                loadPaymentPreview(rewardAnswer === true);
            } else if (target === 'sale' && discount) {
                openPaymentModal();
            }
        }

        document.querySelectorAll('.discount-type-btn').forEach(btn => {
            btn.addEventListener('click', () => setDiscountType(btn.dataset.discountType));
        });

        document.querySelectorAll('.discount-reason-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                discountReasonInput.value = chip.textContent.trim();
            });
        });

        document.getElementById('discount-apply-btn').addEventListener('click', () => {
            const reason = discountReasonInput.value.trim();
            const value = parseFloat(discountValueInput.value);

            if (discountType !== 'courtesy' && !(value > 0)) {
                Toast.show('Escribe cuánto descontar.', 'error');
                return;
            }
            if (discountType === 'percent' && value > 100) {
                Toast.show('El porcentaje no puede pasar de 100%.', 'error');
                return;
            }
            if (reason.length < 3) {
                Toast.show('Escribe o elige el motivo del descuento.', 'error');
                return;
            }

            setDiscount({ type: discountType, value: discountType === 'courtesy' ? null : value, reason });
        });

        discountRemoveBtn.addEventListener('click', () => setDiscount(null));
        document.getElementById('discount-modal-close').addEventListener('click', closeDiscountModal);
        document.getElementById('sale-discount-btn').addEventListener('click', () => openDiscountModal('sale'));
        document.getElementById('sale-discount-edit').addEventListener('click', () => openDiscountModal('sale'));
        document.getElementById('sale-discount-remove').addEventListener('click', () => {
            saleDiscount = null;
            renderCart();
        });

        // --- POPUP DE MODIFICADORES ---
        const modal = document.getElementById('modifier-modal');
        const modalLoading = document.getElementById('modal-loading');
        const modalBody = document.getElementById('modal-body');
        const modalProductName = document.getElementById('modal-product-name');
        const modalProductPrice = document.getElementById('modal-product-price');
        const modalVariantsSection = document.getElementById('modal-variants-section');
        const modalVariants = document.getElementById('modal-variants');
        const modalGroups = document.getElementById('modal-groups');
        const modalNotes = document.getElementById('modal-notes');
        const modalAddBtn = document.getElementById('modal-add-btn');
        const modalAddTotal = document.getElementById('modal-add-total');
        const modalClose = document.getElementById('modal-close');

        let currentProduct = null;
        let selectedVariant = null;
        let selectedModifiers = {};
        let editingCartId = null; // si se está editando un renglón del carrito

        // Mínimo y máximo por grupo. Obligatorio = al menos 1 (o su mínimo):
        // la Presentación (Domo, Charola...) hay que elegirla sí o sí.
        function groupMin(group) {
            return group.is_required ? Math.max(1, group.min_select || 0) : (group.min_select || 0);
        }

        function groupMax(group) {
            return group.max_select > 0 ? group.max_select : Infinity;
        }

        function sameConfig(a, b) {
            const mods = x => [...x].sort((m, n) => m - n).join(',');
            return a.productId === b.productId && a.variantId === b.variantId
                && mods(a.modifierIds) === mods(b.modifierIds) && (a.notes || '') === (b.notes || '');
        }

        /**
         * Agrega al carrito. Si ya hay un renglón IGUAL (mismo producto,
         * opciones y notas, sin descuento), suma 1 en vez de duplicarlo:
         * "un toque agrega, otro suma".
         */
        function addToCart(line) {
            const existing = cart.find(item => item.cartId !== autoGiftCartId && !item.discount && sameConfig(item, line));
            if (existing) {
                existing.qty += 1;
                existing.lineTotal = existing.unitPrice * existing.qty;
            } else {
                cart.push({ cartId: cartItemIdCounter++, qty: 1, ...line, lineTotal: line.unitPrice });
            }
            renderCart();
        }

        function openModal(productId, editItem = null) {
            fetch('/venta/productos/' + productId)
                .then(res => res.json())
                .then(data => {
                    const hasVariants = data.variants.length > 0;
                    const hasModifierGroups = data.modifier_groups.length > 0;

                    // Producto sin opciones: un toque lo agrega directo (o suma).
                    if (!editItem && !hasVariants && !hasModifierGroups) {
                        addToCart({
                            productId: data.id,
                            name: data.name,
                            variantId: null,
                            variantName: null,
                            modifierIds: [],
                            modifierNames: [],
                            notes: '',
                            unitPrice: data.base_price,
                        });
                        return;
                    }

                    currentProduct = data;
                    editingCartId = editItem ? editItem.cartId : null;
                    selectedVariant = editItem ? editItem.variantId : null;
                    selectedModifiers = {};
                    if (editItem) {
                        data.modifier_groups.forEach(group => {
                            selectedModifiers[group.id] = group.modifiers
                                .filter(mod => editItem.modifierIds.includes(mod.id))
                                .map(mod => mod.id);
                        });
                    }
                    modalNotes.value = editItem ? (editItem.notes || '') : '';
                    modalAddBtn.querySelector('span').textContent = editItem ? 'Guardar cambios' : 'Agregar al carrito';

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modalLoading.classList.add('hidden');
                    modalBody.classList.remove('hidden');
                    renderModal();
                });
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            editingCartId = null;
        }

        modalClose.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        let missingGroupId = null; // grupo obligatorio que falta (se resalta)

        function renderModal() {
            modalProductName.textContent = currentProduct.name;
            modalProductPrice.textContent = formatMoney(currentProduct.base_price);

            if (currentProduct.variants.length > 0) {
                modalVariantsSection.classList.remove('hidden');

                // La primera variante por default solo la primera vez (si no,
                // cada clic regresaba a la primera opción).
                if (selectedVariant === null) {
                    selectedVariant = currentProduct.variants[0].id;
                }

                modalVariants.innerHTML = currentProduct.variants.map(v => `
                    <button type="button" data-variant-id="${v.id}"
                        class="variant-btn px-4 py-2 rounded-full text-sm border ${v.id === selectedVariant ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'}">
                        ${escapeHtml(v.name)}${v.price_delta > 0 ? ' +' + formatMoney(v.price_delta) : ''}
                    </button>
                `).join('');

                modalVariants.querySelectorAll('.variant-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        selectedVariant = Number(btn.dataset.variantId);
                        renderModal();
                    });
                });
            } else {
                modalVariantsSection.classList.add('hidden');
            }

            // Los grupos ya vienen en el orden del negocio: obligatorios
            // (Presentación) primero, luego el orden del admin.
            modalGroups.innerHTML = currentProduct.modifier_groups.map(group => {
                if (!selectedModifiers[group.id]) selectedModifiers[group.id] = [];
                const isSingle = group.max_select === 1;
                const min = groupMin(group);
                const max = groupMax(group);
                const label = min > 0
                    ? (isSingle ? 'obligatorio · elige 1' : `obligatorio · elige al menos ${min}`)
                    : (isSingle ? 'opcional · elige 1' : (max === Infinity ? 'opcional · varios' : `opcional · hasta ${max}`));
                const missing = group.id === missingGroupId;

                const optionsHtml = group.modifiers.map(mod => {
                    const isSelected = selectedModifiers[group.id].includes(mod.id);
                    const priceLabel = mod.price_delta > 0 ? `+${formatMoney(mod.price_delta)}` : '';

                    return `
                        <button type="button" data-group-id="${group.id}" data-mod-id="${mod.id}" data-single="${isSingle}"
                            class="mod-btn px-4 py-2 rounded-full text-sm border ${isSelected ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'}">
                            ${escapeHtml(mod.name)} ${priceLabel}
                        </button>
                    `;
                }).join('');

                return `
                    <div class="rounded-xl -mx-2 px-2 py-2 ring-2 ${missing ? 'ring-red-400' : 'ring-transparent'}">
                        <p class="text-sm font-medium mb-2 text-neutral-900 dark:text-neutral-100">${escapeHtml(group.name)} <span class="${min > 0 ? 'text-red-500 dark:text-red-400' : 'text-neutral-400 dark:text-neutral-500'} font-normal">· ${label}</span></p>
                        <div class="flex gap-2 flex-wrap">${optionsHtml}</div>
                    </div>
                `;
            }).join('');

            modalGroups.querySelectorAll('.mod-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const groupId = Number(btn.dataset.groupId);
                    const modId = Number(btn.dataset.modId);
                    const group = currentProduct.modifier_groups.find(g => g.id === groupId);
                    const current = selectedModifiers[groupId];

                    if (btn.dataset.single === 'true') {
                        selectedModifiers[groupId] = current.includes(modId) && !group.is_required ? [] : [modId];
                    } else if (current.includes(modId)) {
                        selectedModifiers[groupId] = current.filter(id => id !== modId);
                    } else if (current.length >= groupMax(group)) {
                        Toast.show(`Máximo ${groupMax(group)} en ${group.name}.`, 'error');
                        return;
                    } else {
                        selectedModifiers[groupId] = [...current, modId];
                    }

                    if (missingGroupId === groupId && selectedModifiers[groupId].length >= groupMin(group)) missingGroupId = null;
                    renderModal();
                });
            });

            updateModalTotal();
        }

        function calculateModalTotal() {
            let total = currentProduct.base_price;

            if (selectedVariant) {
                const variant = currentProduct.variants.find(v => v.id === selectedVariant);
                if (variant) total += variant.price_delta;
            }

            currentProduct.modifier_groups.forEach(group => {
                (selectedModifiers[group.id] || []).forEach(modId => {
                    const mod = group.modifiers.find(m => m.id === modId);
                    if (mod) total += mod.price_delta;
                });
            });

            return total;
        }

        function updateModalTotal() {
            modalAddTotal.textContent = formatMoney(calculateModalTotal());
        }

        modalAddBtn.addEventListener('click', () => {
            // Obligatorios primero: no se agrega sin la Presentación.
            const missing = currentProduct.modifier_groups.find(group => (selectedModifiers[group.id] || []).length < groupMin(group));
            if (missing) {
                missingGroupId = missing.id;
                renderModal();
                Toast.show(`Elige ${missing.name} antes de continuar.`, 'error');
                return;
            }

            const variant = selectedVariant ? currentProduct.variants.find(v => v.id === selectedVariant) : null;

            // Ids y nombres en el orden del negocio (el mismo de la comanda).
            const modifierIds = [];
            const modifierNames = [];
            currentProduct.modifier_groups.forEach(group => {
                group.modifiers.forEach(mod => {
                    if ((selectedModifiers[group.id] || []).includes(mod.id)) {
                        modifierIds.push(mod.id);
                        modifierNames.push(mod.name);
                    }
                });
            });

            const line = {
                productId: currentProduct.id,
                name: currentProduct.name,
                variantId: variant ? variant.id : null,
                variantName: variant ? variant.name : null,
                modifierIds,
                modifierNames,
                notes: modalNotes.value.trim(),
                unitPrice: calculateModalTotal(),
            };

            if (editingCartId !== null) {
                // Editar un renglón: se conservan su cantidad y su descuento.
                const item = cart.find(i => i.cartId === editingCartId);
                if (item) {
                    Object.assign(item, line);
                    item.lineTotal = item.unitPrice * item.qty;
                }
                renderCart();
            } else {
                addToCart(line);
            }

            missingGroupId = null;
            closeModal();
        });

        productCards.forEach(card => {
            card.addEventListener('click', () => {
                openModal(card.dataset.id);
            });
        });

        // --- POPUP DE COBRO (con promociones) ---
        const paymentModal = document.getElementById('payment-modal');
        const paymentModalClose = document.getElementById('payment-modal-close');
        const paymentTotalEl = document.getElementById('payment-total');
        const amountReceivedInput = document.getElementById('amount-received');
        const changeAmountEl = document.getElementById('change-amount');

        let currentCartTotal = 0;
        let previewReady = false; // ya se calculó el total de ESTA orden
        let savingSale = false;
        let payMode = 'buttons'; // 'buttons' | 'other' (otro monto)
        // Si la orden regresó de "Deshacer" para corregirla: la venta original.
        let correctsSaleId = null;
        let rewardAnswer = null; // null = sin responder, true/false = ya contestó

        const rewardPrompt = document.getElementById('reward-prompt');
        const rewardPromptText = document.getElementById('reward-prompt-text');
        const rewardPromptExpires = document.getElementById('reward-prompt-expires');
        const rewardAnswerYesBtn = document.getElementById('reward-answer-yes');
        const rewardAnswerNoBtn = document.getElementById('reward-answer-no');

        function buildPreviewPayload(redeemReward) {
            return {
                items: cart.map(item => ({
                    product_id: item.productId,
                    variant_id: item.variantId,
                    modifier_ids: item.modifierIds,
                    qty: item.qty,
                    discount: item.discount || null,
                    notes: item.notes || null,
                })),
                sale_discount: saleDiscount,
                customer_id: selectedCustomer ? selectedCustomer.id : null,
                redeem_reward: redeemReward,
            };
        }

        function updateRewardAnswerButtons() {
            [[rewardAnswerYesBtn, true], [rewardAnswerNoBtn, false]].forEach(([btn, value]) => {
                const active = rewardAnswer === value;
                btn.classList.toggle('border-2', active);
                btn.classList.toggle('border-primary-600', active);
                btn.classList.toggle('bg-primary-50', active);
                btn.classList.toggle('dark:bg-primary-500/10', active);
                btn.classList.toggle('text-primary-600', active);
                btn.classList.toggle('dark:text-primary-400', active);
                btn.classList.toggle('border', !active);
                btn.classList.toggle('border-neutral-200', !active);
                btn.classList.toggle('dark:border-neutral-700', !active);
                btn.classList.toggle('text-neutral-700', !active);
                btn.classList.toggle('dark:text-neutral-300', !active);
            });

            renderPayButtons();
        }

        function loadPaymentPreview(redeemReward) {
            // Se limpia ANTES de armar la petición para no re-mandar en el
            // payload un premio que ya no aplica (ej. si el cajero cambió
            // de "Sí" a "No" el canje).
            if (autoGiftCartId !== null) {
                cart = cart.filter(item => item.cartId !== autoGiftCartId);
                autoGiftCartId = null;
                renderCart();
            }

            fetch('{{ route("venta.promotions.preview") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(buildPreviewPayload(redeemReward)),
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                // El servidor rechazó el carrito (ej. un producto o extra que
                // se desactivó mientras estaba en el carrito): se avisa y se
                // cierra el cobro en vez de mostrar un total vacío.
                if (!ok) {
                    Toast.show(data.message || 'No se pudo calcular el total. Revisa el carrito.', 'error');
                    paymentModal.classList.add('hidden');
                    paymentModal.classList.remove('flex');
                    return;
                }

                currentCartTotal = data.total;

                document.getElementById('payment-subtotal').textContent = formatMoney(data.subtotal);

                // Una línea por cada descuento (promoción, fidelidad, o el que
                // puso el cajero con su motivo) - lo mismo que queda guardado.
                document.getElementById('payment-discount-lines').innerHTML = (data.discount_breakdown || []).map(line => `
                    <div class="flex justify-between gap-3 text-sm text-green-600 dark:text-green-400">
                        <span class="min-w-0">${escapeHtml((line.product ? line.product + ': ' : '') + line.label)}</span>
                        <span class="whitespace-nowrap">-${formatMoney(line.amount)}</span>
                    </div>
                `).join('');

                // Si el premio es un producto gratis y el cajero no lo había
                // puesto en el carrito, el servidor ya lo agregó solo (para
                // que de verdad se descuente y se imprima en el ticket) -
                // aquí solo se refleja esa misma pieza en el carrito visible.
                if (redeemReward && data.auto_added_gift) {
                    autoGiftCartId = cartItemIdCounter++;
                    cart.push({
                        cartId: autoGiftCartId,
                        productId: data.auto_added_gift.product_id,
                        name: data.auto_added_gift.name,
                        variantId: null,
                        variantName: null,
                        modifierIds: [],
                        modifierNames: ['🎁 Premio de fidelidad, gratis'],
                        notes: '',
                        qty: 1,
                        unitPrice: 0,
                        lineTotal: 0,
                    });
                    renderCart();
                }

                const giftRow = document.getElementById('payment-gift-row');
                if (redeemReward && data.loyalty && data.loyalty.type === 'gift') {
                    if (data.loyalty.free_product_in_cart) {
                        document.getElementById('payment-gift-text').textContent =
                            '🎁 ' + (data.loyalty.description || 'Producto gratis') ;
                    } else {
                        document.getElementById('payment-gift-text').textContent =
                            (data.loyalty.description || 'Producto gratis') +
                            (data.loyalty.free_product_name ? ': agrega "' + data.loyalty.free_product_name + '" al carrito para descontarlo' : '');
                    }
                    giftRow.classList.remove('hidden');
                } else {
                    giftRow.classList.add('hidden');
                }

                // Pregunta de canje: aparece si hay un premio disponible
                // (nuevo de esta visita o uno pendiente de antes) y el
                // cajero todavía no ha contestado en este cobro.
                if (data.loyalty && data.loyalty.type && rewardAnswer === null) {
                    const isPending = data.loyalty.source === 'pending';
                    rewardPromptText.textContent = isPending
                        ? 'Este cliente tiene un premio pendiente de antes: ' + (data.loyalty.description || 'premio de fidelidad') + '.'
                        : 'Este cliente ganó un premio en esta visita: ' + (data.loyalty.description || 'premio de fidelidad') + '.';

                    if (isPending && data.loyalty.expires_at) {
                        const expires = new Date(data.loyalty.expires_at);
                        rewardPromptExpires.textContent = 'Vence el ' + expires.toLocaleDateString('es-MX', { day: 'numeric', month: 'long' }) + '.';
                        rewardPromptExpires.classList.remove('hidden');
                    } else {
                        rewardPromptExpires.classList.add('hidden');
                    }

                    rewardPrompt.classList.remove('hidden');
                } else if (! (data.loyalty && data.loyalty.type)) {
                    rewardPrompt.classList.add('hidden');
                }

                updateRewardAnswerButtons();

                paymentTotalEl.textContent = formatMoney(currentCartTotal);
                amountReceivedInput.value = '';
                previewReady = true;
                renderPayButtons();
            });
        }

        rewardAnswerYesBtn.addEventListener('click', () => {
            rewardAnswer = true;
            loadPaymentPreview(true);
        });

        rewardAnswerNoBtn.addEventListener('click', () => {
            rewardAnswer = false;
            loadPaymentPreview(false);
        });

        function openPaymentModal() {
            if (cart.length === 0) return;

            rewardAnswer = null;
            rewardPrompt.classList.add('hidden');
            previewReady = false;
            payMode = 'buttons';
            renderPayButtons();
            loadPaymentPreview(false);

            paymentModal.classList.remove('hidden');
            paymentModal.classList.add('flex');
        }

        document.getElementById('cart-checkout').addEventListener('click', openPaymentModal);
        document.getElementById('payment-sale-discount-btn').addEventListener('click', () => openDiscountModal('sale'));

        paymentModalClose.addEventListener('click', () => {
            paymentModal.classList.add('hidden');
            paymentModal.classList.remove('flex');
        });

        // --- COBRAR CON UN TOQUE ---
        const BILLS = [20, 50, 100, 200, 500, 1000];

        /** Los 3 billetes siguientes al total (con los que suele pagar el cliente). */
        function billOptions(total) {
            return BILLS.filter(bill => bill > total).slice(0, 3);
        }

        function canPay() {
            const waitingReward = !rewardPrompt.classList.contains('hidden') && rewardAnswer === null;
            return previewReady && !savingSale && !waitingReward && cart.length > 0;
        }

        function renderPayButtons() {
            const enabled = canPay();
            const waitingReward = !rewardPrompt.classList.contains('hidden') && rewardAnswer === null;

            document.getElementById('pay-wait').classList.toggle('hidden', !waitingReward);
            document.getElementById('pay-saving').classList.toggle('hidden', !savingSale);
            document.getElementById('pay-buttons').classList.toggle('hidden', payMode !== 'buttons');
            document.getElementById('pay-other-section').classList.toggle('hidden', payMode !== 'other');
            document.getElementById('pay-exact-amount').textContent = previewReady ? formatMoney(currentCartTotal) : '…';

            document.getElementById('pay-bills').innerHTML = previewReady
                ? billOptions(currentCartTotal).map(bill => `
                    <button type="button" class="pay-btn pay-bill min-h-12 rounded-xl border border-green-600/40 bg-green-50 dark:bg-green-500/10 text-green-800 dark:text-green-300 text-base font-semibold" data-bill="${bill}">$${bill}</button>
                `).join('')
                : '';
            document.querySelectorAll('.pay-bill').forEach(btn => {
                btn.addEventListener('click', () => confirmSale('efectivo', Number(btn.dataset.bill)));
            });

            document.querySelectorAll('.pay-btn').forEach(btn => {
                btn.disabled = !enabled;
                btn.classList.toggle('opacity-50', !enabled);
            });

            if (payMode === 'other') updateChange();
        }

        function updateChange() {
            const received = parseFloat(amountReceivedInput.value) || 0;
            const change = received - currentCartTotal;
            document.getElementById('change-label').textContent = change >= 0 ? 'Cambio' : 'Faltan';
            changeAmountEl.textContent = formatMoney(Math.abs(change));
            const okBtn = document.getElementById('pay-other-ok');
            okBtn.disabled = !canPay() || received < currentCartTotal;
            okBtn.classList.toggle('opacity-50', okBtn.disabled);
        }

        amountReceivedInput.addEventListener('input', updateChange);

        document.getElementById('pay-exact').addEventListener('click', () => confirmSale('efectivo', currentCartTotal));
        document.getElementById('pay-card').addEventListener('click', () => confirmSale('tarjeta', null));
        document.getElementById('pay-transfer').addEventListener('click', () => confirmSale('transferencia', null));
        document.getElementById('pay-other').addEventListener('click', () => {
            payMode = 'other';
            renderPayButtons();
            amountReceivedInput.focus();
        });
        document.getElementById('pay-other-back').addEventListener('click', () => {
            payMode = 'buttons';
            renderPayButtons();
        });
        document.getElementById('pay-other-ok').addEventListener('click', () => {
            confirmSale('efectivo', parseFloat(amountReceivedInput.value) || currentCartTotal);
        });

        function confirmSale(method, received) {
            if (!canPay()) return;
            savingSale = true;
            renderPayButtons();

            const payload = {
                items: cart.map(item => ({
                    product_id: item.productId,
                    variant_id: item.variantId,
                    modifier_ids: item.modifierIds,
                    qty: item.qty,
                    discount: item.discount || null,
                    notes: item.notes || null,
                })),
                sale_discount: saleDiscount,
                payment_method: method,
                customer_id: selectedCustomer ? selectedCustomer.id : null,
                redeem_reward: rewardAnswer === true,
                corrects_sale_id: correctsSaleId,
                amount_received: method === 'efectivo' ? received : null,
            };

            fetch('{{ route("venta.cobrar") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            })
            .then(res => res.json().then(data => ({ status: res.status, data })))
            .then(({ status, data }) => {
                savingSale = false;

                if (status === 200 && data.success) {
                    const change = method === 'efectivo' && received ? Math.max(0, received - data.total) : null;
                    printBluetooth(data.sale_id, data.folio, method === 'efectivo' ? received : null);

                    cart = [];
                    saleDiscount = null;
                    correctsSaleId = null;
                    renderCart();
                    paymentModal.classList.add('hidden');
                    paymentModal.classList.remove('flex');
                    showLastSale({ saleId: data.sale_id, folio: data.folio, total: data.total, change });

                    if (selectedCustomer) {
                        fetch('{{ route("venta.customers.clear") }}', {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        selectedCustomer = null;
                        updateCustomerBadge();
                    }
                } else {
                    renderPayButtons();
                    Toast.show('No se guardó la venta: ' + (data.message || 'intenta de nuevo'), 'error');
                }
            })
            .catch(() => {
                savingSale = false;
                renderPayButtons();
                Toast.show('No se guardó la venta por la conexión. Intenta de nuevo.', 'error');
            });
        }

        // --- VENTA REGISTRADA + DESHACER (como OaxacaWare) ---
        // Arriba sale "Venta #0012 · Cambio $55 · Deshacer". Deshacer regresa
        // la orden al carrito para corregirla y volver a cobrarla. El aviso
        // se quita a los 15 s, pero "↶ Deshacer última venta" queda a la mano
        // mientras duren los minutos permitidos al cajero.
        const UNDO_MINUTES = @json(\App\Services\SaleRevertService::UNDO_MINUTES);
        const PUEDE_AUTORIZAR = @json(auth()->user()->puedeAutorizar());
        const lastSaleEl = document.getElementById('last-sale');
        const undoLink = document.getElementById('undo-last-link');
        let lastSale = null;
        let lastSaleTimer = null;
        let undoLinkTimer = null;

        function showLastSale(sale) {
            lastSale = { ...sale, at: Date.now() };
            document.getElementById('last-sale-folio').textContent = '#' + sale.folio;
            document.getElementById('last-sale-detail').textContent = sale.change !== null && sale.change > 0
                ? 'Cambio ' + formatMoney(sale.change)
                : 'Total ' + formatMoney(sale.total);
            document.getElementById('last-sale-reasons').classList.add('hidden');
            document.getElementById('last-sale-main').classList.remove('hidden');
            lastSaleEl.classList.remove('hidden');
            lastSaleEl.classList.add('flex');

            clearTimeout(lastSaleTimer);
            lastSaleTimer = setTimeout(hideLastSale, 15000);

            undoLink.classList.remove('hidden');
            clearTimeout(undoLinkTimer);
            undoLinkTimer = setTimeout(() => undoLink.classList.add('hidden'), UNDO_MINUTES * 60 * 1000);
        }

        function hideLastSale() {
            lastSaleEl.classList.add('hidden');
            lastSaleEl.classList.remove('flex');
        }

        function askUndoReason() {
            clearTimeout(lastSaleTimer);
            lastSaleEl.classList.remove('hidden');
            lastSaleEl.classList.add('flex');
            document.getElementById('last-sale-main').classList.add('hidden');
            document.getElementById('last-sale-reasons').classList.remove('hidden');
        }

        document.getElementById('last-sale-undo').addEventListener('click', askUndoReason);
        undoLink.addEventListener('click', askUndoReason);
        document.getElementById('last-sale-close').addEventListener('click', hideLastSale);
        document.getElementById('last-sale-cancel').addEventListener('click', hideLastSale);
        document.querySelectorAll('.last-sale-reason').forEach(btn => {
            btn.addEventListener('click', () => lastSale && undoSale(lastSale.saleId, btn.dataset.reason));
        });

        function undoSale(saleId, reason) {
            fetch(`/venta/${saleId}/deshacer`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
                body: JSON.stringify({ reason }),
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                hideLastSale();
                if (!ok) {
                    Toast.show(data.message || 'No se pudo deshacer la venta.', 'error');
                    return;
                }
                undoLink.classList.add('hidden');
                lastSale = null;
                restoreOrder(data.order);
                Toast.show('Venta deshecha. La orden regresó para corregirla.');
            })
            .catch(() => Toast.show('No se pudo deshacer la venta por la conexión.', 'error'));
        }

        /** Pone en el carrito una orden deshecha (de aquí o de Ventas del turno). */
        function restoreOrder(order) {
            if (cart.length && !confirm('El carrito tiene productos. ¿Reemplazarlos por la orden #' + order.folio + '?')) return;

            cart = order.items.map(item => ({
                cartId: cartItemIdCounter++,
                ...item,
                lineTotal: item.unitPrice * item.qty,
            }));
            autoGiftCartId = null;
            saleDiscount = order.sale_discount || null;
            correctsSaleId = order.sale_id;

            if (order.customer) {
                selectedCustomer = { id: order.customer.id, name: order.customer.name };
                fetch(`/venta/cliente/seleccionar/${order.customer.id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            } else {
                selectedCustomer = null;
            }

            updateCustomerBadge();
            renderCart();
        }

        // --- CORREGIR DATOS DEL CLIENTE DESDE LA VENTA ---
        const customerModal = document.getElementById('customer-modal');
        const customerError = document.getElementById('customer-modal-error');

        function closeCustomerModal() {
            customerModal.classList.add('hidden');
            customerModal.classList.remove('flex');
        }

        document.getElementById('customer-edit-btn').addEventListener('click', () => {
            if (!selectedCustomer) return;
            customerError.classList.add('hidden');
            fetch(`/venta/clientes/${selectedCustomer.id}`, { headers: { Accept: 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('customer-name-input').value = data.name || '';
                    document.getElementById('customer-phone-input').value = data.phone || '';
                    document.getElementById('customer-birthdate-input').value = data.birthdate || '';
                    customerModal.classList.remove('hidden');
                    customerModal.classList.add('flex');
                });
        });

        document.getElementById('customer-modal-close').addEventListener('click', closeCustomerModal);

        document.getElementById('customer-save-btn').addEventListener('click', () => {
            fetch(`/venta/clientes/${selectedCustomer.id}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
                body: JSON.stringify({
                    name: document.getElementById('customer-name-input').value.trim(),
                    phone: document.getElementById('customer-phone-input').value.trim(),
                    birthdate: document.getElementById('customer-birthdate-input').value || null,
                }),
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    customerError.textContent = data.message || 'Revisa los datos.';
                    customerError.classList.remove('hidden');
                    return;
                }
                selectedCustomer = { id: data.id, name: data.name };
                updateCustomerBadge();
                closeCustomerModal();
                Toast.show('Datos del cliente actualizados.');
            })
            .catch(() => {
                customerError.textContent = 'No se pudo guardar por la conexión.';
                customerError.classList.remove('hidden');
            });
        });

        // Si se deshizo desde "Ventas del turno", la orden llega aquí.
        try {
            const pending = sessionStorage.getItem('papispapas_orden_a_corregir');
            if (pending) {
                sessionStorage.removeItem('papispapas_orden_a_corregir');
                restoreOrder(JSON.parse(pending));
                Toast.show('La orden regresó al carrito para corregirla.');
            }
        } catch (e) {}

        // --- IMPRESIÓN AL COBRAR ---
        // 1. Si ESTE dispositivo tiene la impresora Bluetooth conectada, el
        //    ticket sale al momento.
        // 2. Si no, NO se "reclama" la venta (antes un celular sin conexión
        //    la apartaba y la lap que sí tenía la impresora ya no la
        //    imprimía): queda pendiente para la estación (Bluetooth o compu).
        // 3. A los pocos segundos se revisa si alguien la imprimió; si nadie,
        //    se avisa con un botón para imprimirla aquí.
        const PRINT_CHECK_DELAY_MS = 8000;
        const btPending = document.getElementById('bt-pending');
        let btPendingSale = null;

        function postPrintStation(url) {
            return fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
            }).then(res => res.json());
        }

        async function printBluetooth(saleId, folio, recibido) {
            const BT = window.ImpresoraBT;

            try {
                if (BT && BT.isEnabled()) {
                    if (!BT.isConnected()) await BT.reconectarSiSePuede();

                    if (BT.isConnected()) {
                        const claim = await postPrintStation(`/impresion/${saleId}/marcar`);
                        if (!claim.claimed) return; // otra estación ya la está imprimiendo

                        try {
                            await BT.imprimirVenta(saleId, { recibido });
                            return;
                        } catch (err) {
                            await postPrintStation(`/impresion/${saleId}/liberar`).catch(() => {});
                        }
                    }
                }
            } catch (err) {
                console.error('Impresión Bluetooth:', err);
            }

            // No se pudo imprimir aquí: si este dispositivo usa la impresora
            // Bluetooth, el ticket queda en la fila y sale solo al reconectar.
            if (BT && BT.isEnabled()) BT.encolar(saleId, folio, recibido);

            setTimeout(() => warnIfNotPrinted(saleId, folio, recibido), PRINT_CHECK_DELAY_MS);
        }

        async function warnIfNotPrinted(saleId, folio, recibido) {
            try {
                const data = await fetch('/impresion/pendientes', { headers: { Accept: 'application/json' } }).then(res => res.json());
                if (!(data.sales || []).some(sale => sale.id === saleId)) return; // ya la imprimió alguien

                btPendingSale = { saleId, recibido };
                const queued = window.ImpresoraBT && ImpresoraBT.isEnabled();
                document.getElementById('bt-pending-text').textContent = queued
                    ? `🖨 El ticket #${folio} saldrá solo en cuanto se conecte la impresora.`
                    : `🖨 El ticket #${folio} no se ha impreso.`;
                btPending.classList.remove('hidden');
                btPending.classList.add('flex');
            } catch (err) {}
        }

        function hidePending() {
            btPendingSale = null;
            btPending.classList.add('hidden');
            btPending.classList.remove('flex');
        }

        document.getElementById('bt-pending-print').addEventListener('click', async () => {
            if (!btPendingSale) return;
            const { saleId, recibido } = btPendingSale;
            const BT = window.ImpresoraBT;

            try {
                const claim = await postPrintStation(`/impresion/${saleId}/marcar`);
                if (!claim.claimed) {
                    hidePending(); // la tomó otro dispositivo mientras tanto
                    return;
                }

                try {
                    if (BT && BT.isSupported()) {
                        // El toque cuenta como gesto: aquí sí se puede elegir la impresora.
                        if (!BT.isConnected()) await BT.conectar();
                        await BT.imprimirVenta(saleId, { recibido });
                    } else {
                        await PrintDocs.printSaleDocuments(saleId);
                    }
                    hidePending();
                } catch (err) {
                    await postPrintStation(`/impresion/${saleId}/liberar`).catch(() => {});
                    if (err.name !== 'NotFoundError') {
                        Toast.show('No se pudo imprimir: ' + (BT && BT.isSupported() ? 'la impresora la tiene otro dispositivo o está apagada.' : err.message), 'error');
                    }
                }
            } catch (err) {
                Toast.show('No se pudo imprimir: ' + err.message, 'error');
            }
        });
        document.getElementById('bt-pending-dismiss').addEventListener('click', hidePending);

        renderCart();
        updateCustomerBadge();
    </script>
</x-layouts.app>
