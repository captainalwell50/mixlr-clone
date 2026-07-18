const CACHE = 'church-live-shell-v1';
const SHELL = ['/', '/discover', '/offline.html'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()),
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
  const { request } = event;
  if (request.method !== 'GET') {
    return;
  }
  const url = new URL(request.url);
  // Never cache live media / API
  if (url.pathname.includes('/hls') || url.pathname.includes('/rtc') || url.pathname.startsWith('/api/')) {
    return;
  }
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/offline.html')),
    );
    return;
  }
  event.respondWith(
    caches.match(request).then((cached) => cached || fetch(request).catch(() => cached)),
  );
});
