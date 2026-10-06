const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const handlers = {};
const self = {
  location: { origin: 'https://sitesafety.site' },
  addEventListener: (name, fn) => { handlers[name] = fn; },
  skipWaiting: () => Promise.resolve(),
  clients: { claim: () => Promise.resolve() },
};
const cacheStore = new Map();
const caches = {
  match: async key => cacheStore.get(key),
  open: async () => ({
    addAll: async () => {},
    put: async (req, resp) => { cacheStore.set(req.url, resp); },
  }),
  keys: async () => [],
};
let fetches = 0;
const fetch = async req => {
  fetches++;
  return {ok: true, type: 'basic', clone(){return this;}};
};
vm.runInNewContext(fs.readFileSync('service-worker.js', 'utf8'), {
  self, caches, fetch, URL, Response: {error: () => ({})}, console,
});

function fire(path, attributes = {}) {
  let responded = false;
  const request = {
    method: 'GET', mode: 'navigate', redirect: 'manual',
    destination: 'document',
    url: 'https://sitesafety.site' + path, ...attributes,
  };
  handlers.fetch({
    request,
    respondWith: () => {responded = true;},
  });
  return responded;
}
for (const path of ['/', '/index.php', '/dashboard.php',
  '/login_admin.php', '/admin_branding.php', '/pdf.php?id=1']) {
  assert.equal(fire(path), false,
    'Browser must own all navigation/redirects: ' + path);
}
assert.equal(fire('/assets/safety-ui.css', {
  mode: 'no-cors', destination: 'style', redirect: 'manual'
}), false, 'Manual-redirect requests must never be intercepted');
for (const path of ['/uploads/branding/company-logo.jpg',
  '/api/suite-summary.php', '/pdf.php']) {
  assert.equal(fire(path, {
    mode: 'cors', destination: 'image', redirect: 'follow'
  }), false, 'Private assets must not be cached: ' + path);
}
assert.equal(fire('/assets/safety-ui.css', {
  mode: 'cors', destination: 'style', redirect: 'follow'
}), true, 'Public static stylesheet may be cached');
assert.equal(fetches, 0, 'No navigation should trigger synthetic fetching');
console.log('PASS: Native login redirects, confidential content and static shell cache.');
