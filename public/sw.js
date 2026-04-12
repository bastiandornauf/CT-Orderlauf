/* global self, caches, fetch */
const CACHE = 'ct-orderlauf-assets-v1';
const PRECACHE = [
  '/assets/css/app.css',
  '/assets/js/main.js',
  '/assets/js/api.js',
  '/assets/js/storage.js',
  '/assets/js/offline.js',
  '/assets/js/order-pages.js',
  '/assets/js/order-round.js',
  '/assets/js/supplier-logic.js',
  '/assets/js/email-generator.js',
  '/manifest.json',
  '/assets/icons/icon.svg',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))),
    ).then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) {
    return;
  }
  if (req.method !== 'GET') {
    return;
  }
  if (url.pathname.startsWith('/assets/')) {
    event.respondWith(
      caches.match(req).then((cached) => {
        const net = fetch(req).then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
          return res;
        });
        return cached || net;
      }),
    );
    return;
  }
});
