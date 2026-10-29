import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwind from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    vue(),
    tailwind(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['apple-touch-icon.png'],
      manifest: {
        name: 'Local BTC Wallet',
        short_name: 'BTC Wallet',
        description: 'Self-custody Bitcoin wallet that runs entirely in your browser.',
        theme_color: '#f7931a',
        background_color: '#0b0a08',
        display: 'standalone',
        orientation: 'portrait',
        start_url: '/',
        scope: '/',
        icons: [
          { src: 'icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: 'icon-512.png', sizes: '512x512', type: 'image/png' },
          { src: 'icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      // cache the app shell only; balance/fee/broadcast calls always hit the network
      workbox: { globPatterns: ['**/*.{js,css,html,png,wasm}'], navigateFallback: '/index.html', maximumFileSizeToCacheInBytes: 5 * 1024 * 1024 },
    }),
  ],
})
