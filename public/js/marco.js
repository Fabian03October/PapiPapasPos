/**
 * Marco fijo del POS (ver components/layouts/app.blade.php): la barra, el
 * menú y la conexión con la impresora Bluetooth se quedan; cada pantalla
 * (Vender, Corte de caja...) carga en el iframe "contenido". Así cambiar de
 * pantalla ya no recarga la página ni corta el Bluetooth.
 *
 * Aquí solo se mantiene el marco al día con la pantalla del centro: qué
 * opción del menú se resalta, el título, y la dirección en la barra del
 * navegador (para que recargar regrese a la misma pantalla).
 */
(function () {
    const frame = document.getElementById('contenido');
    if (!frame) return;

    const links = document.querySelectorAll('[data-nav]');
    const mobileTitle = document.getElementById('page-title-mobile');
    const ACTIVE = ['bg-primary-50', 'dark:bg-primary-500/10', 'text-primary-600', 'dark:text-primary-400'];
    const INACTIVE = ['text-neutral-600', 'dark:text-neutral-400', 'hover:bg-neutral-100', 'dark:hover:bg-neutral-800'];

    function actualizar() {
        let doc;
        let loc;
        try {
            doc = frame.contentDocument;
            loc = frame.contentWindow.location;
        } catch (e) {
            return;
        }
        if (!doc || !loc || loc.href === 'about:blank') return;

        const meta = (name) => doc.querySelector(`meta[name="${name}"]`)?.content || '';
        // El panel admin (Filament) también se abre aquí, dentro del marco,
        // para no cerrar la página que tiene la impresora conectada. No es
        // una pantalla del POS: no trae estos datos.
        const isPosScreen = !!doc.querySelector('meta[name="pos-active"]') && !esAdmin(loc.pathname);
        const active = isPosScreen ? meta('pos-active') : '';

        links.forEach((link) => {
            const on = link.dataset.nav === active;
            ACTIVE.forEach((c) => link.classList.toggle(c, on));
            INACTIVE.forEach((c) => link.classList.toggle(c, !on));
        });

        modoAdmin(!isPosScreen);

        if (mobileTitle && meta('pos-page-title')) mobileTitle.textContent = meta('pos-page-title');
        if (doc.title) document.title = doc.title;

        // Solo pantallas del POS: si se recarga estando en el admin, que
        // regrese a la última pantalla del POS (con el marco), no al admin suelto.
        const url = loc.pathname + loc.search + loc.hash;
        if (isPosScreen && url !== window.location.pathname + window.location.search + window.location.hash) {
            window.history.replaceState(window.history.state, '', url);
        }
    }

    frame.addEventListener('load', actualizar);

    /**
     * El panel admin trae su propio menú: mientras está abierto se esconde el
     * menú de ventas (para no tener dos) y el admin usa todo el ancho. La
     * barra del POS también se esconde: el admin trae la suya, con "Volver a
     * ventas" (ver filament/volver-a-ventas).
     */
    const grid = document.getElementById('pos-grid');
    const sidebar = document.getElementById('sidebar');
    const mobileBar = document.getElementById('pos-mobile-bar');

    const header = document.getElementById('pos-header');

    function modoAdmin(on) {
        // El admin trae su propia barra (con "Volver a ventas"): se esconde la
        // del POS para no tener dos, y el admin ocupa toda la pantalla. La
        // conexión con la impresora vive en esta página, no en la barra: no
        // se corta.
        if (header) header.style.display = on ? 'none' : '';
        if (sidebar) sidebar.style.display = on ? 'none' : '';
        if (mobileBar) mobileBar.style.display = on ? 'none' : '';
        if (grid) {
            grid.style.gridTemplateColumns = on ? '1fr' : '';
            grid.style.padding = on ? '0' : '';
        }
    }

    // En el celular el menú es un panel que se abre encima: se cierra al elegir.
    links.forEach((link) => link.addEventListener('click', () => {
        if (typeof closeSidebar === 'function') closeSidebar();
    }));

    // El menú de usuario (ej. "Panel admin") abre en el marco sin recargar:
    // se cierra solo al elegir.
    document.querySelectorAll('#user-menu a[target="contenido"]').forEach((link) => {
        link.addEventListener('click', () => document.getElementById('user-menu')?.classList.add('hidden'));
    });

    function esAdmin(path) {
        return path === '/admin' || path.startsWith('/admin/');
    }

    // Al tocar "Panel admin" se esconde el menú de ventas de una vez, sin
    // esperar a que cargue: si no, el admin se dibuja angosto y pone su
    // propio menú encimado (con el fondo oscurecido).
    document.querySelectorAll('a[target="contenido"]').forEach((link) => {
        link.addEventListener('click', () => {
            try {
                if (esAdmin(link.pathname || '')) modoAdmin(true);
            } catch (e) {}
        });
    });

    // Si el contenido terminó de cargar antes que este script, se actualiza ya.
    try {
        if (frame.contentDocument && frame.contentDocument.readyState === 'complete') actualizar();
    } catch (e) {}
})();
