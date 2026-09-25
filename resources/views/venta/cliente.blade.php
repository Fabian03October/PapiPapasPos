<x-layouts.app title="Cliente - posPapisV1" active="cliente" page-title="Cliente">

    <div class="flex-1 min-h-0 overflow-y-auto flex items-start justify-center">
        <div class="w-full max-w-md lg:max-w-3xl pt-10 lg:pt-16 pb-6">

            <div class="text-center mb-6">
                <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Identificar cliente</p>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Para sumar sellos o reenviar su QR</p>
            </div>

            <div class="flex flex-col lg:flex-row gap-4">

                <!-- Pestañas: fila en celular, columna en desktop -->
                <div class="grid grid-cols-3 lg:flex lg:flex-col gap-2 lg:w-52 lg:shrink-0">
                    <button type="button" data-tab="scan" class="customer-tab-btn flex flex-col lg:flex-row items-center lg:justify-start gap-1 lg:gap-3 px-2 lg:px-4 py-3 rounded-xl text-xs lg:text-sm font-medium bg-primary-600 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h4.5m-4.5 0v4.5m0-4.5L9 9M3.75 19.5h4.5m-4.5 0v-4.5m0 4.5L9 15M20.25 4.5h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 10.5h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                        Escanear
                    </button>
                    <button type="button" data-tab="search" class="customer-tab-btn flex flex-col lg:flex-row items-center lg:justify-start gap-1 lg:gap-3 px-2 lg:px-4 py-3 rounded-xl text-xs lg:text-sm font-medium bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        Buscar
                    </button>
                    <button type="button" data-tab="register" class="customer-tab-btn flex flex-col lg:flex-row items-center lg:justify-start gap-1 lg:gap-3 px-2 lg:px-4 py-3 rounded-xl text-xs lg:text-sm font-medium bg-white dark:bg-neutral-900 text-neutral-600 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                        </svg>
                        Registrar
                    </button>
                </div>

                <!-- Contenido -->
                <x-card padding="p-5 lg:p-6" class="flex-1 min-h-[320px]">

                    <!-- Tab: escanear -->
                    <div id="customer-tab-scan" class="customer-tab-content flex flex-col lg:flex-row lg:items-center gap-5">
                        <div id="qr-reader" class="rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-800 aspect-square w-full lg:max-w-xs lg:mx-0 mx-auto"></div>
                        <div class="flex-1 text-center lg:text-left">
                            <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-1">Escanea el código QR</p>
                            <p id="qr-status" class="text-sm text-neutral-400 dark:text-neutral-500">Apunta la cámara al código QR del cliente</p>
                        </div>
                    </div>

                    <!-- Tab: buscar -->
                    <div id="customer-tab-search" class="customer-tab-content hidden">
                        <div class="relative mb-3 lg:max-w-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400 dark:text-neutral-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <input type="text" id="customer-search-input" placeholder="Nombre o teléfono"
                                class="w-full h-11 pl-9 pr-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
                        </div>
                        <div id="customer-search-results" class="grid grid-cols-1 lg:grid-cols-2 gap-2 max-h-72 overflow-y-auto">
                            <p class="text-sm text-neutral-400 dark:text-neutral-600 text-center py-8 col-span-full">Escribe al menos 2 letras para buscar</p>
                        </div>
                    </div>

                    <!-- Tab: registrar -->
                    <div id="customer-tab-register" class="customer-tab-content hidden">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 lg:gap-4">
                            <div>
                                <label class="text-sm text-neutral-500 dark:text-neutral-400 block mb-1.5">Nombre</label>
                                <input type="text" id="new-customer-name" placeholder="Nombre completo"
                                    class="w-full h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
                            </div>
                            <div>
                                <label class="text-sm text-neutral-500 dark:text-neutral-400 block mb-1.5">Teléfono <span class="text-neutral-400 dark:text-neutral-500 font-normal">· opcional</span></label>
                                <input type="tel" id="new-customer-phone" placeholder="10 dígitos"
                                    class="w-full h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
                            </div>
                            <div class="lg:col-span-2">
                                <label class="text-sm text-neutral-500 dark:text-neutral-400 block mb-1.5">Correo <span class="text-neutral-400 dark:text-neutral-500 font-normal">· opcional, para mandarle su QR</span></label>
                                <input type="email" id="new-customer-email" placeholder="correo@ejemplo.com"
                                    class="w-full h-11 px-3 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 text-sm">
                            </div>
                        </div>
                        <x-button id="register-customer-btn" class="lg:w-auto lg:px-8 mt-4">
                            Registrar cliente
                        </x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    <script>
        function switchCustomerTab(tab) {
            document.querySelectorAll('.customer-tab-btn').forEach(btn => {
                const active = btn.dataset.tab === tab;
                btn.classList.toggle('bg-primary-600', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('bg-white', !active);
                btn.classList.toggle('dark:bg-neutral-900', !active);
                btn.classList.toggle('text-neutral-600', !active);
                btn.classList.toggle('dark:text-neutral-400', !active);
                btn.classList.toggle('border', !active);
                btn.classList.toggle('border-neutral-200', !active);
                btn.classList.toggle('dark:border-neutral-800', !active);
            });

            document.getElementById('customer-tab-scan').classList.toggle('hidden', tab !== 'scan');
            document.getElementById('customer-tab-search').classList.toggle('hidden', tab !== 'search');
            document.getElementById('customer-tab-register').classList.toggle('hidden', tab !== 'register');

            stopScanner();

            if (tab === 'scan') {
                startScanner();
            }
        }

        document.querySelectorAll('.customer-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => switchCustomerTab(btn.dataset.tab));
        });

        let html5QrCode = null;

        function stopScanner() {
            if (html5QrCode) {
                html5QrCode.stop().catch(() => {});
                html5QrCode = null;
            }
        }

        function startScanner() {
            const qrStatus = document.getElementById('qr-status');
            html5QrCode = new Html5Qrcode('qr-reader');

            html5QrCode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: 220 },
                (decodedText) => {
                    qrStatus.textContent = 'Buscando cliente…';
                    fetch('/venta/clientes/qr/' + encodeURIComponent(decodedText))
                        .then(res => res.json().then(data => ({ status: res.status, data })))
                        .then(({ status, data }) => {
                            if (status === 200 && data.found) {
                                window.location.href = '/venta/cliente/seleccionar/' + data.id;
                            } else {
                                qrStatus.textContent = 'No se encontró ningún cliente con ese código.';
                            }
                        });
                },
                () => {}
            ).catch(() => {
                qrStatus.innerHTML = 'No se pudo acceder a la cámara.<br>Usa "Buscar" o "Registrar" mientras tanto.';
            });
        }

        startScanner();

        // Búsqueda manual
        const customerSearchInput = document.getElementById('customer-search-input');
        const customerSearchResults = document.getElementById('customer-search-results');
        let searchDebounce = null;

        customerSearchInput.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            const q = customerSearchInput.value.trim();

            if (q.length < 2) {
                customerSearchResults.innerHTML = '<p class="text-sm text-neutral-400 dark:text-neutral-600 text-center py-8 col-span-full">Escribe al menos 2 letras para buscar</p>';
                return;
            }

            searchDebounce = setTimeout(() => {
                fetch('/venta/clientes/buscar?q=' + encodeURIComponent(q))
                    .then(res => res.json())
                    .then(customers => {
                        if (customers.length === 0) {
                            customerSearchResults.innerHTML = '<p class="text-sm text-neutral-400 dark:text-neutral-600 text-center py-8 col-span-full">Sin resultados</p>';
                            return;
                        }

                        customerSearchResults.innerHTML = customers.map(c => `
                            <a href="/venta/cliente/seleccionar/${c.id}" class="flex items-center justify-between px-3 py-2.5 rounded-lg hover:bg-neutral-50 dark:hover:bg-neutral-800 border border-neutral-200 dark:border-neutral-800">
                                <div>
                                    <p class="text-sm text-neutral-900 dark:text-neutral-100">${c.name}</p>
                                    ${c.phone ? `<p class="text-xs text-neutral-500 dark:text-neutral-500">${c.phone}</p>` : ''}
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-neutral-300 dark:text-neutral-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </a>
                        `).join('');
                    });
            }, 300);
        });

        // Registro rápido
        document.getElementById('register-customer-btn').addEventListener('click', () => {
            const name = document.getElementById('new-customer-name').value.trim();
            if (!name) return;

            const payload = {
                name,
                phone: document.getElementById('new-customer-phone').value.trim() || null,
                email: document.getElementById('new-customer-email').value.trim() || null,
            };

            fetch('{{ route("venta.customers.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(data => {
                window.location.href = '/venta/cliente/seleccionar/' + data.id;
            });
        });
    </script>
</x-layouts.app>
