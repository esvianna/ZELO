const CACHE_NAME = 'zelo-cache-v182';
const ASSETS_TO_CACHE = [
    './',
    './index.html',
    './manifest.json',
    './assets/js/zelo-build.js?v=182',
    './assets/css/style-v5.css?v=182',
    './assets/js/i18n.js?v=182',
    './assets/js/app-v5.js?v=182',
    './assets/js/api-v5.js?v=182',
    './assets/js/map-manager.js?v=182',
    './assets/icons/icon-192x192.png',
    './assets/icons/icon-256x256.png',
    './assets/icons/icon-512x512.png',
    './assets/icons/icon-512x512-maskable.png',
    './images/favicon.ico',
    './images/favicon-96x96.png',
    './images/logo-zelo.png',
    './images/default-avatar.png',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600&display=swap'
];

// Install Event
self.addEventListener('install', (event) => {
    self.skipWaiting(); // Force activation
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Opened cache');
                return cache.addAll(ASSETS_TO_CACHE);
            })
    );
});

// Fetch Event
self.addEventListener('fetch', (event) => {
    // API WordPress: não interceptar — cookies de sessão e nonce devem ir direto ao browser.
    try {
        const url = new URL(event.request.url);
        if (url.pathname.indexOf('/wp-json/') !== -1) {
            return;
        }
    } catch (e) {
        /* ignore */
    }

    // Network First Strategy for EVERYTHING (ensures freshness)
    // If network fails (offline), fallback to cache

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Return response if it's invalid (but don't cache it)
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    // Check if it's a valid opaque response (e.g. from CDN/CORS)
                    // If type is opaque we can still cache it generally, but let's be simple:
                    // We just cache valid responses to update the cache
                }

                // Check if we received a valid response to cache
                // Basic means same-origin. CORS/opaque responses need careful handling if strict.
                // For simplicity, let's clone and cache ANY successful GET response

                if (event.request.method === 'GET') {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }

                return response;
            })
            .catch(() => {
                // Network failed, try cache
                return caches.match(event.request);
            })
    );
});

// Activate Event
self.addEventListener('activate', (event) => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

function zeloNotifyOpenClients(message) {
    return clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
        list.forEach((client) => {
            try {
                client.postMessage(message);
            } catch (e) {
                /* ignore */
            }
        });
        return list;
    });
}

// Web Push (Fase 3 — requer VAPID e subscribe no servidor)
self.addEventListener('push', (event) => {
    let payload = { title: 'Zelo', body: '' };
    try {
        if (event.data) {
            payload = event.data.json();
        }
    } catch (e) {
        payload.body = event.data ? event.data.text() : '';
    }
    const title = payload.title || 'Zelo';
    const pushUrl = payload.url || './';
    const options = {
        body: payload.body || '',
        icon: './images/logo-zelo.png',
        data: { url: pushUrl }
    };
    const clientMsg = {
        type: 'zelo:push',
        title,
        body: options.body,
        url: pushUrl
    };
    event.waitUntil(Promise.all([
        self.registration.showNotification(title, options),
        zeloNotifyOpenClients(clientMsg)
    ]));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || './';
    const clientMsg = { type: 'zelo:notificationclick', url };
    event.waitUntil(
        zeloNotifyOpenClients(clientMsg).then((list) => {
            for (const client of list) {
                if ('focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
