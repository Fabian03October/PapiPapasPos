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

            <div id="selected-customer-badge" class="hidden items-center justify-between bg-primary-50 dark:bg-primary-500/10 rounded-lg px-3 py-2 mb-3">
                <span class="text-sm text-primary-700 dark:text-primary-400 flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span id="selected-customer-name"></span>
                </span>
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

            <div id="modal-groups" class="space-y-4"></div>

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

        <p class="text-sm font-medium mb-2 text-neutral-900 dark:text-neutral-100">Método de pago</p>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <button type="button" id="payment-method-efectivo" class="payment-method-btn h-12 rounded-lg border-2 border-primary-600 bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 text-sm font-medium">
                💵 Efectivo
            </button>
            <button type="button" id="payment-method-tarjeta" class="payment-method-btn h-12 rounded-lg border border-neutral-200 dark:border-neutral-700 text-neutral-700 dark:text-neutral-300 text-sm font-medium">
                💳 Tarjeta
            </button>
        </div>

        <div id="cash-section">
            <p class="text-sm font-medium mb-2 text-neutral-900 dark:text-neutral-100">Monto recibido</p>
            <div class="relative mb-2">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 dark:text-neutral-500">$</span>
                <input type="number" id="amount-received" step="0.01" class="w-full h-11 pl-7 pr-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-lg font-semibold">
            </div>
            <div class="flex gap-2 mb-4" id="quick-amounts"></div>

            <div class="flex justify-between items-center bg-green-50 dark:bg-green-500/10 rounded-lg p-3 mb-4">
                <span class="text-sm font-medium text-green-700 dark:text-green-400">Cambio</span>
                <span id="change-amount" class="text-lg font-semibold text-green-700 dark:text-green-400">$0.00</span>
            </div>
        </div>

        <x-slot:footer>
            <button id="confirm-sale-btn" class="w-full bg-primary-600 text-white rounded-lg h-12 text-sm font-medium">
                ✓ Confirmar venta
            </button>
        </x-slot:footer>
    </x-modal>

    <!-- Aviso: la impresora Bluetooth se desconectó y quedó un ticket sin imprimir -->
    <div id="bt-pending" class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-[55] items-center gap-3 bg-amber-50 dark:bg-amber-500/15 border border-amber-200 dark:border-amber-500/30 rounded-xl px-4 py-3 shadow-lg max-w-[92vw]">
        <p class="text-sm text-amber-800 dark:text-amber-300">🖨 La impresora Bluetooth está desconectada. El ticket no salió.</p>
        <button id="bt-pending-print" type="button" class="shrink-0 h-9 px-3 rounded-lg bg-amber-600 text-white text-sm font-medium">Conectar e imprimir</button>
        <button id="bt-pending-dismiss" type="button" class="shrink-0 text-amber-700 dark:text-amber-400 text-sm">Omitir</button>
    </div>

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
                const details = detailsParts.join(' · ');
                const itemDiscount = discountAmount(item.discount, item.lineTotal);
                const isAutoGift = item.cartId === autoGiftCartId;

                return `
                    <div class="flex items-start gap-2 border-b border-neutral-200 dark:border-neutral-800 py-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-neutral-900 dark:text-neutral-100 truncate">${escapeHtml(item.name)} x${item.qty}</p>
                            ${details ? `<p class="text-xs text-neutral-500 dark:text-neutral-500 truncate">${escapeHtml(details)}</p>` : ''}
                            ${itemDiscount > 0 ? `<p class="text-xs text-green-600 dark:text-green-400 truncate">${escapeHtml(discountLabel(item.discount))}</p>` : ''}
                        </div>
                        <div class="text-right whitespace-nowrap">
                            ${itemDiscount > 0 ? `<p class="text-xs text-neutral-400 dark:text-neutral-500 line-through">${formatMoney(item.lineTotal)}</p>` : ''}
                            <p class="text-sm text-neutral-900 dark:text-neutral-100">${formatMoney(item.lineTotal - itemDiscount)}</p>
                        </div>
                        ${isAutoGift ? '' : `
                        <button data-cart-id="${item.cartId}" class="cart-discount-btn shrink-0 text-sm ${item.discount ? 'opacity-100' : 'opacity-40 hover:opacity-100'}" title="Descuento o cortesía">🏷</button>`}
                        <button data-cart-id="${item.cartId}" class="cart-remove-btn text-red-400 dark:text-red-500 shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
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

            document.querySelectorAll('.cart-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const removedId = Number(btn.dataset.cartId);
                    cart = cart.filter(item => item.cartId !== removedId);
                    if (autoGiftCartId === removedId) autoGiftCartId = null;
                    renderCart();
                });
            });
        }

        cartClearBtn.addEventListener('click', () => {
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

        function openModal(productId) {
            fetch('/venta/productos/' + productId)
                .then(res => res.json())
                .then(data => {
                    const hasVariants = data.variants.length > 0;
                    const hasModifierGroups = data.modifier_groups.length > 0;

                    if (!hasVariants && !hasModifierGroups) {
                        cart.push({
                            cartId: cartItemIdCounter++,
                            productId: data.id,
                            name: data.name,
                            variantId: null,
                            variantName: null,
                            modifierIds: [],
                            modifierNames: [],
                            notes: '',
                            qty: 1,
                            lineTotal: data.base_price,
                        });
                        renderCart();
                        return;
                    }

                    currentProduct = data;
                    selectedModifiers = {};
                    selectedVariant = null;
                    modalNotes.value = '';

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
        }

        modalClose.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        function renderModal() {
            modalProductName.textContent = currentProduct.name;
            modalProductPrice.textContent = formatMoney(currentProduct.base_price);

            if (currentProduct.variants.length > 0) {
                modalVariantsSection.classList.remove('hidden');

                // Solo se pone la variante por default la primera vez que se
                // abre el modal (selectedVariant viene en null desde
                // openModal). Si ya se puso, renderModal() se vuelve a
                // llamar cada vez que el cajero elige una - sin este check,
                // aqui mismo se pisaba la eleccion y siempre regresaba a la
                // primera opcion.
                if (selectedVariant === null) {
                    selectedVariant = currentProduct.variants[0].id;
                }

                modalVariants.innerHTML = currentProduct.variants.map(v => `
                    <button type="button" data-variant-id="${v.id}"
                        class="variant-btn px-4 py-2 rounded-full text-sm border ${v.id === selectedVariant ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'}">
                        ${v.name}${v.price_delta > 0 ? ' +' + formatMoney(v.price_delta) : ''}
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

            modalGroups.innerHTML = currentProduct.modifier_groups.map(group => {
                if (!selectedModifiers[group.id]) selectedModifiers[group.id] = [];
                const isSingle = group.max_select === 1;
                const label = group.is_required ? 'elige 1' : (isSingle ? 'opcional · elige 1' : 'opcional, varios');

                const optionsHtml = group.modifiers.map(mod => {
                    const isSelected = selectedModifiers[group.id].includes(mod.id);
                    const priceLabel = mod.price_delta > 0 ? `+${formatMoney(mod.price_delta)}` : '';

                    return `
                        <button type="button" data-group-id="${group.id}" data-mod-id="${mod.id}" data-single="${isSingle}"
                            class="mod-btn px-4 py-2 rounded-full text-sm border ${isSelected ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-neutral-800 text-neutral-700 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700'}">
                            ${mod.name} ${priceLabel}
                        </button>
                    `;
                }).join('');

                return `
                    <div>
                        <p class="text-sm font-medium mb-2 text-neutral-900 dark:text-neutral-100">${group.name} <span class="text-neutral-400 dark:text-neutral-500 font-normal">· ${label}</span></p>
                        <div class="flex gap-2 flex-wrap">${optionsHtml}</div>
                    </div>
                `;
            }).join('');

            modalGroups.querySelectorAll('.mod-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const groupId = btn.dataset.groupId;
                    const modId = Number(btn.dataset.modId);

                    if (btn.dataset.single === 'true') {
                        selectedModifiers[groupId] = [modId];
                    } else {
                        const current = selectedModifiers[groupId];
                        selectedModifiers[groupId] = current.includes(modId)
                            ? current.filter(id => id !== modId)
                            : [...current, modId];
                    }

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
            const total = calculateModalTotal();
            const variant = selectedVariant ? currentProduct.variants.find(v => v.id === selectedVariant) : null;

            const modifierNames = [];
            currentProduct.modifier_groups.forEach(group => {
                (selectedModifiers[group.id] || []).forEach(modId => {
                    const mod = group.modifiers.find(m => m.id === modId);
                    if (mod) modifierNames.push(mod.name);
                });
            });

            cart.push({
                cartId: cartItemIdCounter++,
                productId: currentProduct.id,
                name: currentProduct.name,
                variantId: variant ? variant.id : null,
                variantName: variant ? variant.name : null,
                modifierIds: Object.values(selectedModifiers).flat(),
                modifierNames: modifierNames,
                notes: modalNotes.value.trim(),
                qty: 1,
                lineTotal: total,
            });

            renderCart();
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
        const methodEfectivoBtn = document.getElementById('payment-method-efectivo');
        const methodTarjetaBtn = document.getElementById('payment-method-tarjeta');
        const cashSection = document.getElementById('cash-section');
        const amountReceivedInput = document.getElementById('amount-received');
        const quickAmountsEl = document.getElementById('quick-amounts');
        const changeAmountEl = document.getElementById('change-amount');
        const confirmSaleBtn = document.getElementById('confirm-sale-btn');

        let selectedPaymentMethod = 'efectivo';
        let currentCartTotal = 0;
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

            confirmSaleBtn.disabled = rewardPrompt.classList.contains('hidden') ? false : rewardAnswer === null;
            confirmSaleBtn.classList.toggle('opacity-50', confirmSaleBtn.disabled);
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
            .then(res => res.json())
            .then(data => {
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
                amountReceivedInput.value = currentCartTotal.toFixed(2);
                renderQuickAmounts();
                updateChange();
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
            setPaymentMethod('efectivo');
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

        function setPaymentMethod(method) {
            selectedPaymentMethod = method;

            if (method === 'efectivo') {
                methodEfectivoBtn.classList.add('border-2', 'border-primary-600', 'bg-primary-50', 'dark:bg-primary-500/10', 'text-primary-600', 'dark:text-primary-400');
                methodEfectivoBtn.classList.remove('border', 'border-neutral-200', 'dark:border-neutral-700', 'text-neutral-700', 'dark:text-neutral-300');
                methodTarjetaBtn.classList.remove('border-2', 'border-primary-600', 'bg-primary-50', 'dark:bg-primary-500/10', 'text-primary-600', 'dark:text-primary-400');
                methodTarjetaBtn.classList.add('border', 'border-neutral-200', 'dark:border-neutral-700', 'text-neutral-700', 'dark:text-neutral-300');
                cashSection.classList.remove('hidden');
            } else {
                methodTarjetaBtn.classList.add('border-2', 'border-primary-600', 'bg-primary-50', 'dark:bg-primary-500/10', 'text-primary-600', 'dark:text-primary-400');
                methodTarjetaBtn.classList.remove('border', 'border-neutral-200', 'dark:border-neutral-700', 'text-neutral-700', 'dark:text-neutral-300');
                methodEfectivoBtn.classList.remove('border-2', 'border-primary-600', 'bg-primary-50', 'dark:bg-primary-500/10', 'text-primary-600', 'dark:text-primary-400');
                methodEfectivoBtn.classList.add('border', 'border-neutral-200', 'dark:border-neutral-700', 'text-neutral-700', 'dark:text-neutral-300');
                cashSection.classList.add('hidden');
            }
        }

        methodEfectivoBtn.addEventListener('click', () => setPaymentMethod('efectivo'));
        methodTarjetaBtn.addEventListener('click', () => setPaymentMethod('tarjeta'));

        function renderQuickAmounts() {
            const amounts = new Set();
            amounts.add(Math.ceil(currentCartTotal));
            [20, 50, 100, 200, 500].forEach(denom => {
                const rounded = Math.ceil(currentCartTotal / denom) * denom;
                if (rounded > currentCartTotal) amounts.add(rounded);
            });

            const sorted = Array.from(amounts).sort((a, b) => a - b).slice(0, 4);

            quickAmountsEl.innerHTML = sorted.map(amt => `
                <button type="button" class="quick-amount-btn flex-1 h-10 rounded-lg border border-neutral-200 dark:border-neutral-700 text-sm text-neutral-700 dark:text-neutral-300" data-amount="${amt}">
                    $${amt}
                </button>
            `).join('');

            quickAmountsEl.querySelectorAll('.quick-amount-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    amountReceivedInput.value = btn.dataset.amount;
                    updateChange();
                });
            });
        }

        function updateChange() {
            const received = parseFloat(amountReceivedInput.value) || 0;
            const change = received - currentCartTotal;
            changeAmountEl.textContent = formatMoney(change >= 0 ? change : 0);
        }

        amountReceivedInput.addEventListener('input', updateChange);

        confirmSaleBtn.addEventListener('click', () => {
            if (confirmSaleBtn.disabled) return;

            const payload = {
                items: cart.map(item => ({
                    product_id: item.productId,
                    variant_id: item.variantId,
                    modifier_ids: item.modifierIds,
                    qty: item.qty,
                    discount: item.discount || null,
                })),
                sale_discount: saleDiscount,
                payment_method: selectedPaymentMethod,
                customer_id: selectedCustomer ? selectedCustomer.id : null,
                redeem_reward: rewardAnswer === true,
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
                if (status === 200 && data.success) {
                    printBluetooth(data.sale_id, selectedPaymentMethod === 'efectivo' ? parseFloat(amountReceivedInput.value) || null : null);

                    cart = [];
                    saleDiscount = null;
                    renderCart();
                    paymentModal.classList.add('hidden');
                    paymentModal.classList.remove('flex');
                    Toast.show(`Venta registrada — Folio #${data.folio} — Total ${formatMoney(data.total)}`);

                    if (selectedCustomer) {
                        fetch('{{ route("venta.customers.clear") }}', {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        selectedCustomer = null;
                        updateCustomerBadge();
                    }
                } else {
                    alert('Error al guardar la venta: ' + (data.message || 'intenta de nuevo'));
                }
            });
        });

        // --- IMPRESORA BLUETOOTH (impresora-bt.js) ---
        // Si este dispositivo imprime por Bluetooth, el ticket sale solo al
        // cobrar. Se "reclama" la venta igual que la estación de impresión
        // para que, si también hay una compu-estación prendida, no salga doble.
        const btPending = document.getElementById('bt-pending');
        let btPendingSale = null;

        async function printBluetooth(saleId, recibido) {
            if (!window.ImpresoraBT || !ImpresoraBT.isEnabled()) return;

            try {
                const claim = await fetch(`/impresion/${saleId}/marcar`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' },
                }).then(res => res.json());
                if (!claim.claimed) return;

                await ImpresoraBT.imprimirVenta(saleId, { recibido });
            } catch (err) {
                // Sin conexión: se ofrece reconectar (necesita un toque del
                // cajero, Chrome no deja hacerlo solo) e imprimir esta venta.
                btPendingSale = { saleId, recibido };
                btPending.classList.remove('hidden');
                btPending.classList.add('flex');
            }
        }

        if (btPending) {
            document.getElementById('bt-pending-print').addEventListener('click', async () => {
                if (!btPendingSale) return;
                try {
                    if (!ImpresoraBT.isConnected()) await ImpresoraBT.conectar();
                    await ImpresoraBT.imprimirVenta(btPendingSale.saleId, { recibido: btPendingSale.recibido });
                    btPendingSale = null;
                    btPending.classList.add('hidden');
                    btPending.classList.remove('flex');
                } catch (err) {
                    if (err.name !== 'NotFoundError') Toast.show('No se pudo imprimir: ' + err.message, 'error');
                }
            });
            document.getElementById('bt-pending-dismiss').addEventListener('click', () => {
                btPendingSale = null;
                btPending.classList.add('hidden');
                btPending.classList.remove('flex');
            });
        }

        renderCart();
        updateCustomerBadge();
    </script>
</x-layouts.app>
