import { defineConfig } from 'vite';
import { resolve } from 'path';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    root: 'src',
    server: {
        proxy: {
            '/api.php': {
                target: 'http://weather.florian-timm.de/api.php',
                changeOrigin: true,
            }
        }
    },
    build: {
        outDir: resolve(__dirname, 'dist'),
        emptyOutDir: true,
    },
    plugins: [
        viteStaticCopy({
            targets: [
                {
                    src: '**/*.php',
                    dest: './'
                },
                {
                    src: '**/.htaccess',
                    dest: './',
                    globOptions: {
                        dot: true
                    }
                }

            ]
        }),
        VitePWA({
            strategies: 'generateSW', // Vite generiert die sw.js komplett selbst!
            registerType: 'autoUpdate', // Aktualisiert den Service Worker automatisch, wenn sich Code ändert
            injectRegister: 'script',   // Fügt den Registrierungs-Code automatisch in deine index.htm ein

            // Hier spiegeln wir dein exaktes Manifest wider:
            manifest: {
                name: 'Wetter & KWL Leitstand',
                short_name: 'Leitstand',
                description: 'Smart-Home Dashboard für Wetter, Helios KWL und Garten-Akustik',
                start_url: 'index.htm', // Geändert von index.php auf index.htm, da das deine Hauptseite ist
                display: 'standalone',
                background_color: '#f4f7f6',
                theme_color: '#3498db',
                orientation: 'portrait-primary',
                icons: [
                    {
                        src: 'https://cdn-icons-png.flaticon.com/512/3222/3222791.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable'
                    }
                ]
            },

            // Hier übersetzen wir deine alte sw.js Logik in moderne Workbox-Regeln:
            workbox: {
                // 1. Externe CDN-Ressourcen (wie Chart.js) mitcachen
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/cdn\.jsdelivr\.net\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'external-cdn-assets',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 365 // 1 Jahr cachen
                            },
                            cacheableResponse: {
                                statuses: [0, 200]
                            }
                        }
                    },
                    // 2. Deine API-Regel: Anfragen an api.php NIEMALS cachen, immer Netzwerk (NetworkOnly)
                    {
                        urlPattern: /.*api\.php.*/,
                        handler: 'NetworkOnly',
                    }
                ]
            }
        })
    ],
});