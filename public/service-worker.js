/**
 * DancyMeals Service Worker
 *
 * Handles offline fallback only. No data caching or sync.
 * The offline page is pre-cached during installation.
 * Push notification handlers will be added by F-014.
 */

const CACHE_NAME = 'dmc-offline-v1';
const OFFLINE_URL = '/offline.html';

/**
 * Install event: pre-cache the offline fallback page.
 */
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));
        })
    );
    // Activate immediately without waiting for existing clients to close
    self.skipWaiting();
});

/**
 * Activate event: clean up old caches from previous versions.
 */
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        })
    );
    // Take control of all open clients immediately
    self.clients.claim();
});

/**
 * Fetch event: serve offline fallback when navigation requests fail.
 * Only intercepts navigation requests (HTML pages). All other requests
 * (API calls, assets) pass through to the network normally.
 */
self.addEventListener('fetch', (event) => {
    // Only handle navigation requests (HTML page loads)
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(OFFLINE_URL);
        })
    );
});
