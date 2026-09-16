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

        const themeToggle = document.getElementById('theme-toggle');
        const iconMoon = document.getElementById('theme-icon-moon');
        const iconSun = document.getElementById('theme-icon-sun');
        const themeLabel = document.getElementById('theme-label');

        function updateThemeLabel() {
            const isDark = document.documentElement.classList.contains('dark');
            iconMoon.classList.toggle('hidden', isDark);
            iconSun.classList.toggle('hidden', !isDark);
            themeLabel.textContent = isDark ? 'Modo claro' : 'Modo oscuro';
        }
        updateThemeLabel();

        themeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            updateThemeLabel();
        });