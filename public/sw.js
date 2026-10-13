// Service worker do PequenaVia instalado como aplicativo.
// Não guarda páginas do app em cache: os dados são da secretaria e mudam o tempo todo.
// Só mostra uma página de "sem internet" quando a navegação falha por falta de conexão.
const CACHE = 'pequenavia-offline-v1';
const OFFLINE = '/offline.html';

self.addEventListener('install', (evento) => {
    evento.waitUntil(caches.open(CACHE).then((cache) => cache.add(OFFLINE)));
    self.skipWaiting();
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches
            .keys()
            .then((nomes) =>
                Promise.all(
                    nomes
                        .filter((nome) => nome !== CACHE)
                        .map((nome) => caches.delete(nome)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evento) => {
    if (evento.request.mode !== 'navigate') {
        return;
    }

    evento.respondWith(
        fetch(evento.request).catch(() => caches.match(OFFLINE)),
    );
});
