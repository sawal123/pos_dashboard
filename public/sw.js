/**
 * NexaPOS Progressive Web App Service Worker
 * Version: 1.0.0
 */

const CACHE_NAME = 'nexapos-pwa-v1.0.0';

// Essential assets to cache immediately upon install
const PRECACHE_ASSETS = [
    '/offline',
    '/manifest.webmanifest',
    '/favicon.svg',
    '/apple-touch-icon.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable-192.png',
    '/icons/icon-maskable-512.png',
    '/icons/shortcut-pos.png',
    '/icons/shortcut-dash.png',
    '/icons/shortcut-products.png',
    '/icons/shortcut-trans.png'
];

// Install Event - Pre-cache core shell
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS).catch((err) => {
                console.warn('[SW] Pre-caching warning:', err);
            });
        }).then(() => {
            return self.skipWaiting();
        })
    );
});

// Activate Event - Clean up outdated caches & claim clients
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// Fetch Event - Dynamic caching strategy
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // 1. Only intercept GET requests (never cache mutations like POST, PUT, DELETE)
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // 2. Bypass Livewire, Sanctum, and API mutations
    if (
        url.pathname.startsWith('/livewire/') ||
        url.pathname.startsWith('/sanctum/') ||
        url.pathname.startsWith('/api/sync/') ||
        url.pathname.includes('/auth/google')
    ) {
        return;
    }

    // 3. Navigation Requests (HTML pages like /dashboard, /pos, /products)
    // Strategy: Network-First with Cache Fallback -> Offline Page Fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    // If network responds successfully, clone and cache for offline viewing
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                })
                .catch(async () => {
                    // Network failed (offline). First try to return cached version of this exact page
                    const cachedResponse = await caches.match(request);
                    if (cachedResponse) {
                        return cachedResponse;
                    }

                    // If not visited yet, fall back to offline page
                    const offlineFallback = await caches.match('/offline');
                    if (offlineFallback) {
                        return offlineFallback;
                    }

                    return new Response(
                        '<div style="font-family:sans-serif;text-align:center;padding:40px;">' +
                        '<h1>Anda sedang offline</h1><p>Koneksi terputus. Silakan sambungkan kembali ke jaringan.</p>' +
                        '</div>',
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                    );
                })
        );
        return;
    }

    // 4. Static Assets (Vite build assets, images, icons, fonts)
    // Strategy: Stale-While-Revalidate (serve fast from cache, update in background)
    const isStaticAsset = (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.jpeg') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.ico') ||
        url.pathname.endsWith('.woff2') ||
        url.pathname.endsWith('.woff') ||
        url.pathname.endsWith('.ttf') ||
        url.hostname.includes('fonts.googleapis.com') ||
        url.hostname.includes('fonts.gstatic.com') ||
        url.hostname.includes('cdn.tailwindcss.com') ||
        url.hostname.includes('unpkg.com') ||
        url.hostname.includes('cdn.jsdelivr.net')
    );

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                const fetchPromise = fetch(request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return networkResponse;
                }).catch(() => {
                    // Silently ignore static fetch failures when offline
                });

                return cachedResponse || fetchPromise;
            })
        );
        return;
    }

    // Default: try cache first, then network
    event.respondWith(
        caches.match(request).then((response) => {
            return response || fetch(request);
        })
    );
});

// Listen for messages from web pages
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
