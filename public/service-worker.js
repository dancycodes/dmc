/**
 * DancyMeals Service Worker
 *
 * Handles offline fallback and push notifications.
 * The offline page is pre-cached during installation.
 * Push notification handlers added by F-014.
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

/**
 * Push event: handle incoming push notifications.
 * Parses the push payload and displays a notification
 * with title, body, icon, and action URL from the server.
 */
self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    var data;
    try {
        data = event.data.json();
    } catch (e) {
        data = {
            title: 'DancyMeals',
            body: event.data.text()
        };
    }

    var title = data.title || 'DancyMeals';
    var options = {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192x192.png',
        badge: '/icons/icon-192x192.png',
        data: data.data || {},
        tag: data.tag || undefined,
        renotify: !!data.tag,
        requireInteraction: false,
        vibrate: [200, 100, 200]
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

/**
 * Notification click event: open or focus the relevant page.
 * Reads the action URL from the notification data payload.
 * If a matching window is already open, focuses it.
 * Otherwise, opens a new window to the action URL.
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    var actionUrl = '/';
    if (event.notification.data && event.notification.data.url) {
        actionUrl = event.notification.data.url;
    }

    // Ensure we have an absolute URL
    if (actionUrl.startsWith('/')) {
        actionUrl = self.location.origin + actionUrl;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // Check if any existing window matches the URL
            for (var i = 0; i < windowClients.length; i++) {
                var client = windowClients[i];
                if (client.url === actionUrl && 'focus' in client) {
                    return client.focus();
                }
            }
            // If no matching window, try to focus any existing window and navigate
            for (var j = 0; j < windowClients.length; j++) {
                var existingClient = windowClients[j];
                if ('focus' in existingClient) {
                    return existingClient.focus().then(function(focusedClient) {
                        if (focusedClient && 'navigate' in focusedClient) {
                            return focusedClient.navigate(actionUrl);
                        }
                    });
                }
            }
            // No existing windows, open a new one
            if (clients.openWindow) {
                return clients.openWindow(actionUrl);
            }
        })
    );
});
