{{-- "Mi tarjeta": la app web del cliente (instalable, funciona sin internet).
     Página independiente del POS: no carga ningún script de la caja. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a0a0a">
    <title>Mi tarjeta · Papi's Papas</title>
    <link rel="manifest" href="{{ route('mi-tarjeta.manifest', $customer->qr_code) }}">
    <link rel="icon" href="/pwa/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Papi's Papas">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    @vite('resources/css/app.css')
</head>
<body class="min-h-dvh bg-[#0a0a0a] text-white antialiased">
<main class="mx-auto w-full max-w-md px-4 pb-10" style="padding-top: max(12px, env(safe-area-inset-top));">

    <header class="flex justify-center pt-2 pb-4">
        <img src="/pwa/logo-header.png" alt="Papi's Papas" width="232" height="120" class="h-24 w-auto">
    </header>

    @if (request()->boolean('nuevo'))
        <section class="mb-4 rounded-2xl bg-[#f5b32f] px-5 py-4 text-[#0a0a0a]">
            <p class="text-lg font-bold">¡Bienvenido, {{ $card['name'] }}!</p>
            <p class="text-sm">Ya eres parte de Papi's Papas. Guarda esta tarjeta en tu celular (abajo te decimos cómo) y muéstrala en cada visita.</p>
        </section>
    @endif

    @unless ($card['active'])
        <section class="mb-4 rounded-2xl border border-red-400/40 bg-red-500/10 px-5 py-4 text-sm text-red-200">
            Esta tarjeta está inactiva. Si crees que es un error, pregunta en caja.
        </section>
    @endunless

    {{-- QR: lo que escanea la caja. Va dentro del HTML para que abra sin internet. --}}
    <section class="rounded-3xl bg-white p-5 text-center text-[#0a0a0a]">
        <p class="text-xs font-semibold uppercase tracking-widest text-neutral-500">Tarjeta de sellos</p>
        <p class="mt-1 text-xl font-bold">{{ $card['name'] }}</p>
        <img src="data:image/svg+xml;base64,{{ $qrSvg }}" alt="Tu código QR" width="240" height="240" class="mx-auto my-4 h-60 w-60">
        <p class="text-sm text-neutral-600">Muestra este código en caja en cada visita</p>
    </section>

    {{-- Sellos --}}
    <section class="mt-4 rounded-3xl bg-[#18181b] p-5">
        <div class="flex items-baseline justify-between">
            <h2 class="text-base font-semibold">Tus sellos</h2>
            <p class="text-sm text-neutral-400"><span class="text-xl font-bold text-[#f5b32f]">{{ $card['visits'] }}</span> de {{ $card['goal'] }} · nivel {{ $card['level'] }}</p>
        </div>
        <ol class="mt-4 grid grid-cols-4 gap-3" aria-label="Sellos: {{ $card['visits_label'] }}">
            @foreach ($card['stamps'] as $stamp)
                <li class="flex flex-col items-center gap-1">
                    <span @class([
                        'flex h-14 w-14 items-center justify-center rounded-full text-2xl',
                        'bg-[#f5b32f]' => $stamp['filled'],
                        'border-2 border-dashed border-neutral-600' => ! $stamp['filled'],
                    ])>
                        @if ($stamp['filled'])
                            🍟
                        @elseif ($stamp['reward'] === 'discount')
                            <span class="text-sm font-bold text-[#f5b32f]">%</span>
                        @elseif ($stamp['reward'] === 'gift')
                            🎁
                        @endif
                    </span>
                    <span class="text-[11px] text-neutral-500">{{ $stamp['number'] }}</span>
                </li>
            @endforeach
        </ol>
        <p class="mt-4 rounded-xl bg-white/5 px-4 py-3 text-sm text-neutral-200">
            <span class="text-neutral-400">Próximo premio:</span> {{ $card['next_reward'] }}
        </p>
    </section>

    @if ($card['pending_reward'])
        <section class="mt-4 rounded-3xl bg-[#f5b32f] p-5 text-[#0a0a0a]">
            <p class="text-xs font-semibold uppercase tracking-widest">Premio por cobrar</p>
            <p class="mt-1 text-lg font-bold">🎁 {{ $card['pending_reward'] }}</p>
            <p class="mt-1 text-sm">Pídelo en caja en tu próxima visita.</p>
        </section>
    @endif

    @if (count($card['promotions']))
        <section class="mt-4 rounded-3xl bg-[#18181b] p-5">
            <h2 class="text-base font-semibold">Promociones</h2>
            <ul class="mt-3 flex flex-col gap-3">
                @foreach ($card['promotions'] as $promo)
                    <li class="rounded-xl bg-white/5 px-4 py-3">
                        <p class="font-semibold text-[#f5b32f]">{{ $promo['title'] }}</p>
                        <p class="mt-0.5 text-sm text-neutral-200">{{ $promo['body'] }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ $promo['date'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Guardar la tarjeta: cada botón aparece solo donde aplica (ver script). --}}
    <section id="guardar" class="mt-4 flex flex-col gap-3">
        <button id="btn-instalar" type="button" hidden
            class="h-12 rounded-2xl bg-[#f5b32f] font-semibold text-[#0a0a0a]">
            Instalar mi tarjeta en el celular
        </button>

        <div id="ios-instrucciones" hidden class="rounded-3xl bg-[#18181b] p-5 text-sm text-neutral-200">
            <p class="font-semibold text-white">Guárdala en tu iPhone</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>Toca el botón <strong>Compartir</strong> <span aria-hidden="true">(el cuadro con la flecha ↑)</span> de Safari.</li>
                <li>Elige <strong>Agregar a pantalla de inicio</strong>.</li>
                <li>Toca <strong>Agregar</strong>. Te queda el ícono de Papi's Papas, y abre aunque no tengas internet.</li>
            </ol>
        </div>

        @if ($card['google_wallet_url'])
            <a id="btn-google-wallet" hidden href="{{ $card['google_wallet_url'] }}"
                class="flex h-12 items-center justify-center rounded-2xl border border-white/20 font-semibold text-white">
                Agregar a Google Wallet
            </a>
        @endif
    </section>

    <p id="estado" class="mt-6 text-center text-xs text-neutral-500" data-generado="{{ $card['generated_at'] }}">
        Actualizado hace un momento
    </p>
</main>

<script>
(() => {
    const estado = document.getElementById('estado');
    const generado = new Date(estado.dataset.generado);
    const instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const ua = navigator.userAgent;
    const esIphone = /iPhone|iPad|iPod/.test(ua);
    const esAndroid = /Android/.test(ua);

    function hace() {
        const min = Math.round((Date.now() - generado) / 60000);
        const cuando = min < 1 ? 'hace un momento' : min < 60 ? `hace ${min} min` : min < 1440 ? `hace ${Math.round(min / 60)} h` : `hace ${Math.round(min / 1440)} días`;
        estado.textContent = navigator.onLine ? `Actualizado ${cuando}` : `Sin conexión · datos de ${cuando}`;
    }
    hace();
    setInterval(hace, 30000);
    window.addEventListener('offline', hace);

    // Con internet otra vez, o al volver a abrirla después de un rato: trae lo nuevo.
    const refrescar = () => { if (navigator.onLine && Date.now() - generado > 60000) location.reload(); };
    window.addEventListener('online', refrescar);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refrescar(); });

    // Botones para guardarla, solo donde sirven y si aún no está instalada.
    if (!instalada) {
        if (esIphone) document.getElementById('ios-instrucciones').hidden = false;
        if (esAndroid) {
            const google = document.getElementById('btn-google-wallet');
            if (google) google.hidden = false;
        }
    }
    let aviso = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        aviso = event;
        document.getElementById('btn-instalar').hidden = false;
    });
    document.getElementById('btn-instalar').addEventListener('click', async () => {
        if (!aviso) return;
        aviso.prompt();
        await aviso.userChoice;
        aviso = null;
        document.getElementById('btn-instalar').hidden = true;
    });

    // Sin internet: el service worker solo controla /mi-tarjeta/ (nunca el POS).
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/mi-tarjeta-sw.js', { scope: '/mi-tarjeta/' }).then(() => navigator.serviceWorker.ready).then((reg) => {
            const guardar = [location.pathname, ...[...document.querySelectorAll('link[rel="stylesheet"], link[rel="manifest"], img[src^="/"]')]
                .map((el) => el.getAttribute('href') || el.getAttribute('src'))];
            reg.active && reg.active.postMessage({ guardar });
        }).catch(() => {});
    }
})();
</script>
</body>
</html>
