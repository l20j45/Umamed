/*
 * Manejo de notificaciones Web Push para el Service Worker de Umamed.
 *
 * Este archivo se importa dentro del Service Worker generado por
 * vite-plugin-pwa (ver vite.config.js: workbox.importScripts). Los mensajes
 * llegan cifrados; aquí sólo se leen los datos que manda Laravel
 * (NotificationChannels\WebPush), p. ej.:
 *
 * { title: 'Recordatorio de cita', body: '...', data: { url: '/mis-citas' } }
 */

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Umamed', {
            body: payload.body || '',
            icon: payload.icon || '/icons/icon-192x192.png',
            badge: payload.badge || '/icons/icon-192x192.png',
            lang: payload.lang || 'es',
            data: payload.data || {},
            requireInteraction: payload.requireInteraction || false,
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            // Si hay una ventana de la app abierta, enfócala y navega al destino.
            for (const client of clients) {
                if (new URL(client.url).origin === self.location.origin) {
                    client.navigate(url).catch(() => {});
                    return client.focus();
                }
            }

            return self.clients.openWindow(url);
        }),
    );
});
