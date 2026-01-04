/**
 * Service Worker for Offline PWA
 */

const CACHE_NAME = 'equipmentmanager-pwa-v2';
const STATIC_CACHE = 'equipmentmanager-static-v2';

// Files to cache for offline use
const STATIC_FILES = [
    './',
    './index.php',
    './app.js',
    './db.js',
    './manifest.json.php'
];

// Install event - cache static files
self.addEventListener('install', (event) => {
    // console.log('[SW] Installing...');

    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                // console.log('[SW] Caching static files');
                return cache.addAll(STATIC_FILES);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
    // console.log('[SW] Activating...');

    event.waitUntil(
        caches.keys()
            .then(keys => {
                return Promise.all(
                    keys.filter(key => key !== CACHE_NAME && key !== STATIC_CACHE)
                        .map(key => caches.delete(key))
                );
            })
            .then(() => self.clients.claim())
    );
});

// Fetch event - network first with cache fallback
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip external requests
    if (url.origin !== location.origin) {
        return;
    }

    // API requests - network only (we use IndexedDB for offline data)
    if (url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    return new Response(
                        JSON.stringify({ error: 'Offline', offline: true }),
                        { headers: { 'Content-Type': 'application/json' } }
                    );
                })
        );
        return;
    }

    // Static files - cache first, then network
    if (url.pathname.includes('/pwa/') ||
        url.pathname.includes('.js') ||
        url.pathname.includes('.css') ||
        url.pathname.includes('.png') ||
        url.pathname.includes('.jpg')) {

        event.respondWith(
            caches.match(event.request)
                .then(cached => {
                    if (cached) {
                        // Return cached, but also update cache in background
                        fetch(event.request)
                            .then(response => {
                                if (response.ok) {
                                    caches.open(STATIC_CACHE)
                                        .then(cache => cache.put(event.request, response));
                                }
                            })
                            .catch(() => {});
                        return cached;
                    }

                    return fetch(event.request)
                        .then(response => {
                            if (response.ok) {
                                const clone = response.clone();
                                caches.open(STATIC_CACHE)
                                    .then(cache => cache.put(event.request, clone));
                            }
                            return response;
                        });
                })
        );
        return;
    }

    // PHP pages - network first with cache fallback
    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME)
                        .then(cache => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => {
                return caches.match(event.request)
                    .then(cached => {
                        if (cached) return cached;

                        // Return offline page for HTML requests
                        return new Response(
                            '<html><body><h1>Offline</h1><p>Bitte verbinden Sie sich mit dem Internet.</p></body></html>',
                            { headers: { 'Content-Type': 'text/html' } }
                        );
                    });
            })
    );
});

// Background sync event
self.addEventListener('sync', (event) => {
    // console.log('[SW] Sync event:', event.tag);

    if (event.tag === 'sync-changes') {
        event.waitUntil(syncChanges());
    }
});

// Sync changes when back online
async function syncChanges() {
    // This will be handled by the main app
    // Just notify all clients
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
        client.postMessage({ type: 'SYNC_READY' });
    });
}

// Message handler
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
