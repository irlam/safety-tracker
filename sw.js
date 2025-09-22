/* Safety Tours PWA Service Worker */
const SW_VERSION = 'st-v1.0.0';
const STATIC_CACHE = `static-${SW_VERSION}`;
const RUNTIME_CACHE = `runtime-${SW_VERSION}`;

// What to precache (small + critical)
const PRECACHE_URLS = [
  '/',                  // if index.php redirects to dashboard.php, fine
  '/dashboard.php',
  '/offline.html',
  '/assets/img/logo.png',
  '/manifest.webmanifest'
];

// Filetype helpers
const isHTML = url => url.pathname.endsWith('.php') || url.pathname.endsWith('.html') || url.pathname === '/';
const isStatic = url =>
  url.pathname.startsWith('/assets/') ||
  url.pathname.endsWith('.css') ||
  url.pathname.endsWith('.js') ||
  url.pathname.endsWith('.png') ||
  url.pathname.endsWith('.jpg') ||
  url.pathname.endsWith('.jpeg') ||
  url.pathname.endsWith('.svg') ||
  url.pathname.endsWith('.webp') ||
  url.pathname.endsWith('.ico');

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then(c => c.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys
        .filter(k => ![STATIC_CACHE, RUNTIME_CACHE].includes(k))
        .map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// Network strategies:
// - HTML/PHP pages: network-first (fallback to cache → offline.html)
// - Static assets: cache-first (stale-while-revalidate)
// - PDF endpoint (/pdf.php?id=): cache-first (revalidate in background)
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Don’t mess with POST/PUT/DELETE, let them pass through.
  if (req.method !== 'GET') return;

  // PDF special-case
  if (url.pathname === '/pdf.php') {
    event.respondWith(cacheFirstSWRevalidate(req));
    return;
  }

  // Static assets
  if (isStatic(url)) {
    event.respondWith(cacheFirstSWRevalidate(req));
    return;
  }

  // HTML/PHP pages
  if (isHTML(url)) {
    event.respondWith(networkFirst(req));
    return;
  }

  // Everything else: try network, then cache
  event.respondWith(networkFirst(req));
});

async function cacheFirstSWRevalidate(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  const cached = await cache.match(request);
  const networkFetch = fetch(request).then(resp => {
    if (resp && resp.status === 200) cache.put(request, resp.clone());
    return resp;
  }).catch(() => cached);
  return cached || networkFetch;
}

async function networkFirst(request) {
  const cache = await caches.open(RUNTIME_CACHE);
  try {
    const resp = await fetch(request);
    if (resp && resp.status === 200) cache.put(request, resp.clone());
    return resp;
  } catch (e) {
    const cached = await cache.match(request);
    if (cached) return cached;
    if (request.mode === 'navigate' || (request.destination === 'document')) {
      return caches.match('/offline.html');
    }
    throw e;
  }
}
