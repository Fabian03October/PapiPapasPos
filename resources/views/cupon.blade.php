<x-layouts.guest title="Tu cupón - Papi's Papas">
    <x-card padding="p-7" class="w-full max-w-sm shadow-lg text-center">
        <p class="text-xs font-medium tracking-widest uppercase text-primary-600 dark:text-primary-400 mb-1">🎟 Cupón de Papi's Papas</p>
        <p class="text-2xl font-bold text-neutral-900 dark:text-neutral-100 mb-1">{{ $pass->benefitLabel() }}</p>

        @php($status = $pass->status())
        @if ($status === 'Disponible')
            <p class="text-sm text-neutral-500 dark:text-neutral-400 mb-5">
                Vale hasta el {{ $pass->expires_on->locale('es')->translatedFormat('j \d\e F') }}. Se usa una sola vez.
            </p>

            <img src="data:image/svg+xml;base64,{{ $pass->qrBase64(240) }}" alt="Código QR del cupón"
                class="mx-auto mb-3 rounded-lg border border-neutral-200 dark:border-neutral-800 p-2 bg-white">

            <p class="font-mono text-xl font-bold tracking-widest text-neutral-900 dark:text-neutral-100 mb-3">{{ $pass->displayCode() }}</p>
            <p class="text-xs text-neutral-400 dark:text-neutral-500">Muestra este código en caja.</p>
        @else
            <p class="mt-4 text-base font-medium text-red-600 dark:text-red-400">
                {{ $status === 'Usado' ? 'Este cupón ya se usó.' : 'Este cupón ya venció.' }}
            </p>
        @endif
    </x-card>
</x-layouts.guest>
