const CACHE_NAME = 'zelo-cache-v8';
const ASSETS_TO_CACHE = [
    './',
    './index.html',
    './manifest.json',
    './assets/css/style.css',
    './assets/js/app.js',
    './assets/js/api.js',
    './assets/js/map.js',
    './images/logo-zelo.png',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600&display=swap'
];

// Install Event
self.addEventListener('install', (event) => {
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
