const CACHE='aurum-offline-v1';
self.addEventListener('install',e=>e.waitUntil(caches.open(CACHE).then(c=>c.add(new URL('offline.html',self.registration.scope).href))));
self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));
// Only a static offline notice is cached. Never cache or replay API requests, credentials, or trading orders.
self.addEventListener('fetch',e=>{const u=new URL(e.request.url);if(e.request.method!=='GET'||e.request.mode!=='navigate'||u.origin!==self.location.origin||u.pathname.includes('/api/'))return;e.respondWith(fetch(e.request).catch(()=>caches.open(CACHE).then(c=>c.match(new URL('offline.html',self.registration.scope).href)).then(r=>r||new Response('Aurum offline. Trading unavailable.',{status:503}))))});
