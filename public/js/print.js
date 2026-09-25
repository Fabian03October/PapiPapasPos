/**
 * Impresión silenciosa vía el navegador (Chrome/Edge con la bandera
 * --kiosk-printing en el acceso directo de la compu del mostrador).
 * Cada página de ticket/comanda se imprime sola al cargar
 * (<body onload="window.print()">) — aquí solo la cargamos en un iframe
 * oculto y la quitamos después. Requiere que la impresora térmica esté
 * configurada como predeterminada en Windows.
 */
(function (window) {
    function printUrl(url) {
        return new Promise((resolve) => {
            const iframe = document.createElement('iframe');
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            iframe.src = url;
            document.body.appendChild(iframe);

            setTimeout(() => {
                iframe.remove();
                resolve();
            }, 3000);
        });
    }

    async function printSaleDocuments(saleId) {
        if (!saleId) return;

        await printUrl(`/venta/${saleId}/ticket`);
        await printUrl(`/venta/${saleId}/comanda`);
    }

    window.PrintDocs = { printUrl, printSaleDocuments };

    /**
     * Estación de impresión: revisa cada pocos segundos si hay ventas nuevas
     * sin imprimir de la caja abierta y las imprime sola — sin importar desde
     * qué dispositivo se registraron. Corre siempre, en todos los
     * dispositivos: solo la(s) compu(s) con una impresora térmica
     * configurada como predeterminada producen un ticket real.
     */
    const STATION_INTERVAL_MS = 4000;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    async function checkPendingPrints() {
        try {
            const res = await fetch('/impresion/pendientes');
            const data = await res.json();

            for (const sale of data.sales || []) {
                // Se marca ANTES de imprimir (no despues) para que, si el
                // siguiente chequeo arranca mientras esta venta todavia se
                // esta imprimiendo (ticket + comanda tardan varios segundos),
                // no la vuelva a agarrar y la imprima doble.
                await fetch(`/impresion/${sale.id}/marcar`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                });
                await printSaleDocuments(sale.id);
            }
        } catch (err) {
            console.error('Error revisando impresiones pendientes:', err);
        }
    }

    async function stationTick() {
        await checkPendingPrints();
        setTimeout(stationTick, STATION_INTERVAL_MS);
    }

    stationTick();
})(window);
