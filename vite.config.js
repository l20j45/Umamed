import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa'; // 1. Importar el plugin

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
        // 2. Configurar el plugin PWA
        VitePWA({
            registerType: 'autoUpdate', // Actualiza el Service Worker automáticamente
            injectRegister: 'inline',
            manifest: {
                name: 'Mi Progreso Clínico', // Nombre completo de tu app
                short_name: 'Progreso',      // Nombre que aparecerá bajo el icono en el celular
                description: 'Seguimiento de progreso clínico y mediciones corporales',
                theme_color: '#1e3a8a',      // Color de la barra de estado (ej: Azul slate/indigo)
                background_color: '#ffffff', // Color de fondo al abrir la app
                display: 'standalone',       // Hace que se abra a pantalla completa sin barra de navegación del navegador
                orientation: 'portrait',     // Forzar orientación vertical si lo deseas
                icons: [
                    {
                        src: '/icons/icon-192x192.png',
                        sizes: '192x192',
                        type: 'image/png'
                    },
                    {
                        src: '/icons/icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png'
                    }
                ]
            }
        })
    ],
});
