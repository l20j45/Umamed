import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { activarPushSiYaAutorizado } from './lib/push';

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    import('virtual:pwa-register')
        .then(({ registerSW }) => {
            registerSW({ immediate: true });
        })
        .catch((error) => {
            console.error('Error al cargar el Service Worker de la PWA:', error);
        });
}

const appName = import.meta.env.VITE_APP_NAME || 'Umamed | Bariatría y Nutrición';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);

        if (
            import.meta.env.PROD &&
            (props as { initialPage: { props: { auth: { user: unknown } } } }).initialPage
                .props.auth.user
        ) {
            // Si la PWA ya tiene permiso de notificaciones de una visita
            // anterior, re-registra la suscripción para que siga viva.
            void activarPushSiYaAutorizado();
        }
    },
    progress: {
        color: '#4B5563',
    },
});
