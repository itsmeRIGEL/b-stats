import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

const projectRoot = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig(({ mode }) => {
    return {
        esbuild: {
            drop: mode === 'production' ? ['console', 'debugger'] : [],
        },
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                    compilerOptions: {
                        isCustomElement: (tag) => tag === 'marquee',
                    },
                },
            }),
            VitePWA({
                outDir: 'public',
                buildBase: '/build/',
                registerType: 'autoUpdate',
                manifest: {
                    name: 'Pickleball Booking System',
                    short_name: 'PickleBook',
                    description: 'Manage pickleball court bookings and schedules.',
                    theme_color: '#3b82f6',
                    background_color: '#ffffff',
                    display: 'fullscreen',
                    scope: '/',
                    start_url: '/',
                    icons: [
                        {
                            src: '/pwa-192x192.png',
                            sizes: '192x192',
                            type: 'image/png',
                        },
                        {
                            src: '/pwa-512x512.png',
                            sizes: '512x512',
                            type: 'image/png',
                        },
                        {
                            src: '/pwa-512x512.png',
                            sizes: '512x512',
                            type: 'image/png',
                            purpose: 'any maskable',
                        },
                    ],
                },
                workbox: {
                    navigateFallback: null,
                    globPatterns: ['**/*.{js,css,png,jpg,jpeg,svg,ico,woff,woff2}'],
                },
            }),
        ],
        resolve: {
            alias: {
                '@': path.resolve(projectRoot, './resources/js'),
            },
        },
        css: {
            postcss: {
                plugins: [tailwindcss, autoprefixer],
            },
        },
        server: {
            host: 'localhost',
            port: 5173,
            strictPort: true,
            hmr: {
                host: 'localhost',
                protocol: 'ws',
                port: 5173,
            },
        },
    };
});
