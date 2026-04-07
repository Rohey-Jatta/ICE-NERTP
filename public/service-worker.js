/**
 * Service Worker - Offline-first PWA support.
 *
 * From architecture: public/service-worker.js
 *
 * Features:
 * - Cache static assets for offline use
 * - Background sync for pending submissions
 * - Network-first strategy for API calls
 */

const CACHE_NAME = 'iec-nertp-v1';
const ASSETS_TO_CACHE = [
    '/',
    '/auth/login',
    '/build/assets/app.css',
    '/build/assets/app.js',
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    console.log('Service Worker installing...');

    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('Caching static assets');
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );

    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('Service Worker activating...');

    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );

    self.clients.claim();
});

// Fetch event - network-first for API, cache-first for assets
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // API requests - network first, no cache
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(request).catch(() => {
                return new Response(
                    JSON.stringify({
                        error: 'Please try again.'
                    }),
                    {
                        status: 503,
                        headers: { 'Content-Type': 'application/json' }
                    }
                );
            })
        );
        return;
    }

    // Static assets - cache first, fallback to network
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then((response) => {
                // Don't cache non-successful responses
                if (!response || response.status !== 200 || response.type === 'error') {
                    return response;
                }

                // Clone response and cache it
                const responseToCache = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, responseToCache);
                });

                return response;
            });
        })
    );
});

// Background sync event
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-results') {
        console.log('Background sync triggered');
        event.waitUntil(syncPendingResults());
    }
});

async function syncPendingResults() {
    // This will be handled by SyncQueue.js in the app
    // Service worker just triggers the event
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
        client.postMessage({
            type: 'BACKGROUND_SYNC',
            tag: 'sync-results'
        });
    });
}
