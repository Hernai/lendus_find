import { fileURLToPath, URL } from 'node:url'
import { readFileSync } from 'node:fs'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import { VitePWA } from 'vite-plugin-pwa'

// Versión del frontend expuesta a runtime como import.meta.env.VITE_APP_VERSION.
const pkg = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf-8'))
process.env.VITE_APP_VERSION = pkg.version

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
    VitePWA({
      registerType: 'autoUpdate',
      injectRegister: 'inline',
      // El manifest se sirve dinámicamente desde el backend por tenant.
      // Inyectamos el link en index.html, así que aquí lo dejamos vacío.
      manifest: false,
      workbox: {
        navigateFallback: '/index.html',
        // No interceptar rutas de API ni recursos firmados — la app es 100% online.
        navigateFallbackDenylist: [/^\/api\//, /^\/storage\//],
        globPatterns: ['**/*.{js,css,html,svg,png,ico,woff,woff2}'],
        // Monaco editor (admin-only, ~7 MB) no se precachea — se cachea bajo demanda.
        globIgnores: ['**/monaco-*.js', '**/ts.worker-*.js', '**/*.worker-*.js'],
        maximumFileSizeToCacheInBytes: 3 * 1024 * 1024,
        runtimeCaching: [
          {
            // API: siempre red (la lógica de negocio es server-authoritative).
            urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
            handler: 'NetworkOnly',
          },
          {
            // Signed URLs de S3/MinIO: no cachear (caducan).
            urlPattern: ({ url }) =>
              url.hostname.includes('amazonaws.com') || url.pathname.startsWith('/storage/'),
            handler: 'NetworkOnly',
          },
          {
            // Fuentes y assets del CDN propio: cache-first.
            urlPattern: ({ request }) =>
              ['style', 'script', 'image', 'font'].includes(request.destination),
            handler: 'CacheFirst',
            options: {
              cacheName: 'lendus-assets',
              expiration: { maxEntries: 80, maxAgeSeconds: 60 * 60 * 24 * 30 },
            },
          },
        ],
        skipWaiting: true,
        clientsClaim: true,
      },
      devOptions: {
        enabled: false,
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    allowedHosts: [
      'localhost',
      '127.0.0.1',
      '.ngrok-free.app',
      '.ngrok.io',
    ],
  },
  optimizeDeps: {
    exclude: ['monaco-editor'],
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          monaco: ['monaco-editor'],
        },
      },
    },
  },
})
