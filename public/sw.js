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

// Notificação do servidor (Web Push): { titulo, corpo, url, tag }.
self.addEventListener('push', (evento) => {
    let dados = {};

    try {
        dados = evento.data ? evento.data.json() : {};
    } catch {
        dados = { corpo: evento.data ? evento.data.text() : '' };
    }

    evento.waitUntil(
        self.registration.showNotification(dados.titulo || 'PequenaVia', {
            body: dados.corpo || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/badge-96.png',
            tag: dados.tag,
            lang: 'pt-BR',
            data: { url: dados.url || '/inicio' },
        }),
    );
});

// Tocar na notificação abre a página dela: reaproveita uma janela do app já aberta, se houver.
self.addEventListener('notificationclick', (evento) => {
    evento.notification.close();
    const destino = new URL(
        evento.notification.data?.url || '/inicio',
        self.location.origin,
    ).href;

    evento.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((janelas) => {
                const aberta = janelas.find(
                    (janela) =>
                        new URL(janela.url).origin === self.location.origin,
                );

                if (aberta) {
                    return aberta.focus().then(() => aberta.navigate(destino));
                }

                return self.clients.openWindow(destino);
            }),
    );
});
