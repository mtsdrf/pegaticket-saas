import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  build: {
    rollupOptions: {
      // Site multi-página nativo do Vite (sem SPA router): cada URL pública
      // vira um HTML próprio, com <title>/meta/canonical reais no build,
      // sem depender de JS pra corrigir <head> pós-navegação. Ver decisão em
      // docs/roadmap (página de preços dedicada, 2026-07-22).
      input: {
        main: fileURLToPath(new URL('./index.html', import.meta.url)),
        precos: fileURLToPath(new URL('./precos.html', import.meta.url)),
      },
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return undefined
          }

          if (id.includes('@mui/icons-material')) {
            return 'vendor-mui-icons'
          }

          if (id.includes('@mui/material') || id.includes('@emotion/')) {
            return 'vendor-mui'
          }

          if (id.includes('react') || id.includes('react-dom')) {
            return 'vendor-react'
          }

          return 'vendor'
        },
      },
    },
  },
})
