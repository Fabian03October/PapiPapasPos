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

    async function attach(newDevice, log) {
        device = newDevice;
        device.addEventListener('gattserverdisconnected', () => {
            characteristic = null;
            notify();
        });

        log('Conectando a "' + (device.name || 'sin nombre') + '"…');
        const server = await device.gatt.connect();
        characteristic = await pickCharacteristic(server, log);

        set(KEYS.enabled, '1');
        set(KEYS.deviceId, device.id);
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

            try {
                await attach(known, log);
            } catch (e) {
                // Chrome a veces no deja conectar a un dispositivo recordado
                // hasta "oírlo" anunciarse otra vez: se escucha unos segundos
                // y se reintenta.
                log('Esperando a que la impresora se anuncie…');
                await esperarAnuncio(known, 6000);
                await attach(known, log);
            }
            log('Reconectada sola a "' + deviceName() + '".');
            return true;
        } catch (e) {
            log('No se pudo reconectar solo: ' + e.message);
            return false;
        }
    }

    function esperarAnuncio(target, timeoutMs) {
        if (!target.watchAdvertisements) return Promise.resolve();

        return new Promise(resolve => {
            const controller = new AbortController();
            const done = () => {
                clearTimeout(timer);
                controller.abort();
                resolve();
            };
            const timer = setTimeout(done, timeoutMs);
            target.addEventListener('advertisementreceived', done, { once: true });
            target.watchAdvertisements({ signal: controller.signal }).catch(done);
        });
    }

    async function asegurarConexion() {
        if (isConnected()) return;

        // Mismo dispositivo de esta página pero se cayó la conexión: volver
        // a conectar no necesita clic.
        if (device) {
            const server = await device.gatt.connect();
            characteristic = await pickCharacteristic(server, () => {});
            notify();
            return;
        }

        if (!(await reconectarSiSePuede())) {
            throw new Error('La impresora Bluetooth no está conectada.');
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
        if (isStation() && (isConnected() || device)) {
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
    };

    // Al cargar la página, si ya se usaba la impresora, intenta reconectar sola.
    if (isEnabled()) reconectarSiSePuede();
    stationTick();
})(window);
