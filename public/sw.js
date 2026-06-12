/* global self, caches, fetch */
const CACHE = 'ct-orderlauf-assets-v35';
const PRECACHE = [
  '/assets/css/app.css',
  '/assets/js/main.js',
  '/assets/js/toast.js',
  '/assets/js/api.js',
  '/assets/js/storage.js',
  '/assets/js/inventory-storage.js',
  '/assets/js/inventory-round.js',
  '/assets/js/inventory-csv.js',
  '/assets/js/inventory-pages.js',
  '/assets/js/offline.js',
  '/assets/js/order-pages.js',
  '/assets/js/order-round.js',
  '/assets/js/supplier-logic.js',
  '/assets/js/email-generator.js',
  '/manifest.json',
  '/assets/icons/icon.svg',
];

const APP_SHELL_ROUTES = [
  '/order/round',
  '/order/review',
  '/order/output',
  '/inventory',
  '/inventory/round',
  '/inventory/finalize',
  '/items/pending',
];
// Bei sehr schwachem Netz (z. B. Kühlhaus) nach kurzer Wartezeit aus dem Cache
// antworten und die Netzantwort im Hintergrund nachziehen.
const NETWORK_TIMEOUT_MS = 2500;

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

function fetchAndCache(req) {
  return fetch(req).then((res) => {
    if (res && res.status === 200 && res.type === 'basic') {
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
    }
    return res;
  });
}

function networkFirstWithTimeout(req, timeoutMs = NETWORK_TIMEOUT_MS) {
  return new Promise((resolve) => {
    let settled = false;
    const finish = (val) => {
      if (!settled && val) {
        settled = true;
        resolve(val);
      }
    };

    const timer = setTimeout(() => {
      caches.match(req).then((cached) => finish(cached));
    }, timeoutMs);

    fetchAndCache(req)
      .then((res) => {
        clearTimeout(timer);
        if (settled) return;
        settled = true;
        resolve(res);
      })
      .catch(() => {
        clearTimeout(timer);
        if (settled) return;
        caches.match(req).then((cached) => {
          settled = true;
          resolve(cached || Response.error());
        });
      });
  });
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) {
    return;
  }
  if (req.method !== 'GET') {
    return;
  }
  if (url.pathname.startsWith('/assets/') || url.pathname === '/manifest.json') {
    event.respondWith(networkFirstWithTimeout(req));
    return;
  }
  if (APP_SHELL_ROUTES.includes(url.pathname)) {
    event.respondWith(networkFirstWithTimeout(req));
    return;
  }
});
