const CACHE_NAME = 'dzulfikri-store-v1';
const PRECACHE_ASSETS = [
    '/',
    '/manifest.json',
    '/css/app.css',
    '/js/app.js',
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-512x512.png',
    '/favicon.ico'
];

// Install Event
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('PWA Precache partial fail:', err);
            });
        })
    );
    self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Fetch Event
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Skip non-GET requests (e.g. POST, PUT, DELETE)
    if (request.method !== 'GET') {
        return;
    }

    // Skip auth/admin actions and external chrome-extension requests
    const url = new URL(request.url);
    if (!url.protocol.startsWith('http')) {
        return;
    }

    // Static Assets: Cache-First, fallback to Network
    if (url.pathname.match(/\.(css|js|png|jpg|jpeg|svg|woff2|woff|ttf|ico)$/)) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Navigation and Dynamic HTML: Network-First, fallback to Cache
    event.respondWith(
        fetch(request).catch(() => {
            return caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                // If offline and request is an HTML page
                if (request.headers.get('accept') && request.headers.get('accept').includes('text/html')) {
                    return caches.match('/');
                }
            });
        })
    );
});
