/* TMZ service worker: fast repeat visits + an offline page.
 * Safe by design: it only caches static assets and images, never pages, API calls, admin, payments,
 * live rooms, uploads of video/audio or range requests. Bump VERSION to refresh the cache. */
const VERSION = 'tmz-sw-3';
const STATIC_CACHE = VERSION + '-static';
const OFFLINE_URL = '/TMZ%20PODCAST/offline.html';

self.addEventListener('install', function (event) {
  event.waitUntil(caches.open(STATIC_CACHE).then(function (c) { return c.add(OFFLINE_URL); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k.indexOf('tmz-sw-') === 0 && k !== STATIC_CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

function isStaticAsset(url, request) {
  if (url.pathname.indexOf('/TMZ%20PODCAST/assets/') === -1 && url.pathname.indexOf('/TMZ PODCAST/assets/') === -1) return false;
  return ['style', 'script', 'font', 'image'].indexOf(request.destination) !== -1;
}

self.addEventListener('fetch', function (event) {
  const request = event.request;
  if (request.method !== 'GET' || request.headers.has('range')) return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Pages: network first, friendly offline page if the network is down. Nothing is stored.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(function () { return caches.match(OFFLINE_URL); }));
    return;
  }

  // Versioned static assets: serve from cache instantly, refresh in the background.
  if (isStaticAsset(url, request)) {
    event.respondWith(
      caches.open(STATIC_CACHE).then(function (cache) {
        return cache.match(request).then(function (cached) {
          const network = fetch(request).then(function (response) {
            if (response && response.status === 200) cache.put(request, response.clone());
            return response;
          }).catch(function () { return cached; });
          return cached || network;
        });
      })
    );
  }
});
