<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vender - posPapisV1</title>
    @vite('resources/css/app.css')
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-zinc-950 min-h-screen">

    <div class="grid grid-cols-1 md:grid-cols-[190px_1fr_280px] gap-4 p-4 md:h-screen">

        @include('partials.sidebar', ['active' => 'vender', 'pageTitle' => 'Vender'])

        <div class="flex flex-col overflow-hidden">

            <div class="flex gap-2 mb-3 overflow-x-auto pb-1" id="category-tabs">
                <button class="category-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap bg-blue-600 text-white" data-category="all">
                    Todos
                </button>
                @foreach ($categories as $category)
                <button class="category-tab px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap bg-white dark:bg-zinc-900 text-gray-600 dark:text-zinc-400 border border-gray-200 dark:border-zinc-800" data-category="{{ $category->id }}">
                    {{ $category->name }}
                </button>
                @endforeach
            </div>

            <div class="relative mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input type="text" id="product-search" placeholder="Buscar producto" class="w-full h-10 pl-9 pr-3 rounded-lg border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-gray-900 dark:text-zinc-100 text-sm placeholder:text-gray-400 dark:placeholder:text-zinc-500">
            </div>

            <div class="overflow-y-auto flex-1">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3" id="product-grid">
                    @foreach ($products as $categoryId => $categoryProducts)
                    @foreach ($categoryProducts as $product)
                    <button type="button" class="product-card bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-xl p-3 text-center hover:border-blue-300 dark:hover:border-blue-500/50 hover:shadow-sm transition" data-category="{{ $categoryId }}" data-name="{{ strtolower($product->name) }}" data-id="{{ $product->id }}">
                        <div class="w-full aspect-square bg-gray-100 dark:bg-zinc-800 rounded-lg mb-2 flex items-center justify-center text-gray-400 dark:text-zinc-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.182 16.318A4.486 4.486 0 0012.016 15a4.486 4.486 0 00-3.198 1.318M21 12a9 9 0 11-18 0 9 9 0 0118 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z" />
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-900 dark:text-zinc-100">{{ $product->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-zinc-500 mt-0.5">${{ number_format($product->base_price, 2) }}</p>
                    </button>
                    @endforeach
                    @endforeach
                </div>

                <p id="no-products-msg" class="text-center text-sm text-gray-400 dark:text-zinc-600 mt-10 hidden">
                    No hay productos en esta categoría
                </p>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-zinc-900 rounded-xl p-4 flex flex-col">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-medium flex items-center gap-2 text-gray-900 dark:text-zinc-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.976-4.766 2.53-7.352.104-.487-.263-.898-.762-.898H5.106M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                    Carrito
                </p>
                <button id="cart-clear" type="button" class="text-xs text-red-500 dark:text-red-400 hidden">Vaciar</button>
            </div>

            <div id="selected-customer-badge" class="hidden items-center justify-between bg-blue-50 dark:bg-blue-500/10 rounded-lg px-3 py-2 mb-3">
                <span class="text-sm text-blue-700 dark:text-blue-400 flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span id="selected-customer-name"></span>
                </span>
                <a href="{{ route('venta.customers.clear') }}" class="text-blue-400 dark:text-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>

            <div class="flex-1 overflow-y-auto" id="cart-items-container">
                <div class="h-full flex items-center justify-center text-center" id="cart-empty">
                    <p class="text-sm text-gray-400 dark:text-zinc-600">Toca un producto<br>para agregarlo</p>
                </div>
                <div class="hidden flex-col gap-1" id="cart-items"></div>
            </div>

            <div class="border-t border-gray-200 dark:border-zinc-800 pt-3 mt-3">
                <div class="flex justify-between text-lg font-semibold mb-3 text-gray-900 dark:text-zinc-100">
                    <span>Total</span>
                    <span id="cart-total">$0.00</span>
                </div>
                <button id="cart-checkout" class="w-full bg-blue-600 text-white rounded-lg py-3 text-sm font-medium opacity-40 cursor-not-allowed" disabled>
                    Cobrar
                </button>
            </div>
        </div>

    </div>

    <!-- Popup de modificadores -->
    <div id="modifier-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl p-5 w-full max-w-md max-h-[85vh] overflow-y-auto">

            <div class="flex justify-between items-start mb-4">
                <div>
                    <p id="modal-product-name" class="text-lg font-semibold text-gray-900 dark:text-zinc-100"></p>
                    <p id="modal-product-price" class="text-sm text-gray-500 dark:text-zinc-400"></p>
                </div>
                <button id="modal-close" type="button" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 dark:text-zinc-500 hover:bg-gray-100 dark:hover:bg-zinc-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div id="modal-loading" class="text-center py-10 text-sm text-gray-400 dark:text-zinc-600">Cargando…</div>

            <div id="modal-body" class="hidden">
                <div id="modal-variants-section" class="mb-4 hidden">
                    <p class="text-sm font-medium mb-2 text-gray-900 dark:text-zinc-100">Tamaño <span class="text-gray-400 dark:text-zinc-500 font-normal">· elige 1</span></p>
                    <div id="modal-variants" class="flex gap-2 flex-wrap"></div>
                </div>

                <div id="modal-groups"></div>

                <div class="mb-4">
                    <label class="text-sm font-medium text-gray-900 dark:text-zinc-100 block mb-2">Notas para cocina <span class="text-gray-400 dark:text-zinc-500 font-normal">· opcional</span></label>
                    <input type="text" id="modal-notes" placeholder="Ej. sin popote" class="w-full h-10 px-3 rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 text-sm">
                </div>
            </div>

            <button id="modal-add-btn" class="w-full bg-blue-600 text-white rounded-lg py-3 text-sm font-medium flex items-center justify-between px-4 mt-2">
                <span>Agregar al carrito</span>
                <span id="modal-add-total">$0.00</span>
            </button>
        </div>
    </div>

    <!-- Popup de cobro -->
    <div id="payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-2xl p-5 w-full max-w-sm">

            <div class="flex justify-between items-start mb-4">
                <p class="text-lg font-semibold text-gray-900 dark:text-zinc-100">Cobrar</p>
                <button id="payment-modal-close" type="button" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 dark:text-zinc-500 hover:bg-gray-100 dark:hover:bg-zinc-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="bg-gray-50 dark:bg-zinc-800 rounded-lg p-3 mb-4">
                <div class="flex justify-between text-sm text-gray-500 dark:text-zinc-400 mb-1">
                    <span>Subtotal</span>
                    <span id="payment-subtotal">$0.00</span>
                </div>
                <div id="payment-discount-row" class="flex justify-between text-sm text-green-600 dark:text-green-400 mb-1 hidden">
                    <span id="payment-discount-label">Descuento</span>
                    <span id="payment-discount">-$0.00</span>
                </div>
                <div id="payment-gift-row" class="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400 mb-1 hidden bg-amber-50 dark:bg-amber-500/10 rounded p-2">
                    <span>🎁</span>
                    <span id="payment-gift-text">Regalo por fidelidad</span>
                </div>
                <div class="flex justify-between text-lg font-semibold text-gray-900 dark:text-zinc-100 border-t border-gray-200 dark:border-zinc-700 pt-2 mt-1">
                    <span>Total</span>
                    <span id="payment-total">$0.00</span>
                </div>
            </div>

            <p class="text-sm font-medium mb-2 text-gray-900 dark:text-zinc-100">Método de pago</p>
            <div class="grid grid-cols-2 gap-2 mb-4">
                <button type="button" id="payment-method-efectivo" class="payment-method-btn h-14 rounded-lg border-2 border-blue-600 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 text-sm font-medium">
                    💵 Efectivo
                </button>
                <button type="button" id="payment-method-tarjeta" class="payment-method-btn h-14 rounded-lg border border-gray-200 dark:border-zinc-700 text-gray-700 dark:text-zinc-300 text-sm font-medium">
                    💳 Tarjeta
                </button>
            </div>

            <div id="cash-section">
                <p class="text-sm font-medium mb-2 text-gray-900 dark:text-zinc-100">Monto recibido</p>
                <div class="relative mb-2">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-zinc-500">$</span>
                    <input type="number" id="amount-received" step="0.01" class="w-full h-14 pl-7 pr-3 rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 text-lg font-semibold">
                </div>
                <div class="flex gap-2 mb-4" id="quick-amounts"></div>

                <div class="flex justify-between items-center bg-green-50 dark:bg-green-500/10 rounded-lg p-3 mb-4">
                    <span class="text-sm font-medium text-green-700 dark:text-green-400">Cambio</span>
                    <span id="change-amount" class="text-lg font-semibold text-green-700 dark:text-green-400">$0.00</span>
                </div>
            </div>

            <button id="confirm-sale-btn" class="w-full bg-blue-600 text-white rounded-lg py-3 text-sm font-medium">
                ✓ Confirmar venta
            </button>
        </div>
    </div>

    <script>
        @include('partials.sidebar-scripts')

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
                    t.classList.remove('bg-blue-600', 'text-white');
                    t.classList.add('bg-white', 'dark:bg-zinc-900', 'text-gray-600', 'dark:text-zinc-400', 'border', 'border-gray-200', 'dark:border-zinc-800');
                });
                tab.classList.add('bg-blue-600', 'text-white');
                tab.classList.remove('bg-white', 'dark:bg-zinc-900', 'text-gray-600', 'dark:text-zinc-400', 'border', 'border-gray-200', 'dark:border-zinc-800');

                activeCategory = tab.dataset.category;
                applyFilters();
            });
        });

        searchInput.addEventListener('input', applyFilters);

        // --- CARRITO ---
        let cart = [];
        let cartItemIdCounter = 1;
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

        function renderCart() {
            if (cart.length === 0) {
                cartEmpty.classList.remove('hidden');
                cartEmpty.classList.add('flex');
                cartItemsEl.classList.add('hidden');
                cartItemsEl.classList.remove('flex');
                cartClearBtn.classList.add('hidden');
                cartCheckoutBtn.disabled = true;
                cartCheckoutBtn.classList.add('opacity-40', 'cursor-not-allowed');
            } else {
                cartEmpty.classList.add('hidden');
                cartEmpty.classList.remove('flex');
                cartItemsEl.classList.remove('hidden');
                cartItemsEl.classList.add('flex');
                cartClearBtn.classList.remove('hidden');
                cartCheckoutBtn.disabled = false;
                cartCheckoutBtn.classList.remove('opacity-40', 'cursor-not-allowed');
            }

            cartItemsEl.innerHTML = cart.map(item => {
                const detailsParts = [];
                if (item.variantName) detailsParts.push(item.variantName);
                if (item.modifierNames.length) detailsParts.push(item.modifierNames.join(', '));
                const details = detailsParts.join(' · ');

                return `
                    <div class="flex items-start gap-2 border-b border-gray-200 dark:border-zinc-800 py-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900 dark:text-zinc-100 truncate">${item.name} x${item.qty}</p>
                            ${details ? `<p class="text-xs text-gray-500 dark:text-zinc-500 truncate">${details}</p>` : ''}
                        </div>
                        <p class="text-sm text-gray-900 dark:text-zinc-100 whitespace-nowrap">${formatMoney(item.lineTotal)}</p>
                        <button data-cart-id="${item.cartId}" class="cart-remove-btn text-red-400 dark:text-red-500 shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                `;
            }).join('');

            const total = cart.reduce((sum, item) => sum + item.lineTotal, 0);
            cartTotalEl.textContent = formatMoney(total);

            document.querySelectorAll('.cart-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    cart = cart.filter(item => item.cartId !== Number(btn.dataset.cartId));
                    renderCart();
                });
            });
        }

        cartClearBtn.addEventListener('click', () => {
            cart = [];
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
                selectedVariant = currentProduct.variants[0].id;
                modalVariants.innerHTML = currentProduct.variants.map(v => `
                    <button type="button" data-variant-id="${v.id}"
                        class="variant-btn px-4 py-2 rounded-full text-sm border ${v.id === selectedVariant ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 border-gray-200 dark:border-zinc-700'}">
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

                    if (isSingle) {
                        return `
                            <button type="button" data-group-id="${group.id}" data-mod-id="${mod.id}" data-single="true"
                                class="mod-btn px-4 py-2 rounded-full text-sm border ${isSelected ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 border-gray-200 dark:border-zinc-700'}">
                                ${mod.name} ${priceLabel}
                            </button>
                        `;
                    }

                    return `
                        <label class="flex items-center justify-between px-3 py-2.5 border border-gray-200 dark:border-zinc-700 rounded-lg text-sm cursor-pointer">
                            <span class="flex items-center gap-2 text-gray-900 dark:text-zinc-100">
                                <input type="checkbox" data-group-id="${group.id}" data-mod-id="${mod.id}" class="mod-checkbox" ${isSelected ? 'checked' : ''}>
                                ${mod.name}
                            </span>
                            ${priceLabel ? `<span class="text-gray-500 dark:text-zinc-500">${priceLabel}</span>` : ''}
                        </label>
                    `;
                }).join('');

                return `
                    <div class="mb-4">
                        <p class="text-sm font-medium mb-2 text-gray-900 dark:text-zinc-100">${group.name} <span class="text-gray-400 dark:text-zinc-500 font-normal">· ${label}</span></p>
                        <div class="${isSingle ? 'flex gap-2 flex-wrap' : 'flex flex-col gap-2'}">${optionsHtml}</div>
                    </div>
                `;
            }).join('');

            modalGroups.querySelectorAll('.mod-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const groupId = btn.dataset.groupId;
                    const modId = Number(btn.dataset.modId);
                    selectedModifiers[groupId] = [modId];
                    renderModal();
                });
            });

            modalGroups.querySelectorAll('.mod-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    const groupId = cb.dataset.groupId;
                    const modId = Number(cb.dataset.modId);
                    if (cb.checked) {
                        selectedModifiers[groupId].push(modId);
                    } else {
                        selectedModifiers[groupId] = selectedModifiers[groupId].filter(id => id !== modId);
                    }
                    updateModalTotal();
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

                document.getElementById('cart-checkout').addEventListener('click', () => {
            if (cart.length === 0) return;

            const previewPayload = {
                items: cart.map(item => ({
                    product_id: item.productId,
                    variant_id: item.variantId,
                    modifier_ids: item.modifierIds,
                    qty: item.qty,
                })),
                customer_id: selectedCustomer ? selectedCustomer.id : null,
            };

            fetch('{{ route("venta.promotions.preview") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(previewPayload),
            })
            .then(res => res.json())
            .then(data => {
                currentCartTotal = data.total;

                document.getElementById('payment-subtotal').textContent = formatMoney(data.subtotal);

                const discountLabels = [...data.applied_promotions];
                if (data.loyalty && data.loyalty.type === 'discount') {
                    discountLabels.push(data.loyalty.description || 'fidelidad');
                }
                if (data.loyalty && data.loyalty.type === 'gift' && data.loyalty.free_product_in_cart) {
                    discountLabels.push(data.loyalty.description || 'regalo de fidelidad');
                }

                const discountRow = document.getElementById('payment-discount-row');
                if (data.discount > 0) {
                    document.getElementById('payment-discount').textContent = '-' + formatMoney(data.discount);
                    document.getElementById('payment-discount-label').textContent =
                        'Descuento' + (discountLabels.length ? ' (' + discountLabels.join(', ') + ')' : '');
                    discountRow.classList.remove('hidden');
                } else {
                    discountRow.classList.add('hidden');
                }

                const giftRow = document.getElementById('payment-gift-row');
                if (data.loyalty && data.loyalty.type === 'gift') {
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

                paymentTotalEl.textContent = formatMoney(currentCartTotal);
                setPaymentMethod('efectivo');
                amountReceivedInput.value = currentCartTotal.toFixed(2);
                renderQuickAmounts();
                updateChange();

                paymentModal.classList.remove('hidden');
                paymentModal.classList.add('flex');
            });
        });

        paymentModalClose.addEventListener('click', () => {
            paymentModal.classList.add('hidden');
            paymentModal.classList.remove('flex');
        });

        function setPaymentMethod(method) {
            selectedPaymentMethod = method;

            if (method === 'efectivo') {
                methodEfectivoBtn.classList.add('border-2', 'border-blue-600', 'bg-blue-50', 'dark:bg-blue-500/10', 'text-blue-600', 'dark:text-blue-400');
                methodEfectivoBtn.classList.remove('border', 'border-gray-200', 'dark:border-zinc-700', 'text-gray-700', 'dark:text-zinc-300');
                methodTarjetaBtn.classList.remove('border-2', 'border-blue-600', 'bg-blue-50', 'dark:bg-blue-500/10', 'text-blue-600', 'dark:text-blue-400');
                methodTarjetaBtn.classList.add('border', 'border-gray-200', 'dark:border-zinc-700', 'text-gray-700', 'dark:text-zinc-300');
                cashSection.classList.remove('hidden');
            } else {
                methodTarjetaBtn.classList.add('border-2', 'border-blue-600', 'bg-blue-50', 'dark:bg-blue-500/10', 'text-blue-600', 'dark:text-blue-400');
                methodTarjetaBtn.classList.remove('border', 'border-gray-200', 'dark:border-zinc-700', 'text-gray-700', 'dark:text-zinc-300');
                methodEfectivoBtn.classList.remove('border-2', 'border-blue-600', 'bg-blue-50', 'dark:bg-blue-500/10', 'text-blue-600', 'dark:text-blue-400');
                methodEfectivoBtn.classList.add('border', 'border-gray-200', 'dark:border-zinc-700', 'text-gray-700', 'dark:text-zinc-300');
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
                <button type="button" class="quick-amount-btn flex-1 h-10 rounded-lg border border-gray-200 dark:border-zinc-700 text-sm text-gray-700 dark:text-zinc-300" data-amount="${amt}">
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
            const payload = {
                items: cart.map(item => ({
                    product_id: item.productId,
                    variant_id: item.variantId,
                    modifier_ids: item.modifierIds,
                    qty: item.qty,
                })),
                payment_method: selectedPaymentMethod,
                customer_id: selectedCustomer ? selectedCustomer.id : null,
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
                    cart = [];
                    renderCart();
                    paymentModal.classList.add('hidden');
                    paymentModal.classList.remove('flex');
                    showSuccessToast(data.folio, data.total);

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

        function showSuccessToast(folio, total) {
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 z-[60] bg-green-600 text-white px-5 py-3 rounded-lg shadow-lg text-sm font-medium flex items-center gap-2';
            toast.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                Venta registrada — Folio #${folio} — Total ${formatMoney(total)}
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3500);
        }

        renderCart();
        updateCustomerBadge();
    </script>
</body>
</html>