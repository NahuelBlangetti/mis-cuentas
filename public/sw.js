const CACHE = 'mis-cuentas-static-v4';
const STATIC_FILES = ['/css/app.css', '/js/app.js', '/icon.svg', '/icon-192.png', '/icon-512.png', '/icon-180.png', '/offline.html', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(STATIC_FILES)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)))));
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    if (STATIC_FILES.includes(url.pathname)) {
        // Responde con la copia guardada y la renueva en segundo plano: un deploy llega en la carga siguiente.
        const update = caches.open(CACHE).then((cache) => fetch(request).then((response) => {
            if (response.ok) cache.put(request, response.clone());
            return response;
        }));
        event.respondWith(caches.match(request).then((cached) => cached || update));
        event.waitUntil(update.catch(() => {}));
    }
});
