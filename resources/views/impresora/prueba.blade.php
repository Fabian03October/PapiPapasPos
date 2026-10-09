<x-layouts.app title="Prueba de impresora - posPapisV1" page-title="Prueba de impresora">

    <div class="flex-1 min-h-0 overflow-y-auto">
        <div class="max-w-2xl mx-auto w-full flex flex-col gap-4 pb-8">

            <div>
                <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">Prueba de impresora Bluetooth</h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Solo funciona en Chrome (Android) con la página en https. Antes de conectar, cierra RawBT y desconecta nRF Connect:
                    la impresora acepta una sola conexión.
                </p>
            </div>

            <div class="bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 p-4 flex flex-col gap-3">
                <p id="bt-test-status" class="text-sm text-neutral-700 dark:text-neutral-300">Sin conectar.</p>

                <div class="grid grid-cols-2 gap-2">
                    <button id="bt-test-connect" type="button" class="h-11 rounded-lg bg-primary-600 text-white text-sm font-medium">1. Conectar</button>
                    <button id="bt-test-print" type="button" class="h-11 rounded-lg border border-neutral-200 dark:border-neutral-700 text-sm font-medium text-neutral-700 dark:text-neutral-300">2. Imprimir prueba</button>
                </div>

                <label class="text-xs text-neutral-500 dark:text-neutral-400">
                    Característica con la que se imprime (si no sale nada, prueba otra y vuelve a darle "Imprimir prueba")
                    <select id="bt-test-characteristic" disabled class="mt-1 w-full h-10 px-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-xs text-neutral-900 dark:text-neutral-100">
                        <option>Conecta primero</option>
                    </select>
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <label class="text-xs text-neutral-500 dark:text-neutral-400">
                        Tabla de acentos (ESC t)
                        <select id="bt-test-codepage" class="mt-1 w-full h-10 px-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100">
                            <option value="16">16 (recomendada)</option>
                            <option value="19">19</option>
                            <option value="0">0</option>
                        </select>
                    </label>
                    <label class="text-xs text-neutral-500 dark:text-neutral-400">
                        Tamaño de bloque
                        <select id="bt-test-chunk" class="mt-1 w-full h-10 px-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100">
                            <option value="100">100 bytes (rápido)</option>
                            <option value="20">20 bytes (si imprime basura)</option>
                        </select>
                    </label>
                </div>

                <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                    <input id="bt-test-comanda" type="checkbox" class="w-4 h-4">
                    Al cobrar, imprimir también la comanda de cocina
                </label>

                <button id="bt-test-forget" type="button" class="self-start text-xs text-red-500 dark:text-red-400 hover:underline">Olvidar impresora en este dispositivo</button>
            </div>

            <div class="bg-white dark:bg-neutral-900 rounded-xl border border-neutral-200 dark:border-neutral-800 p-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">Log</p>
                    <button id="bt-test-copy" type="button" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">Copiar log</button>
                </div>
                <pre id="bt-test-log" class="text-xs whitespace-pre-wrap break-all text-neutral-600 dark:text-neutral-400 min-h-24"></pre>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const BT = window.ImpresoraBT;
            const logEl = document.getElementById('bt-test-log');
            const statusEl = document.getElementById('bt-test-status');
            const codepageEl = document.getElementById('bt-test-codepage');
            const chunkEl = document.getElementById('bt-test-chunk');
            const comandaEl = document.getElementById('bt-test-comanda');

            function log(message) {
                logEl.textContent += new Date().toLocaleTimeString('es-MX') + '  ' + message + '\n';
            }

            log('Navegador: ' + navigator.userAgent);
            log('Bluetooth disponible: ' + (BT && BT.isSupported() ? 'sí' : 'NO (necesita Chrome y https)'));
            log('Reconexión automática (getDevices): ' + (navigator.bluetooth && navigator.bluetooth.getDevices ? 'sí' : 'no'));

            codepageEl.value = String(BT.config.codepage);
            chunkEl.value = String(BT.config.chunkSize);
            comandaEl.checked = BT.config.printComanda;
            codepageEl.addEventListener('change', () => { BT.config.codepage = codepageEl.value; log('Tabla de acentos: ' + codepageEl.value); });
            chunkEl.addEventListener('change', () => { BT.config.chunkSize = chunkEl.value; log('Bloque: ' + chunkEl.value + ' bytes'); });
            comandaEl.addEventListener('change', () => { BT.config.printComanda = comandaEl.checked; });

            const charEl = document.getElementById('bt-test-characteristic');

            function renderCharacteristics() {
                const list = BT.caracteristicas();
                charEl.disabled = list.length === 0;
                charEl.innerHTML = list.length === 0
                    ? '<option>Conecta primero</option>'
                    : list.map(c => `<option value="${c.uuid}" ${c.selected ? 'selected' : ''}>${c.uuid} (servicio ${c.service.slice(4, 8)})</option>`).join('');
            }

            charEl.addEventListener('change', () => {
                BT.usarCaracteristica(charEl.value);
                log('Ahora se imprimirá con ' + charEl.value);
            });

            BT.onChange(({ connected, name }) => {
                statusEl.textContent = connected ? 'Conectada: ' + name : 'Sin conectar.';
                renderCharacteristics();
            });

            document.getElementById('bt-test-connect').addEventListener('click', async () => {
                try {
                    const result = await BT.conectar(log);
                    log('Listo. Conectada a "' + result.name + '".');
                } catch (e) {
                    log('ERROR al conectar: ' + e.name + ': ' + e.message);
                }
            });

            const SAMPLE = [
                { text: "Papi's Papas", align: 'center', bold: true, size: 'tall' },
                { text: 'Ticket de PRUEBA', align: 'center' },
                { text: '-'.repeat(32), align: 'left' },
                { text: 'Acentos: áéíóú ÁÉÍÓÚ ñ Ñ ü ¡! ¿?', align: 'left' },
                { text: '1x Salchipapas' + ' '.repeat(11) + '$89.00', align: 'left' },
                { text: ' + Queso extra' + ' '.repeat(12) + '$15.00', align: 'left' },
                { text: '-'.repeat(32), align: 'left' },
                { text: 'TOTAL' + ' '.repeat(20) + '$104.00', align: 'left', bold: true },
                { text: '12345678901234567890123456789012', align: 'left' },
                { text: 'Si ves los 32 numeros completos', align: 'center' },
                { text: 'en un renglon, el ancho va bien', align: 'center' },
                { text: 'GRANDE', align: 'center', bold: true, size: 'big' },
            ];

            document.getElementById('bt-test-print').addEventListener('click', async () => {
                try {
                    if (!BT.isConnected()) await BT.conectar(log);
                    log('Imprimiendo prueba (tabla ' + BT.config.codepage + ', bloque ' + BT.config.chunkSize + ')…');
                    await BT.imprimir(SAMPLE);
                    log('Enviado. Revisa el papel: si los acentos salen mal cambia la tabla; si sale basura, baja el bloque a 20.');
                } catch (e) {
                    log('ERROR al imprimir: ' + e.name + ': ' + e.message);
                }
            });

            document.getElementById('bt-test-forget').addEventListener('click', () => {
                BT.olvidar();
                log('Impresora olvidada en este dispositivo.');
            });

            document.getElementById('bt-test-copy').addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(logEl.textContent);
                    Toast.show('Log copiado.');
                } catch (e) {
                    Toast.show('No se pudo copiar; selecciónalo a mano.', 'error');
                }
            });
        })();
    </script>
</x-layouts.app>
