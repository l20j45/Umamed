/**
 * Web Push del lado del navegador.
 *
 * Flujo: el Service Worker de la PWA obtiene la suscripción del PushManager
 * con la llave pública VAPID (expuesta por el backend en app.blade.php) y la
 * registra en Laravel con `push.suscribir`. El recordatorio de la cita llega
 * como notificación aunque la app esté cerrada.
 */

const URL_SEGURO = typeof window !== 'undefined' && window.isSecureContext;
const COMPATIBLE =
    'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

export function pushDisponible(): boolean {
    return URL_SEGURO && COMPATIBLE && Boolean(leaseVapidKey());
}

function leaseVapidKey(): string {
    return document.querySelector('meta[name="vapid-public-key"]')?.getAttribute('content') ?? '';
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function registrarEnServidor(suscripcion: PushSubscription): Promise<boolean> {
    const respuesta = await fetch(route('push.suscribir'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(suscripcion.toJSON()),
    });

    return respuesta.ok;
}

function vapidKeyBytes(vapidKey: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (vapidKey.length % 4)) % 4);
    const base64 = (vapidKey + padding).replace(/-/g, '+').replace(/_/g, '/');

    const raw = window.atob(base64);

    // Un ArrayBuffer explícito: la firma de PushManager.subscribe exige un
    // BufferSource y TS rechaza Uint8Array<ArrayBufferLike> genérico.
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let i = 0; i < raw.length; i++) {
        bytes[i] = raw.charCodeAt(i);
    }

    return bytes;
}

async function registro(): Promise<ServiceWorkerRegistration> {
    const existente = await navigator.serviceWorker.getRegistration();

    return existente ?? navigator.serviceWorker.register('/build/sw.js');
}

/**
 * Pide permiso, suscribe el navegador y registra la suscripción en el
 * servidor. Devuelve el estado resultante del permiso.
 */
export async function activarPush(): Promise<NotificationPermission | 'error'> {
    if (!pushDisponible()) return 'error';

    try {
        const permiso = await Notification.requestPermission();

        if (permiso !== 'granted') {
            return permiso;
        }

        const registration = await registro();

        const suscripcion = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: vapidKeyBytes(leaseVapidKey()),
        });

        return (await registrarEnServidor(suscripcion)) ? permiso : 'error';
    } catch (error) {
        console.error('No se pudo activar las notificaciones push:', error);

        return 'error';
    }
}

/** Cancela la suscripción local y la borra del servidor. */
export async function desactivarPush(): Promise<void> {
    if (!COMPATIBLE) return;

    const registration = await navigator.serviceWorker.getRegistration();

    const suscripcion = await registration?.pushManager.getSubscription();

    if (suscripcion) {
        try {
            await fetch(route('push.eliminar'), {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ endpoint: suscripcion.endpoint }),
            });
        } finally {
            await suscripcion.unsubscribe();
        }
    }
}

/** Activa el push solo si el permiso ya está concedido (sin preguntar). */
export async function activarPushSiYaAutorizado(): Promise<void> {
    if (!pushDisponible() || Notification.permission !== 'granted') return;

    try {
        const registration = await registro();

        const suscripcion = await registration.pushManager.getSubscription();

        if (!suscripcion) return; // Sin permiso guardado, no interroga al usuario.

        if (!(await registrarEnServidor(suscripcion))) {
            console.warn('El servidor rechazó la suscripción push.');
        }
    } catch (error) {
        console.error('No se pudo registrar la suscripción push:', error);
    }
}
