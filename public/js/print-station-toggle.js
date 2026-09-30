(function () {
    const toggle = document.getElementById('print-station-toggle');
    if (!toggle || !window.PrintDocs) return;

    const dot = toggle.querySelector('span');

    function render(enabled) {
        toggle.classList.toggle('bg-primary-600', enabled);
        toggle.classList.toggle('bg-neutral-200', !enabled);
        toggle.classList.toggle('dark:bg-neutral-700', !enabled);
        dot.style.transform = enabled ? 'translateX(16px)' : 'translateX(0)';
    }

    render(window.PrintDocs.isPrintStation());

    toggle.addEventListener('click', () => {
        const next = !window.PrintDocs.isPrintStation();
        window.PrintDocs.setPrintStation(next);
        // Recarga para que print.js decida de nuevo, en este load, si
        // arranca o no el sondeo de la estación de impresión.
        window.location.reload();
    });
})();
