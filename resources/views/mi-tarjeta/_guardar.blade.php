    {{-- Guardar la tarjeta: cada botón aparece solo donde aplica (ver script). --}}
    <section id="guardar" class="{{ $clase ?? 'mt-4' }} flex flex-col gap-3">
        <button id="btn-instalar" type="button" hidden
            class="h-12 rounded-2xl bg-[var(--accent)] font-semibold text-[var(--on-accent)]">
            Instalar mi tarjeta en el celular
        </button>

        <div id="ios-instrucciones" hidden class="rounded-3xl bg-[var(--panel)] p-5 text-sm text-[var(--fg)]">
            <p class="font-semibold text-[var(--fg)]">Guárdala en tu iPhone</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>Toca el botón <strong>Compartir</strong> <span aria-hidden="true">(el cuadro con la flecha ↑)</span> de Safari.</li>
                <li>Elige <strong>Agregar a pantalla de inicio</strong>.</li>
                <li>Toca <strong>Agregar</strong>. Te queda el ícono de Papi's Papas, y abre aunque no tengas internet.</li>
            </ol>
        </div>

        @if ($card['google_wallet_url'])
            <a id="btn-google-wallet" hidden href="{{ $card['google_wallet_url'] }}"
                class="flex h-12 items-center justify-center rounded-2xl border border-[var(--line)] font-semibold text-[var(--fg)]">
                Agregar a Google Wallet
            </a>
        @endif
    </section>
