/* Dhaka Hotel service worker — public pages only.
   Never caches admin, API, checkout, cart, tracking or order data. */

'use strict';

const CACHE_VERSION = 'dhaka-hotel-v1';
const PRECACHE = [
  './',
  'menu.php',
  'offline.php',
  'assets/css/style.css',
  'assets/js/app.js',
  'assets/js/language.js',
  'assets/js/pwa.js',
  'assets/images/hero.svg',
  'assets/images/menu/placeholder.svg',
  'assets/images/icons/icon-192.png',
  'assets/images/icons/icon-512.png'
];

const NEVER_CACHE = [
  'admin/',
  'api/',
  'checkout.php',
  'cart.php',
  'order-success.php',
  'track-order.php',
  'manifest.webmanifest',
  'service-worker.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) =>
      Promise.all(
        PRECACHE.map((url) =>
          cache.add(new Request(url, { cache: 'reload' })).catch(() => null)
        )
      )
    ).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

function isNeverCached(url) {
  return NEVER_CACHE.some((part) => url.pathname.includes(part));
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin || isNeverCached(url)) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response && response.ok) {
            const copy = response.clone();
            caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy)).catch(() => {});
          }
          return response;
        })
        .catch(() =>
          caches.match(request).then((cached) => cached || caches.match('offline.php'))
        )
    );
    return;
  }

  if (url.pathname.startsWith(new URL('./assets/', self.location).pathname)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const network = fetch(request)
          .then((response) => {
            if (response && response.ok) {
              const copy = response.clone();
              caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy)).catch(() => {});
            }
            return response;
          })
          .catch(() => cached);
        return cached || network;
      })
    );
  }
});
