(function (window) {
    const VARIANTS = {
        success: 'bg-green-600 text-white',
        error: 'bg-neutral-900 dark:bg-neutral-100 text-white dark:text-neutral-900',
    };

    const ICONS = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />',
        error: '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />',
    };

    function show(message, variant = 'success') {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-lg shadow-lg text-sm font-medium flex items-center gap-2 max-w-[90vw] ${VARIANTS[variant] || VARIANTS.success}`;
        toast.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                ${ICONS[variant] || ICONS.success}
            </svg>
            <span></span>
        `;
        toast.querySelector('span').textContent = message;

        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), variant === 'error' ? 4000 : 3500);
    }

    window.Toast = { show };
})(window);
