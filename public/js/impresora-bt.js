/**
 * Impresión directa por Web Bluetooth (Chrome en Android, HTTPS) a una
 * térmica BLE de 58mm ("BLUETOOTH PRINTER", POS-58, 32 columnas, sin
 * cortador). Alternativa a la estación de impresión (print.js), que
 * necesita una compu con la impresora conectada.
 *
 * - conectar() SIEMPRE tiene que salir de un clic (Chrome lo exige para
 *   elegir el dispositivo). Después, imprimir no necesita clic: por eso al
 *   cobrar se imprime solo.
 * - Chrome no recuerda la conexión entre recargas de página: al volver a
 *   cargar se intenta reconectar solo (getDevices, si el Chrome lo trae);
 *   si no se puede, hay que volver a darle "Conectar".
 * - La impresora acepta UNA sola conexión: cerrar RawBT / nRF Connect.
 *
 * El servidor manda los renglones ya acomodados a 32 columnas
 * (App\Services\ThermalTicketBuilder); aquí solo se pasan a bytes ESC/POS.
 */
(function (window) {
    // Servicios vistos con nRF Connect - Chrome solo deja usar los que se
    // declaren aquí al pedir el dispositivo.
    const SERVICES = [
        0x18f0, 0xff00, 0xeee0, 0xeee2, 0xfee7, 0xff80, 0xfff0,
        'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
        '49535343-fe7d-4ae5-8fa9-9fafd205e455',
    ];
    // Características que más probablemente imprimen, en orden de preferencia.
    const PREFERRED_CHARACTERISTICS = [
        '00002af1-0000-1000-8000-00805f9b34fb', // 0x2AF1 dentro de 0x18F0
        '49535343-8841-43f4-a8d4-ecbe34729bb3',
    ];

    const KEYS = {
        enabled: 'papispapas_bt_activa',
        service: 'papispapas_bt_servicio',
        characteristic: 'papispapas_bt_caracteristica',
        deviceId: 'papispapas_bt_dispositivo',
        codepage: 'papispapas_bt_codepage',
        chunk: 'papispapas_bt_bloque',
        comanda: 'papispapas_bt_comanda',
        station: 'papispapas_bt_estacion', // fecha (ISO) desde que se activó, o nada
        events: 'papispapas_bt_eventos',
    };

    let device = null;
    let characteristic = null;
    let writable = []; // todas en las que se puede escribir (para probarlas en /prueba-impresora)
    let printing = Promise.resolve();
    const listeners = new Set();

    function get(key, fallback = null) {
        try {
            const value = localStorage.getItem(key);
            return value === null ? fallback : value;
        } catch (e) {
            return fallback;
        }
    }

    function set(key, value) {
        try {
            if (value === null) localStorage.removeItem(key);
            else localStorage.setItem(key, String(value));
        } catch (e) {}
    }

    const config = {
        get codepage() { return Number(get(KEYS.codepage, 16)); },
        set codepage(v) { set(KEYS.codepage, v); },
        get chunkSize() { return Number(get(KEYS.chunk, 100)); },
        set chunkSize(v) { set(KEYS.chunk, v); },
        get printComanda() { return get(KEYS.comanda, '1') === '1'; },
        set printComanda(v) { set(KEYS.comanda, v ? '1' : '0'); },
    };

    function isSupported() {
        return !!(navigator.bluetooth && window.isSecureContext);
    }

    // "Activa" = este dispositivo imprime por Bluetooth (se marca al conectar
    // la primera vez). Sirve para avisar si al cobrar quedó desconectada.
    function isEnabled() {
        return get(KEYS.enabled) === '1';
    }

    function isConnected() {
        return !!(characteristic && device && device.gatt.connected);
    }

    function deviceName() {
        return device ? (device.name || 'Impresora') : null;
    }

    function onChange(fn) {
        listeners.add(fn);
        return () => listeners.delete(fn);
    }

    function notify() {
        actualizarPantallaEncendida();
        listeners.forEach(fn => {
            try { fn({ connected: isConnected(), name: deviceName(), enabled: isEnabled() }); } catch (e) {}
        });
    }

    function propsOf(c) {
        return Object.entries({
            read: c.properties.read,
            write: c.properties.write,
            writeWithoutResponse: c.properties.writeWithoutResponse,
            notify: c.properties.notify,
            indicate: c.properties.indicate,
        }).filter(([, v]) => v).map(([k]) => k);
    }

    /**
     * Recorre todos los servicios/características (y los manda al log) y
     * escoge con cuál imprimir: primero la que funcionó la vez pasada,
     * luego las candidatas conocidas, luego la primera en la que se pueda
     * escribir.
     */
    async function pickCharacteristic(server, log) {
        const services = await server.getPrimaryServices();
        writable = [];

        for (const service of services) {
            log('Servicio ' + service.uuid);
            let chars = [];
            try {
                chars = await service.getCharacteristics();
            } catch (e) {
                log('   (no se pudieron leer sus características: ' + e.message + ')');
            }
            for (const c of chars) {
                const props = propsOf(c);
                log('   Característica ' + c.uuid + ' [' + props.join(', ') + ']');
                if (c.properties.write || c.properties.writeWithoutResponse) {
                    writable.push({ service: service.uuid, characteristic: c });
                }
            }
        }

        const savedService = get(KEYS.service);
        const savedChar = get(KEYS.characteristic);
        const choice =
            writable.find(w => w.service === savedService && w.characteristic.uuid === savedChar) ||
            PREFERRED_CHARACTERISTICS.map(uuid => writable.find(w => w.characteristic.uuid === uuid)).find(Boolean) ||
            writable[0];

        if (!choice) {
            throw new Error('La impresora no tiene ninguna característica en la que se pueda escribir.');
        }

        set(KEYS.service, choice.service);
        set(KEYS.characteristic, choice.characteristic.uuid);
        log('→ Se imprimirá con ' + choice.characteristic.uuid + ' (servicio ' + choice.service + ')');

        return choice.characteristic;
    }

    async function attach(newDevice, log, origen = 'con el botón') {
        device = newDevice;
        if (!device.__papisListener) {
            device.__papisListener = true;
            device.addEventListener('gattserverdisconnected', () => {
                characteristic = null;
                if (leavingPage) return; // la soltó esta misma página al cambiar de pantalla
                // Chrome en Android corta el Bluetooth cuando la pantalla se
                // apaga o se cambia de app: se anota para poder diagnosticarlo.
                evento('Se desconectó' + (document.hidden ? ' (pantalla apagada o Chrome en segundo plano)' : ''));
                notify();
                programarReconexion();
            });
        }

        log('Conectando a "' + (device.name || 'sin nombre') + '"…');
        const server = await conectarGatt(device, log);
        characteristic = await pickCharacteristic(server, log);

        set(KEYS.enabled, '1');
        set(KEYS.deviceId, device.id);
        retryCount = 0;
        evento('Conectada ' + origen);
        avisarOtrasPestanas();
        notify();

        return { name: deviceName(), characteristic: characteristic.uuid };
    }

    /** Pide elegir la impresora. Debe llamarse desde un clic. */
    async function conectar(log = () => {}) {
        if (!isSupported()) {
            throw new Error('Este navegador no soporta Bluetooth. Usa Chrome en Android con la página en https.');
        }

        const chosen = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: SERVICES,
        });

        return attach(chosen, log);
    }

    /**
     * Reconexión sin clic tras recargar la página: solo funciona si este
     * Chrome trae navigator.bluetooth.getDevices() y ya se había dado
     * permiso a la impresora. Si no, se queda desconectada y hay que darle
     * "Conectar" otra vez.
     */
    let reconnecting = null;

    function reconectarSiSePuede(log = () => {}) {
        // Si ya hay un intento en curso (el que arranca solo al cargar la
        // página), se espera ese en vez de abrir otra conexión.
        if (!reconnecting) {
            reconnecting = intentarReconectar(log).finally(() => { reconnecting = null; });
        }
        return reconnecting;
    }

    async function intentarReconectar(log) {
        if (isConnected() || !isEnabled() || !isSupported() || !navigator.bluetooth.getDevices) return false;

        try {
            const devices = await navigator.bluetooth.getDevices();
            const known = devices.find(d => d.id === get(KEYS.deviceId)) || devices[0];
            if (!known) return false;

            await attach(known, log, 'sola al abrir la pantalla');
            log('Reconectada sola a "' + deviceName() + '".');
            return true;
        } catch (e) {
            ultimoError = e;
            log('No se pudo reconectar solo: ' + e.message);
            // Solo el primer fallo de cada racha, para no llenar el historial.
            if (retryCount === 0) evento('Falló al reconectar: ' + motivo(e));
            return false;
        }
    }

    let ultimoError = null;

    /** El error de Chrome en palabras, para el historial. */
    function motivo(e) {
        const text = (e && (e.name + ': ' + e.message)) || String(e);
        if (/already in use|busy|in use/i.test(text)) return 'la impresora la tiene otro dispositivo o pestaña (' + text + ')';
        if (/out of range|not found|no longer in range|connection failed/i.test(text)) return 'no se encontró: apagada, sin batería o lejos (' + text + ')';
        return text;
    }

    /**
     * Conecta a un dispositivo ya elegido antes (sin clic). Chrome solo deja
     * reconectar a uno "recordado" después de oírlo anunciarse; si no, falla
     * con "Bluetooth Device is no longer in range" aunque esté al lado. Justo
     * después de cambiar de pantalla la impresora tarda unos segundos en
     * volver a anunciarse, así que se la escucha y se reintenta.
     *
     * (Antes los reintentos automáticos solo repetían connect() sin escuchar:
     * fallaban siempre y solo funcionaba el botón "Conectar".)
     */
    async function conectarGatt(target, log = () => {}) {
        try {
            return await target.gatt.connect();
        } catch (e) {
            log('Esperando a que la impresora se anuncie…');
            const heard = await esperarAnuncio(target, 10000);
            if (!heard) throw e;
            return await target.gatt.connect();
        }
    }

    /** true si se oyó a la impresora anunciarse antes del tiempo límite. */
    function esperarAnuncio(target, timeoutMs) {
        // Sin watchAdvertisements (Chrome sin la opción de chrome://flags)
        // no se puede escuchar: se deja reintentar una vez a ciegas.
        if (!target.watchAdvertisements) return Promise.resolve(true);

        return new Promise(resolve => {
            const controller = new AbortController();
            const finish = (heard) => {
                clearTimeout(timer);
                target.removeEventListener('advertisementreceived', onAdvertisement);
                controller.abort();
                resolve(heard);
            };
            const onAdvertisement = () => finish(true);
            const timer = setTimeout(() => finish(false), timeoutMs);
            target.addEventListener('advertisementreceived', onAdvertisement);
            target.watchAdvertisements({ signal: controller.signal }).catch(() => finish(false));
        });
    }

    let connecting = null;

    function asegurarConexion() {
        if (isConnected()) return Promise.resolve();

        // Un solo intento a la vez (lo pueden pedir al mismo tiempo la
        // reconexión automática, la estación y una venta).
        if (!connecting) {
            connecting = (async () => {
                // Mismo dispositivo de esta página pero se cayó la conexión:
                // volver a conectar no necesita clic.
                if (device) {
                    const server = await conectarGatt(device);
                    characteristic = await pickCharacteristic(server, () => {});
                    notify();
                    return;
                }

                if (!(await reconectarSiSePuede())) {
                    throw new Error('La impresora Bluetooth no está conectada.');
                }
            })().finally(() => { connecting = null; });
        }

        return connecting;
    }

    // --- Reconexión automática ---
    // Si se cae la conexión se reintenta solo, cada vez más espaciado, mientras
    // la página esté a la vista (con la pantalla apagada Chrome no deja).
    const RETRY_DELAYS_MS = [1500, 4000, 8000, 15000, 30000];
    let retryTimer = null;
    let retryCount = 0;

    function programarReconexion(delay = RETRY_DELAYS_MS[Math.min(retryCount, RETRY_DELAYS_MS.length - 1)]) {
        clearTimeout(retryTimer);
        if (!isEnabled() || isConnected() || document.hidden) return;

        retryTimer = setTimeout(async () => {
            if (!isEnabled() || isConnected() || document.hidden) return;
            try {
                await asegurarConexion();
                evento('Reconectada sola' + (retryCount > 0 ? ' (intento ' + (retryCount + 1) + ')' : ''));
                retryCount = 0;
            } catch (e) {
                retryCount++;
                if (retryCount === 3) evento('Sigue sin reconectar (lo seguirá intentando): ' + motivo(ultimoError || e));
                programarReconexion();
            }
        }, delay);
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;
        // Al volver a la pantalla (desbloquear, regresar a Chrome) se
        // reconecta de inmediato y se vuelve a pedir la pantalla encendida.
        retryCount = 0;
        programarReconexion(300);
        actualizarPantallaEncendida();
    });

    // --- Pantalla encendida ---
    // En el dispositivo que imprime para todos (estación) se pide que la
    // pantalla no se apague: si se apaga, Android corta el Bluetooth y deja
    // de imprimir. Chrome la suelta sola si se cambia de app; al volver se
    // pide otra vez.
    let wakeLock = null;
    let wakeLockPending = false;

    async function actualizarPantallaEncendida() {
        const want = isStation() && isConnected() && !document.hidden;

        if (want && !wakeLock && !wakeLockPending && navigator.wakeLock) {
            wakeLockPending = true;
            try {
                wakeLock = await navigator.wakeLock.request('screen');
                wakeLock.addEventListener('release', () => { wakeLock = null; });
            } catch (e) {
            } finally {
                wakeLockPending = false;
            }
        } else if (!want && wakeLock) {
            wakeLock.release().catch(() => {});
            wakeLock = null;
        }
    }

    // --- Historial de conexión (se ve en /prueba-impresora) ---
    function evento(message) {
        try {
            const list = JSON.parse(get(KEYS.events, '[]'));
            list.push({ t: new Date().toISOString(), m: message });
            set(KEYS.events, JSON.stringify(list.slice(-50)));
        } catch (e) {}
    }

    function eventos() {
        try {
            return JSON.parse(get(KEYS.events, '[]'));
        } catch (e) {
            return [];
        }
    }

    /** Características en las que se puede escribir (de la última conexión). */
    function caracteristicas() {
        return writable.map(w => ({
            service: w.service,
            uuid: w.characteristic.uuid,
            selected: w.characteristic === characteristic,
        }));
    }

    /** Cambia con cuál se imprime (y la recuerda para la próxima vez). */
    function usarCaracteristica(uuid) {
        const choice = writable.find(w => w.characteristic.uuid === uuid);
        if (!choice) throw new Error('Esa característica no está en la impresora conectada.');
        characteristic = choice.characteristic;
        set(KEYS.service, choice.service);
        set(KEYS.characteristic, uuid);
        notify();
    }

    function olvidar() {
        // Primero se borra "activa" para que la desconexión de abajo no
        // dispare la reconexión automática.
        set(KEYS.enabled, null);
        clearTimeout(retryTimer);
        evento('Desconectada a mano');
        try { device && device.gatt.connected && device.gatt.disconnect(); } catch (e) {}
        device = null;
        characteristic = null;
        // Al soltar la impresora (ej. para conectarla en otro dispositivo)
        // este deja de ser la estación.
        [KEYS.enabled, KEYS.service, KEYS.characteristic, KEYS.deviceId, KEYS.station].forEach(k => set(k, null));
        notify();
    }

    // --- ESC/POS ---
    const ESC = 0x1b;
    const GS = 0x1d;
    const LF = 0x0a;
    const ALIGN = { left: 0, center: 1, right: 2 };
    const SIZE = { normal: 0x00, tall: 0x01, big: 0x11 };

    function textBytes(text) {
        // Latin-1: acentos y ñ caben en un byte; lo demás sale como "?".
        const out = [];
        for (const ch of text) {
            const code = ch.codePointAt(0);
            out.push(code <= 0xff ? code : 0x3f);
        }
        return out;
    }

    /** Renglones [{text, align, bold, size}] → bytes ESC/POS. */
    function encode(lines) {
        const bytes = [ESC, 0x40, ESC, 0x74, config.codepage];

        for (const line of lines) {
            bytes.push(
                ESC, 0x61, ALIGN[line.align] ?? 0,
                ESC, 0x45, line.bold ? 1 : 0,
                GS, 0x21, SIZE[line.size] ?? 0,
                ...textBytes(line.text || ''),
                LF
            );
        }

        // Sin cortador: 4 renglones en blanco para poder arrancar el papel.
        bytes.push(ESC, 0x61, 0, ESC, 0x45, 0, GS, 0x21, 0, LF, LF, LF, LF);

        return new Uint8Array(bytes);
    }

    const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

    async function send(data) {
        const size = config.chunkSize;

        for (let i = 0; i < data.length; i += size) {
            const chunk = data.slice(i, i + size);
            if (characteristic.properties.writeWithoutResponse) {
                await characteristic.writeValueWithoutResponse(chunk);
            } else {
                await characteristic.writeValueWithResponse(chunk);
            }
            await sleep(30);
        }
    }

    /**
     * Imprime renglones ya armados. Los trabajos se forman en fila para que
     * dos impresiones seguidas (ticket + comanda) no se mezclen en el papel.
     */
    function imprimir(lines) {
        const job = printing.then(async () => {
            await asegurarConexion();
            try {
                await send(encode(lines));
            } catch (e) {
                // Se fuerza a reconectar en el siguiente intento.
                characteristic = null;
                notify();
                throw e;
            }
        });
        printing = job.catch(() => {});
        return job;
    }

    /** Ticket (y comanda, si está activado) de una venta ya guardada. */
    async function imprimirVenta(saleId, { recibido = null, comanda = config.printComanda } = {}) {
        const url = `/venta/${saleId}/impresion-bluetooth` + (recibido !== null ? `?recibido=${encodeURIComponent(recibido)}` : '');
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('No se pudo obtener el ticket (' + res.status + ')');
        const data = await res.json();

        await imprimir(data.ticket);
        if (comanda) await imprimir(data.comanda);
    }

    /**
     * Estación Bluetooth: la impresora solo acepta UNA conexión, así que un
     * dispositivo se queda conectado y además imprime lo que se venda en
     * los demás (lap, otra tablet...), revisando cada pocos segundos. Usa
     * el mismo "reclamo" que la estación de la compu (print.js), así que
     * nunca sale doble. Solo toma ventas hechas desde que se activó.
     */
    const STATION_INTERVAL_MS = 4000;

    function isStation() {
        return !!get(KEYS.station);
    }

    function setStation(enabled) {
        set(KEYS.station, enabled ? new Date().toISOString() : null);
        notify();
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function postJson(url) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        }).then(res => res.json());
    }

    async function stationTick() {
        // Si se cayó la conexión con la impresora de esta página, se
        // reconecta sola (sin clic). Si no se puede, NO se reclama nada:
        // esas ventas siguen pendientes y salen en cuanto se reconecte.
        let ready = false;
        if (isStation() && !document.hidden && (isConnected() || device)) {
            try {
                await asegurarConexion();
                ready = true;
            } catch (e) {}
        }

        if (ready) {
            try {
                const res = await fetch('/impresion/pendientes?desde=' + encodeURIComponent(get(KEYS.station)), {
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json();

                for (const sale of data.sales || []) {
                    const claim = await postJson(`/impresion/${sale.id}/marcar`);
                    if (!claim.claimed) continue;

                    try {
                        await imprimirVenta(sale.id);
                    } catch (e) {
                        // Se devuelve a pendiente para reintentarla, no se pierde.
                        await postJson(`/impresion/${sale.id}/liberar`).catch(() => {});
                        break;
                    }
                }
            } catch (e) {
                console.error('Estación Bluetooth:', e);
            }
        }

        setTimeout(stationTick, STATION_INTERVAL_MS);
    }

    window.ImpresoraBT = {
        isSupported, isEnabled, isConnected, deviceName, onChange,
        conectar, reconectarSiSePuede, olvidar, caracteristicas, usarCaracteristica,
        imprimir, imprimirVenta, encode, config, isStation, setStation,
        eventos, limpiarEventos: () => set(KEYS.events, null),
    };

    // --- Varias pestañas del POS en el mismo dispositivo ---
    // La impresora acepta una sola conexión: dos pestañas se la pelean. No se
    // cambia nada solo, pero se avisa para que cierren la que sobra.
    const tabs = 'BroadcastChannel' in window ? new BroadcastChannel('papispapas-impresora') : null;

    function avisarOtrasPestanas() {
        if (tabs) tabs.postMessage({ type: 'tengo-la-impresora' });
    }

    if (tabs) {
        tabs.onmessage = (event) => {
            const type = event.data?.type;
            if (type === 'tengo-la-impresora' && isEnabled()) {
                evento('Hay otra pestaña del POS usando la impresora: dejen abierta solo una');
                if (window.Toast) Toast.show('Hay otra pestaña del sistema abierta usando la impresora. Cierren las demás y dejen solo una.', 'error');
            } else if (type === 'quien-la-tiene' && isConnected()) {
                // Una pestaña recién abierta pregunta: se le avisa que esta ya la tiene.
                avisarOtrasPestanas();
            }
        };
        if (isEnabled()) tabs.postMessage({ type: 'quien-la-tiene' });
    }

    // --- Al cambiar de pantalla ---
    // Cada pantalla del POS es una página nueva y la conexión no pasa de una
    // a otra. Se suelta la impresora en cuanto se sale de la página para que
    // vuelva a anunciarse rápido y la pantalla siguiente la pueda agarrar
    // (si no, se quedaba "ocupada" unos segundos y la reconexión fallaba).
    let leavingPage = false;

    window.addEventListener('pagehide', () => {
        leavingPage = true;
        clearTimeout(retryTimer);
        try { device && device.gatt.connected && device.gatt.disconnect(); } catch (e) {}
    });

    // Regresar con "atrás" puede restaurar esta misma página (sin recargarla):
    // ahí hay que volver a conectar.
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        leavingPage = false;
        retryCount = 0;
        programarReconexion(300);
    });

    // Al cargar la página, si ya se usaba la impresora, intenta reconectar
    // sola; si en ese momento no se puede (apagada, lejos, ocupada), se sigue
    // intentando en vez de rendirse.
    if (isEnabled()) {
        reconectarSiSePuede().then(ok => {
            if (!ok && isEnabled()) programarReconexion();
        });
    }
    stationTick();
})(window);
