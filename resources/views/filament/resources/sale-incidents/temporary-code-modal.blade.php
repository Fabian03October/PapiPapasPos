<div x-data="{ copied: false }" class="text-center">
    <p class="text-sm text-gray-500 dark:text-zinc-400 mb-4">
        Dícteselo al cajero por teléfono. Es válido por 10 minutos y de un solo uso.
    </p>

    <div class="flex items-center justify-between gap-3 bg-gray-50 dark:bg-zinc-800 rounded-xl px-5 py-4 mb-2">
        <span class="text-3xl font-semibold tracking-[0.3em] text-gray-900 dark:text-zinc-100">{{ $code }}</span>
        <button
            type="button"
            x-on:click="navigator.clipboard.writeText('{{ $code }}'); copied = true; setTimeout(() => copied = false, 1500)"
            class="w-10 h-10 shrink-0 rounded-lg flex items-center justify-center text-gray-500 dark:text-zinc-400 hover:bg-gray-200 dark:hover:bg-zinc-700"
        >
            <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
            </svg>
            <svg x-show="copied" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </button>
    </div>
    <p x-show="copied" x-cloak class="text-xs text-gray-400 dark:text-zinc-500">Copiado</p>
</div>