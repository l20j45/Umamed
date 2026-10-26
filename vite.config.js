import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa'; // 1. Importar el plugin
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            // Necesario para que los imports "@/Components/..." y "@/Layouts/..." funcionen.
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
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
                name: 'Umamed · Seguimiento clínico',
                short_name: 'Umamed',
                description: 'Seguimiento de progreso clínico y mediciones corporales',
                theme_color: '#243830',
                background_color: '#F7F5F0',
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
