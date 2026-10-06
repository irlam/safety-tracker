// Privacy-safe PWA shell. Never persist signed-in PHP pages or reports
// in a shared service-worker cache.
const CACHE = 'site-safety-shell-v2';
const CORE = [
  '/offline.html', '/manifest.webmanifest', '/pwa-install.js',
  '/assets/safety-ui.css', '/assets/img/safety-brand.svg'
];
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache =>
    cache.addAll(CORE)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
  event.waitUntil(Promise.all([
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key !== CACHE).map(key => caches.delete(key)))),
    self.clients.claim()
  ]));
});
self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) return;
  const url = new URL(request.url);
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(async () =>
      (await caches.match('/offline.html')) || Response.error()));
    return;
  }
  // Never cache any private PHP, uploaded evidence, PDFs, admin settings or API.
  if (url.pathname.endsWith('.php') || url.pathname.startsWith('/api/')
      || url.pathname.startsWith('/uploads/') || url.pathname.endsWith('.pdf')) return;
  if (!CORE.includes(url.pathname)) return;
  event.respondWith(caches.match(request).then(cached =>
    cached || fetch(request).then(response => {
      if (response.ok && response.type === 'basic') {
        const copy = response.clone();
        caches.open(CACHE).then(cache => cache.put(request, copy));
      }
      return response;
    })));
});
