/* sw.js â€” service worker PADI-PJOK: shell aset di-cache, halaman network-first. */
var CACHE = 'padi-v6';
var ASET = ['aset/app.css', 'aset/app.js', 'aset/offline.html', 'aset/icon-192.png'];

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(ASET); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) { return k === CACHE ? null : caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Halaman: network-first, offline fallback.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(function () { return caches.match('aset/offline.html'); }));
    return;
  }

  // Aset: cache-first.
  if (url.pathname.indexOf('/aset/') !== -1) {
    e.respondWith(caches.match(req).then(function (hit) {
      if (hit) return hit;
      return fetch(req).then(function (res) {
        var salinan = res.clone();
        caches.open(CACHE).then(function (c) { c.put(req, salinan); });
        return res;
      });
    }));
  }
});
