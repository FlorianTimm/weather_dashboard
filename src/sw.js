const CACHE_NAME = 'leitstand-v1';
const ASSETS = [
  'index.htm',
  'style.css',
  'script.js',
  'https://cdn.jsdelivr.net/npm/chart.js'
];

// Installieren und Assets cachen
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
  );
});

// Bei Netzwerkabfragen zuerst versuchen zu laden, sonst Cache nutzen
self.addEventListener('fetch', (e) => {
  if (e.request.url.includes('api.php')) {
    return; // API-Anfragen NIEMALS cachen, wir wollen immer Live-Daten!
  }
  e.respondWith(
    fetch(e.request).catch(() => caches.match(e.request))
  );
});