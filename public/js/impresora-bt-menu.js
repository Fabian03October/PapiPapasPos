// Sección "Impresora Bluetooth" del menú de usuario (ver impresora-bt.js).
(function () {
    const btn = document.getElementById('bt-connect-btn');
    if (!btn || !window.ImpresoraBT) return;

    const dot = document.getElementById('bt-status-dot');
    const text = document.getElementById('bt-status-text');
    const stationRow = document.getElementById('bt-station-row');
    const stationToggle = document.getElementById('bt-station-toggle');
    const BT = window.ImpresoraBT;

    function render({ connected, name, enabled }) {
        // El interruptor de estación solo tiene sentido con la impresora conectada aquí.
        stationRow.classList.toggle('hidden', !connected);
        stationRow.classList.toggle('flex', connected);
        const station = BT.isStation();
        stationToggle.classList.toggle('bg-primary-600', station);
        stationToggle.classList.toggle('bg-neutral-200', !station);
        stationToggle.classList.toggle('dark:bg-neutral-700', !station);
        stationToggle.querySelector('span').style.transform = station ? 'translateX(16px)' : 'translateX(0)';

        dot.classList.toggle('bg-green-500', connected);
        dot.classList.toggle('bg-amber-500', !connected && enabled);
        dot.classList.toggle('bg-neutral-300', !connected && !enabled);
        dot.classList.toggle('dark:bg-neutral-600', !connected && !enabled);

        if (!BT.isSupported()) {
            text.textContent = 'Este navegador no soporta Bluetooth (usa Chrome en Android).';
            btn.disabled = true;
            btn.classList.add('opacity-50');
        } else if (connected) {
            text.textContent = 'Conectada: ' + name + '. Los tickets salen solos al cobrar.';
            btn.textContent = 'Desconectar';
        } else if (enabled) {
            text.textContent = 'Desconectada. Dale "Conectar" para seguir imprimiendo.';
            btn.textContent = 'Conectar';
        } else {
            text.textContent = 'Para celular o tablet con Chrome.';
            btn.textContent = 'Conectar';
        }
    }

    btn.addEventListener('click', async () => {
        if (BT.isConnected()) {
            BT.olvidar();
            Toast.show('Impresora Bluetooth desconectada.');
            return;
        }

        try {
            const { name } = await BT.conectar();
            Toast.show('Impresora conectada: ' + name);
        } catch (e) {
            // Cerrar el selector de Chrome sin elegir también cae aquí.
            if (e.name !== 'NotFoundError') Toast.show('No se pudo conectar: ' + e.message, 'error');
        }
    });

    stationToggle.addEventListener('click', () => {
        const next = !BT.isStation();
        BT.setStation(next);
        Toast.show(next
            ? 'Este dispositivo imprimirá también las ventas de los demás.'
            : 'Este dispositivo ya solo imprime sus propias ventas.');
    });

    BT.onChange(render);
    render({ connected: BT.isConnected(), name: BT.deviceName(), enabled: BT.isEnabled() });
})();
