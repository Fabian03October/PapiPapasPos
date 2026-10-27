{{-- "Mi tarjeta": la app web del cliente (instalable, funciona sin internet).
     Página independiente del POS: no carga ningún script de la caja. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $design['bg'] }}">
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
<body class="min-h-dvh bg-[var(--bg)] text-[var(--fg)] antialiased" style="{{ \App\Services\Portal\CardDesign::cssVars($design) }}">
<main class="mx-auto w-full max-w-md px-4 pb-10" style="padding-top: max(12px, env(safe-area-inset-top));">

    <header class="flex justify-center pt-2 pb-4">
        <img src="{{ $design['logo_url'] }}" alt="Papi's Papas" @class([
            'h-24 w-auto max-w-[80%] object-contain',
            'rounded-2xl bg-[#0a0a0a] px-3 py-2' => $design['logo_badge'],
        ])>
    </header>

    @if (request()->boolean('nuevo'))
        <section class="mb-4 rounded-2xl bg-[var(--accent)] px-5 py-4 text-[var(--on-accent)]">
            <p class="text-lg font-bold">¡Bienvenido, {{ $card['name'] }}!</p>
            <p class="text-sm">{{ $design['welcome_message'] ?? "Ya eres parte de Papi's Papas. Guarda esta tarjeta en tu celular (abajo te decimos cómo) y muéstrala en cada visita." }}</p>
        </section>
    @endif

    @if (request()->boolean('nuevo'))
        @include('mi-tarjeta._guardar', ['clase' => 'mb-4'])
    @endif

    @unless ($card['active'])
        <section class="mb-4 rounded-2xl border border-red-400/40 bg-red-500/10 px-5 py-4 text-sm text-red-200">
            Esta tarjeta está inactiva. Si crees que es un error, pregunta en caja.
        </section>
    @endunless

    {{-- QR: lo que escanea la caja. Va dentro del HTML para que abra sin internet. --}}
    <section class="rounded-3xl bg-white p-5 text-center text-[#0a0a0a]">
        <p class="text-xs font-semibold uppercase tracking-widest text-neutral-500">{{ $design['title'] }}</p>
        <p class="mt-1 text-xl font-bold">{{ $card['name'] }}</p>
        <button id="btn-qr-grande" type="button" class="mx-auto my-4 block" aria-label="Agrandar el código QR">
            <img src="data:image/svg+xml;base64,{{ $qrSvg }}" alt="Tu código QR" width="240" height="240" class="h-60 w-60">
        </button>
        <p class="text-sm text-neutral-600">Muestra este código en caja en cada visita · <span class="font-medium text-neutral-800">tócalo para agrandarlo</span></p>
    </section>

    {{-- Sellos --}}
    <section class="mt-4 rounded-3xl bg-[var(--panel)] p-5">
        <div class="flex items-baseline justify-between">
            <h2 class="text-base font-semibold">Tus sellos</h2>
            <p class="text-sm text-[var(--muted)]"><span class="text-xl font-bold text-[var(--accent)]">{{ $card['visits'] }}</span> de {{ $card['goal'] }} · nivel {{ $card['level'] }}</p>
        </div>
        @if ($card['cards_label'])
            <p class="mt-1 text-right text-xs text-[var(--muted)]">{{ $card['cards_label'] }}</p>
        @endif
        <ol class="mt-4 grid grid-cols-4 gap-3" aria-label="Sellos: {{ $card['visits_label'] }}">
            @foreach ($card['stamps'] as $stamp)
                <li class="flex flex-col items-center gap-1">
                    <span @class([
                        'flex h-14 w-14 items-center justify-center rounded-full text-2xl',
                        'bg-[var(--accent)]' => $stamp['filled'],
                        'border-2 border-dashed border-[var(--line)]' => ! $stamp['filled'],
                    ])>
                        @if ($stamp['filled'])
                            @if ($design['stamp_image_url'])
                                <img src="{{ $design['stamp_image_url'] }}" alt="" class="h-9 w-9 object-contain">
                            @else
                                {{ $design['stamp_emoji'] }}
                            @endif
                        @elseif ($stamp['reward'] === 'discount')
                            <span class="text-sm font-bold text-[var(--accent)]">{{ $design['discount_icon'] }}</span>
                        @elseif ($stamp['reward'] === 'gift')
                            {{ $design['gift_icon'] }}
                        @endif
                    </span>
                    <span class="text-[11px] text-[var(--muted)]">{{ $stamp['number'] }}</span>
                </li>
            @endforeach
        </ol>
        <p class="mt-4 rounded-xl bg-[var(--panel-2)] px-4 py-3 text-sm text-[var(--fg)]">
            @if ($card['visits'] === 0)
                Tu primera visita te da tu primer sello {{ $design['stamp_emoji'] }}. <span class="text-[var(--muted)]">Muestra tu código en caja.</span>
            @else
                <span class="text-[var(--muted)]">Próximo premio:</span> {{ $card['next_reward'] }}
            @endif
        </p>
    </section>

    @if ($card['pending_reward'])
        <section class="mt-4 rounded-3xl bg-[var(--accent)] p-5 text-[var(--on-accent)]">
            <p class="text-xs font-semibold uppercase tracking-widest">Premio por cobrar</p>
            <p class="mt-1 text-lg font-bold">{{ $design['gift_icon'] }} {{ $card['pending_reward'] }}</p>
            <p class="mt-1 text-sm">Pídelo en caja en tu próxima visita.</p>
        </section>
    @endif

    @if (count($card['promotions']))
        <section id="promociones" class="mt-4 rounded-3xl bg-[var(--panel)] p-5"
            data-url="{{ url('/mi-tarjeta/'.$customer->qr_code.'/promociones') }}" data-cliente="{{ $customer->qr_code }}">
            <h2 class="text-base font-semibold">Promociones</h2>
            <ul class="mt-3 flex flex-col gap-3">
                @foreach ($card['promotions'] as $promo)
                    <li class="relative rounded-xl bg-[var(--panel-2)] py-3 pl-4 pr-12" data-promo="{{ $promo['id'] }}">
                        <button type="button" class="btn-ocultar absolute right-0 top-0 flex h-11 w-11 items-center justify-center rounded-full text-lg text-[var(--muted)]"
                            aria-label="Quitar esta promoción">✕</button>
                        <p class="font-semibold text-[var(--accent)]">{{ $promo['title'] }}</p>
                        <p class="mt-0.5 text-sm text-[var(--fg)]">{{ $promo['body'] }}</p>
                        <p class="mt-1 text-xs text-[var(--muted)]">{{ $promo['date'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Avisos (Web Push): el estado lo decide el script según el celular. --}}
    @if ($pushKey && $card['active'])
        <section id="avisos" hidden class="mt-4 rounded-3xl bg-[var(--panel)] p-5"
            data-key="{{ $pushKey }}" data-url="{{ route('mi-tarjeta.avisos', $customer->qr_code) }}">
            <div id="avisos-activar" hidden>
                <p class="text-base font-semibold">🔔 Recibe avisos</p>
                <p class="mt-1 text-sm text-[var(--fg)]">Te avisamos cuando ganes un premio, un día antes de que se venza y cuando haya promociones.</p>
                <button id="btn-avisos" type="button" class="mt-3 h-12 w-full rounded-2xl bg-[var(--accent)] font-semibold text-[var(--on-accent)]">Activar avisos</button>
            </div>
            <div id="avisos-activos" hidden class="flex items-center justify-between gap-3">
                <p class="text-sm"><span class="font-semibold">🔔 Avisos activados</span><br><span class="text-[var(--muted)]">Te avisaremos de tus premios y promos.</span></p>
                <button id="btn-avisos-quitar" type="button" class="shrink-0 text-xs text-[var(--muted)] underline">Desactivar</button>
            </div>
            <p id="avisos-instalar" hidden class="text-sm text-[var(--fg)]">🔔 Para recibir avisos en tu iPhone, primero guarda la tarjeta en tu pantalla de inicio (te decimos cómo en esta página) y ábrela desde el ícono.</p>
            <p id="avisos-bloqueados" hidden class="text-sm text-[var(--fg)]">🔕 Los avisos están bloqueados. Si los quieres, actívalos en los ajustes de notificaciones de tu celular.</p>
        </section>
    @endif

    @unless (request()->boolean('nuevo'))
        @include('mi-tarjeta._guardar')
    @endunless

    @if ($design['contact_info'])
        <section class="mt-4 rounded-3xl bg-[var(--panel)] p-5 text-sm">
            <h2 class="text-base font-semibold">Visítanos</h2>
            <p class="mt-2 whitespace-pre-line text-[var(--fg)]">{{ $design['contact_info'] }}</p>
        </section>
    @endif

    {{-- QR a pantalla completa: blanco y grande para que el lector de caja lo lea fácil. --}}
    <div id="qr-grande" hidden role="dialog" aria-label="Código QR en grande"
        class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-white p-6 text-center text-[#111]">
        <p class="text-2xl font-bold">{{ $card['name'] }}</p>
        <img src="data:image/svg+xml;base64,{{ $qrSvg }}" alt="Tu código QR" class="aspect-square w-full max-w-[85vmin]">
        <p class="text-sm text-neutral-600">Sube el brillo de tu pantalla si no lo lee</p>
        <button id="btn-qr-cerrar" type="button" class="mt-2 h-12 w-full max-w-xs rounded-2xl bg-[#111] font-semibold text-white">Cerrar</button>
    </div>

    <p id="estado" class=""mt-6 text-center text-xs text-[var(--muted)]" data-generado="{{ $card['generated_at'] }}">
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

    // QR a pantalla completa (con la pantalla encendida mientras está abierto).
    const qrGrande = document.getElementById('qr-grande');
    let wakeLock = null;
    document.getElementById('btn-qr-grande').addEventListener('click', async () => {
        qrGrande.hidden = false;
        try { wakeLock = await navigator.wakeLock?.request('screen'); } catch (e) {}
    });
    const cerrarQr = () => { qrGrande.hidden = true; wakeLock?.release?.().catch(() => {}); wakeLock = null; };
    document.getElementById('btn-qr-cerrar').addEventListener('click', cerrarQr);
    qrGrande.addEventListener('click', (e) => { if (e.target === qrGrande) cerrarQr(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !qrGrande.hidden) cerrarQr(); });

    // Quitar promociones (✕): se ocultan al instante; sin internet quedan
    // pendientes en el celular y se mandan cuando vuelva la conexión.
    const promos = document.getElementById('promociones');
    if (promos) {
        const clave = 'mi-tarjeta-ocultas-' + promos.dataset.cliente;
        const leer = () => { try { return JSON.parse(localStorage.getItem(clave) || '[]'); } catch (e) { return []; } };
        const escribir = (ids) => { try { localStorage.setItem(clave, JSON.stringify(ids)); } catch (e) {} };
        const quitarDeLista = (id) => {
            promos.querySelector(`[data-promo="${id}"]`)?.remove();
            if (!promos.querySelector('[data-promo]')) promos.hidden = true;
        };
        const mandar = (id) => fetch(`${promos.dataset.url}/${id}/ocultar`, { method: 'POST', headers: { 'Accept': 'application/json' } })
            .then((r) => { if (r.ok || r.status === 404) escribir(leer().filter((x) => x !== id)); })
            .catch(() => {});

        // Las que quitó sin internet (la copia guardada aún las trae): ocultar y reintentar.
        leer().forEach((id) => { quitarDeLista(id); if (navigator.onLine) mandar(id); });
        window.addEventListener('online', () => leer().forEach(mandar));

        promos.addEventListener('click', (event) => {
            const boton = event.target.closest('.btn-ocultar');
            if (!boton) return;
            const id = Number(boton.closest('[data-promo]').dataset.promo);
            escribir([...new Set([...leer(), id])]);
            quitarDeLista(id);
            mandar(id);
        });
    }

    // Avisos (Web Push).
    const avisos = document.getElementById('avisos');
    const verAvisos = (estado) => {
        if (!avisos) return;
        avisos.hidden = false;
        for (const id of ['activar', 'activos', 'instalar', 'bloqueados']) {
            document.getElementById('avisos-' + id).hidden = id !== estado;
        }
    };
    const llave = (base64) => {
        const pad = '='.repeat((4 - base64.length % 4) % 4);
        const raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
    };
    const guardarAvisos = (sub, metodo = 'POST') => fetch(avisos.dataset.url, {
        method: metodo,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(sub.toJSON ? sub.toJSON() : sub),
    });

    async function prepararAvisos(reg) {
        if (!avisos) return;
        if (!('PushManager' in window) || !('Notification' in window)) {
            // iPhone: solo funciona instalada en la pantalla de inicio.
            if (esIphone && !instalada) verAvisos('instalar');
            return;
        }
        if (Notification.permission === 'denied') return verAvisos('bloqueados');
        const sub = await reg.pushManager.getSubscription();
        if (sub && Notification.permission === 'granted') {
            guardarAvisos(sub).catch(() => {}); // por si el servidor no la tenía
            return verAvisos('activos');
        }
        verAvisos('activar');
    }

    document.getElementById('btn-avisos')?.addEventListener('click', async () => {
        const boton = document.getElementById('btn-avisos');
        boton.disabled = true;
        try {
            const permiso = await Notification.requestPermission();
            if (permiso !== 'granted') return verAvisos(permiso === 'denied' ? 'bloqueados' : 'activar');
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: llave(avisos.dataset.key) });
            const res = await guardarAvisos(sub);
            verAvisos(res.ok ? 'activos' : 'activar');
        } catch (e) {
            verAvisos('activar');
            alert('No se pudieron activar los avisos. Revisa tu conexión e intenta de nuevo.');
        } finally {
            boton.disabled = false;
        }
    });

    document.getElementById('btn-avisos-quitar')?.addEventListener('click', async () => {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
            await guardarAvisos({ endpoint: sub.endpoint }, 'DELETE').catch(() => {});
            await sub.unsubscribe();
        }
        verAvisos('activar');
    });

    // Sin internet: el service worker solo controla /mi-tarjeta/ (nunca el POS).
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/mi-tarjeta-sw.js', { scope: '/mi-tarjeta/' }).then(() => navigator.serviceWorker.ready).then((reg) => {
            const guardar = [location.pathname, ...[...document.querySelectorAll('link[rel="stylesheet"], link[rel="manifest"], img[src^="/"]')]
                .map((el) => el.getAttribute('href') || el.getAttribute('src'))];
            reg.active && reg.active.postMessage({ guardar });
            return prepararAvisos(reg);
        }).catch(() => {});
    }
})();
</script>
</body>
</html>
