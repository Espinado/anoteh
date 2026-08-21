const CACHE_NAME = 'autopark-shell-{{ $version }}';
const OFFLINE_URL = '/offline';
const CORE_ASSETS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/images/icons/icon-192.png',
    '/images/icons/icon-512.png',
    '/images/icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(CORE_ASSETS)),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (
        url.pathname.startsWith('/livewire/')
        || url.pathname.startsWith('/api/')
        || url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/__vite')
        || url.pathname === '/logout'
    ) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );

        return;
    }

    if (CORE_ASSETS.includes(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));

                return response;
            })),
        );
    }
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
