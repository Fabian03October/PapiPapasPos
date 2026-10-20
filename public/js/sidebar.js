const sidebar = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebar-backdrop');
const menuToggle = document.getElementById('menu-toggle');
const sidebarClose = document.getElementById('sidebar-close');

function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    backdrop.classList.remove('hidden');
}

function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    backdrop.classList.add('hidden');
}

if (menuToggle) menuToggle.addEventListener('click', openSidebar);
if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
if (backdrop) backdrop.addEventListener('click', closeSidebar);

// Esconder/mostrar el menú lateral en tablet y compu (se recuerda en este
// dispositivo). Lo de en medio (la venta) se hace más ancho al esconderlo.
const menuCollapse = document.getElementById('menu-collapse');

if (menuCollapse) {
    const syncCollapse = () => {
        const oculto = document.documentElement.classList.contains('menu-oculto');
        menuCollapse.setAttribute('aria-expanded', oculto ? 'false' : 'true');
        menuCollapse.setAttribute('aria-label', oculto ? 'Mostrar menú' : 'Esconder menú');
    };
    syncCollapse();
    menuCollapse.addEventListener('click', () => {
        const oculto = document.documentElement.classList.toggle('menu-oculto');
        try { localStorage.setItem('pos_menu_oculto', oculto ? '1' : '0'); } catch (e) {}
        syncCollapse();
    });
}

const userMenuTrigger = document.getElementById('user-menu-trigger');
const userMenu = document.getElementById('user-menu');

if (userMenuTrigger && userMenu) {
    userMenuTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('hidden');
    });
    document.addEventListener('click', (e) => {
        if (!userMenu.contains(e.target)) userMenu.classList.add('hidden');
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') userMenu.classList.add('hidden');
    });
}

const themeOptions = document.querySelectorAll('.theme-option');
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

function applyTheme(value) {
    const isDark = value === 'dark' || (value === 'system' && prefersDark.matches);
    document.documentElement.classList.toggle('dark', isDark);
    themeOptions.forEach((btn) => {
        const active = btn.dataset.theme === value;
        btn.classList.toggle('bg-primary-50', active);
        btn.classList.toggle('dark:bg-primary-500/10', active);
        btn.classList.toggle('text-primary-600', active);
        btn.classList.toggle('dark:text-primary-400', active);
        btn.classList.toggle('text-neutral-400', !active);
        btn.classList.toggle('dark:text-neutral-500', !active);
    });
}

if (themeOptions.length) {
    applyTheme(localStorage.theme || 'system');

    themeOptions.forEach((btn) => {
        btn.addEventListener('click', () => {
            localStorage.theme = btn.dataset.theme;
            applyTheme(btn.dataset.theme);
        });
    });

    prefersDark.addEventListener('change', () => {
        if (localStorage.theme === 'system') applyTheme('system');
    });
}
