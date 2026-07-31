import { defineConfig, devices } from '@playwright/test'
import { fileURLToPath } from 'node:url'

const rootDir = fileURLToPath(new URL('.', import.meta.url))

const PORT = Number(process.env.PLAYWRIGHT_PORT ?? 4173)
const HOST = process.env.PLAYWRIGHT_HOST ?? '127.0.0.1'
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://${HOST}:${PORT}`
const useExternalBaseUrl = Boolean(process.env.PLAYWRIGHT_BASE_URL)
const webServerMode = process.env.PLAYWRIGHT_WEB_SERVER_MODE ?? 'preview'
const webServerCommand =
  webServerMode === 'dev'
    ? `npm run dev -- --host ${HOST} --port ${PORT} --strictPort`
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
