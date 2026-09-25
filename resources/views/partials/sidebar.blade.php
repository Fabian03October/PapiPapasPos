<div class="flex items-center justify-between p-3 border-b border-neutral-200 dark:border-neutral-800 md:hidden">
        <button id="menu-toggle" type="button" class="p-1 text-neutral-700 dark:text-neutral-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
            </svg>
        </button>
        <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $pageTitle ?? 'posPapisV1' }}</span>
        <span class="w-6"></span>
    </div>

    <div id="sidebar-backdrop" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

    <div id="sidebar" class="fixed md:static inset-y-0 left-0 z-40 w-64 md:w-auto
                -translate-x-full md:translate-x-0 transition-transform duration-200
                bg-neutral-50 dark:bg-neutral-900 md:bg-transparent
                p-4 md:p-0
                flex flex-col gap-1">

        <div class="flex items-center justify-between mb-3 md:hidden">
            <p class="text-xs text-neutral-400 dark:text-neutral-500 uppercase tracking-wide font-medium">Menú</p>
            <button id="sidebar-close" type="button" class="text-neutral-400 dark:text-neutral-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <p class="hidden md:block text-xs text-neutral-400 dark:text-neutral-500 uppercase tracking-wide font-medium mb-2 px-3">Menú</p>

        <a href="{{ route('venta.index') }}" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium {{ ($active ?? '') === 'vender' ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400' : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 1.976-4.766 2.53-7.352.104-.487-.263-.898-.762-.898H5.106M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
            </svg>
            Vender
        </a>
        <a href="{{ route('caja.resumen') }}" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium {{ ($active ?? '') === 'caja' ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400' : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
            </svg>
            Corte de caja
        </a>
        <a href="{{ route('venta.historial') }}" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium {{ ($active ?? '') === 'historial' ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400' : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            Ventas del turno
        </a>
        <a href="{{ route('venta.cliente') }}" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium {{ ($active ?? '') === 'cliente' ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400' : 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Cliente
        </a>
    </div>

    <script src="{{ asset('js/print.js') }}"></script>