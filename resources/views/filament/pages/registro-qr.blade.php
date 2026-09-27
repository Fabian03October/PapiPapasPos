<x-filament-panels::page>
    <div class="max-w-md mx-auto">
        <x-filament::section>
            <div class="flex flex-col items-center gap-6 py-2 text-center" id="registro-qr-print">
                <div>
                    <p class="text-base font-semibold text-gray-950 dark:text-white">
                        Pégalo en el mostrador
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-xs mx-auto">
                        Cualquier cliente lo escanea para registrarse solo y guardar su tarjeta de
                        fidelidad en Google Wallet.
                    </p>
                </div>

                <div class="p-6 bg-white rounded-2xl border border-gray-200 shadow-sm dark:border-white/10">
                    <img
                        src="data:image/svg+xml;base64,{{ $this->getQrBase64() }}"
                        alt="QR de registro"
                        width="260"
                        height="260"
                    >
                </div>

                <p class="text-xs font-mono text-gray-400 dark:text-gray-500 break-all">
                    {{ $this->getRegistrationUrl() }}
                </p>

                <div class="flex flex-wrap items-center justify-center gap-2 print:hidden">
                    <x-filament::link
                        tag="button"
                        type="button"
                        color="primary"
                        icon="heroicon-o-printer"
                        x-on:click="window.print()"
                    >
                        Imprimir
                    </x-filament::link>

                    <x-filament::link
                        :href="'data:image/svg+xml;base64,' . $this->getQrBase64()"
                        download="qr-registro-papipapas.svg"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                    >
                        Descargar SVG
                    </x-filament::link>
                </div>
            </div>
        </x-filament::section>
    </div>

    <style>
        @media print {
            .fi-topbar, .fi-sidebar, .fi-header { display: none !important; }
        }
    </style>
</x-filament-panels::page>
