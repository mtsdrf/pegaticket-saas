import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      // `site.webmanifest` já existe em `public/` com a identidade visual oficial
      // (nome/ícones/cores da PegaTicket) e já é linkado manualmente em `index.html`.
      // `manifest: false` faz o plugin cuidar só do Service Worker (geração +
      // registro) sem gerar/injetar um segundo manifest — evita duplicar
      // `<link rel="manifest">` e mantém o arquivo existente como única fonte.
      manifest: false,
      devOptions: {
        enabled: true,
      },
      // Web Push (VAPID) real, roadmap Delivery Fase 4 — injeta o handler de
      // `push`/`notificationclick` (`public/push-sw.js`) dentro do Service
      // Worker gerado pelo plugin, sem trocar a estratégia (`generateSW`,
      // default) nem duplicar registro/manifest.
      workbox: {
        importScripts: ['push-sw.js'],
      },
    }),
  ],
  build: {
    // O bundle do ag-Grid Community continua relativamente grande mesmo após
    // code-splitting e registro modular; elevamos o aviso para reduzir ruído
    // e manter o alerta útil só para regressões acima do baseline atual.
    chunkSizeWarningLimit: 900,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return undefined
          }

          if (id.includes('ag-grid-community') || id.includes('ag-grid-react') || id.includes('@ag-grid-community')) {
            return 'vendor-ag-grid'
          }

          if (id.includes('chart.js') || id.includes('react-chartjs-2')) {
            return 'vendor-charts'
          }

          if (id.includes('@mui/icons-material')) {
            return 'vendor-mui-icons'
          }

          if (id.includes('@mui/material') || id.includes('@emotion/')) {
            return 'vendor-mui'
          }

          if (id.includes('react-router-dom')) {
            return 'vendor-router'
          }

          if (id.includes('react') || id.includes('react-dom')) {
            return 'vendor-react'
          }

          if (id.includes('axios')) {
            return 'vendor-http'
          }

          return 'vendor'
        },
      },
    },
  },
})
