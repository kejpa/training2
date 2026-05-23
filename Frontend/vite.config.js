import {fileURLToPath, URL} from 'node:url'

import {defineConfig} from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import {VitePWA} from "vite-plugin-pwa";

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
    VitePWA
    ({
      registerType: 'prompt',
      includeAssets: ['favicon.ico', 'apple-touch-icon.png'],
      manifest: {
        name: 'Träningsdagbok',
        short_name: 'Träning',
        description: 'Logga och följ upp dina träningspass',
        theme_color: '#41b883',
        background_color: '#ffffff',
        display: 'standalone',
        scope: '/',
        start_url: '/',
        icons: [
          {src: 'Training192x192.png', sizes: '192x192', type: 'image/png'},
          {src: 'Training512x512.png', sizes: '512x512', type: 'image/png'},
          {src: 'Training512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable'}
        ]
      },
      workbox: {
        // Cache-strategi: NetworkFirst för API-anrop, CacheFirst för statiska assets
        runtimeCaching: [
          {
            urlPattern: /^https?:\/\/.*\/api\/.*/,
            handler: 'NetworkFirst',
            options: {cacheName: 'api-cache', networkTimeoutSeconds: 10}
          }
        ]
      }
    })
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true
      }
    }
  },

})
