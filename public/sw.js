// MyCities Service Worker
// Minimal SW to satisfy PWA installability criteria.
// No aggressive caching - keeps the app always fresh from the server.

const CACHE_NAME = 'mycities-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            )
        ).then(() => self.clients.claim())
    );
});

// Network-first strategy: always try the network, fall back to cache for navigation
self.addEventListener('fetch', (event) => {
    // Only handle GET requests for same-origin navigation
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin) return;

    // For navigation requests (page loads), network-first
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() =>
                caches.match('/user/splash')
            )
        );
    }
});
