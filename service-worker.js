// Privacy-safe PWA shell. Never persist signed-in PHP pages or reports
// in a shared service-worker cache.
const CACHE = 'site-safety-shell-v3';
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
  // Login, home and dashboard navigation can redirect (e.g. / -> dashboard
  // -> login). The browser's navigation request may have redirect=manual;
  // returning a redirected response via respondWith() causes a network error.
  // Let the browser own ALL navigations and their redirect/cookie semantics.
  // This also avoids caching private HTML pages.
  if (request.mode === 'navigate' || request.destination === 'document'
      || request.redirect !== 'follow') return;
  const url = new URL(request.url);
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
