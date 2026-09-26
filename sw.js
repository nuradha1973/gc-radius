// GC Radius - Service Worker (PWA)
const CACHE_NAME = 'gc-radius-v4';
const urlsToCache = [
    '/gc-radius/',
    '/gc-radius/index.php',
    '/gc-radius/login.php',
    '/gc-radius/assets/css/style.css',
    '/gc-radius/assets/logo.png',
    '/gc-radius/manifest.json'
];

// Install: cache app shell
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(urlsToCache);
        })
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Fetch: network-first with cache fallback
self.addEventListener('fetch', event => {
    // Skip non-GET + API/AJAX requests
    if (event.request.method !== 'GET') return;
    if (event.request.url.includes('/api/') || event.request.url.includes('admin-ajax')) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Cache successful responses
                if (response && response.status === 200 && response.type === 'basic') {
                    const cloned = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, cloned));
                }
                return response;
            })
            .catch(() => {
                // Offline fallback
                return caches.match(event.request);
            })
    );
});
