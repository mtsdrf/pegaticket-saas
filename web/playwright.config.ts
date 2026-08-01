import { defineConfig, devices } from '@playwright/test'
import { fileURLToPath } from 'node:url'

const rootDir = fileURLToPath(new URL('.', import.meta.url))

const PORT = Number(process.env.PLAYWRIGHT_PORT ?? 4173)
const HOST = process.env.PLAYWRIGHT_HOST ?? '127.0.0.1'
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://${HOST}:${PORT}`
const useExternalBaseUrl = Boolean(process.env.PLAYWRIGHT_BASE_URL)
const webServerMode = process.env.PLAYWRIGHT_WEB_SERVER_MODE ?? 'preview'
// CI já roda `npm run build` como passo próprio antes deste (workflow de
// deploy) e depois reaproveita web/dist/ via rsync — rebuildar aqui de novo
// é redundante e arriscado: o Vite limpa dist/ (emptyOutDir) antes de
// reescrever, e se esse segundo build tiver qualquer instabilidade/timing
// diferente do primeiro, o dist/ que o rsync copia depois pode ficar
// vazio/parcial (incidente 2026-08-04: pasta publicada ficou vazia).
// PLAYWRIGHT_SKIP_BUILD=1 pula o rebuild e só sobe o preview do dist/ já
// existente — setado pelo workflow de deploy, não usado localmente (onde
// rebuildar a cada run continua sendo o comportamento seguro/esperado).
const skipBuild = process.env.PLAYWRIGHT_SKIP_BUILD === '1'
const webServerCommand =
  webServerMode === 'dev'
    ? `npm run dev -- --host ${HOST} --port ${PORT} --strictPort`
    : skipBuild
      ? `npm run preview -- --host ${HOST} --port ${PORT} --strictPort`
      : `npm run build && npm run preview -- --host ${HOST} --port ${PORT} --strictPort`

export default defineConfig({
  testDir: './e2e',
  timeout: 30_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? [['html', { open: 'never' }], ['list']] : 'list',
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
      },
    },
  ],
  webServer: useExternalBaseUrl
    ? undefined
    : {
        command: webServerCommand,
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        cwd: rootDir,
        timeout: 120_000,
      },
})
