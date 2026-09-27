<x-layouts.guest title="¡Bienvenido! - Papi's Papas">
    <x-card padding="p-7" class="w-full max-w-sm shadow-lg text-center">
        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center">
            <span class="text-2xl text-primary-500 dark:text-primary-400">✓</span>
        </div>
        <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100 mb-1">¡Bienvenido, {{ $customer->name }}!</p>
        <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-6">Ya eres parte del programa de fidelidad de Papi's Papas.</p>

        @if ($walletSaveLink)
            <a href="{{ $walletSaveLink }}">
                <x-button class="mb-5">Agregar a Google Wallet</x-button>
            </a>

            <div class="flex items-center gap-2 mb-5">
                <div class="flex-1 border-t border-neutral-200 dark:border-neutral-800"></div>
                <p class="text-xs text-neutral-400 dark:text-neutral-500">o usa tu código QR</p>
                <div class="flex-1 border-t border-neutral-200 dark:border-neutral-800"></div>
            </div>
        @endif

        <img src="data:image/svg+xml;base64,{{ $customer->loyaltyQrBase64(220) }}" alt="Tu código QR de fidelidad"
            class="mx-auto mb-3 rounded-lg border border-neutral-200 dark:border-neutral-800 p-2 bg-white">

        <p class="text-xs text-neutral-400 dark:text-neutral-500">
            ¿Tienes iPhone o prefieres no usar Wallet? Toma una captura de pantalla de este código — muéstralo en caja para identificarte.
        </p>
    </x-card>
</x-layouts.guest>
