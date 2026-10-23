/*
 * Service worker de "Mi tarjeta" (la app web del cliente).
 *
 * Se registra con scope '/mi-tarjeta/': SOLO controla esas páginas. Las
 * pantallas del POS, el panel y la impresora nunca pasan por aquí.
 *
 * - Páginas y datos de la tarjeta: primero internet (siempre lo más nuevo);
 *   sin internet, la última copia guardada (el QR va dentro del HTML).
 * - Estilos e íconos: de la copia guardada (cambian de nombre al cambiar).
 * - Avisos (Web Push): los muestra aunque la tarjeta esté cerrada; al
 *   tocarlos abre la tarjeta del cliente.
 */
const CACHE = 'mi-tarjeta-v2';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key.startsWith('mi-tarjeta-') && key !== CACHE)
                .map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

// La página pide guardar de una vez lo que necesita para abrir sin internet
// (la primera visita ocurre antes de que este worker la controle).
self.addEventListener('message', (event) => {
    const urls = (event.data && event.data.guardar) || [];
    event.waitUntil(
        caches.open(CACHE).then((cache) => Promise.all(urls.map((url) =>
            fetch(url, { credentials: 'same-origin' })
                .then((response) => response.ok && cache.put(stripQuery(url), response))
                .catch(() => {})
        )))
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    const isCard = url.pathname.startsWith('/mi-tarjeta/');
    const isAsset = url.pathname.startsWith('/build/') || url.pathname.startsWith('/pwa/');

    if (isAsset) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }
                return response;
            }))
        );
        return;
    }

    if (!isCard) return;

    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(stripQuery(request.url), copy));
                }
                return response;
            })
            .catch(() => caches.match(stripQuery(request.url))
                .then((cached) => cached || new Response(
                    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                    + '<body style="background:#0a0a0a;color:#fff;font-family:system-ui;padding:32px;text-align:center">'
                    + '<h2>Sin conexión</h2><p>Abre tu tarjeta una vez con internet para poder usarla sin conexión.</p></body>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                )))
    );
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data && event.data.text() }; }

    event.waitUntil(self.registration.showNotification(data.title || "Papi's Papas", {
        body: data.body || '',
        icon: '/pwa/icon-192.png',
        badge: '/pwa/icon-192.png',
        tag: data.tag || undefined,
        data: { url: data.url || '/' },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data && event.notification.data.url || '/', self.location.origin).href;

    // Si la tarjeta ya está abierta, se trae al frente y se recarga; si no, se abre.
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        for (const win of windows) {
            if (win.url.split('?')[0] === url.split('?')[0] && 'focus' in win) {
                return win.focus().then((w) => w && w.navigate ? w.navigate(url) : w);
            }
        }
        return self.clients.openWindow(url);
    }));
});

// "?app=1" o "?nuevo=1" no cambian la tarjeta: se guarda una sola copia.
function stripQuery(url) {
    const u = new URL(url, self.location.origin);
    u.search = '';
    return u.toString();
}
