import { expect, test, type Page } from '@playwright/test'

const smokeEmail = process.env.SMOKE_LOGIN_EMAIL
const smokePassword = process.env.SMOKE_LOGIN_PASSWORD

async function confirmTenantIfNeeded(page: Page) {
  const tenantDialogTitle = page.getByRole('heading', { name: 'Selecione a empresa' })
  const shouldConfirmTenant = await tenantDialogTitle.isVisible().catch(() => false)

  if (!shouldConfirmTenant) return

  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(tenantDialogTitle).toHaveCount(0)
}

async function expectAuthenticatedShell(page: Page) {
  const accountButton = page.getByRole('button', { name: 'Abrir menu da conta' })
  const loginError = page.getByRole('alert')

  await expect
    .poll(
      async () => {
        if (await accountButton.isVisible().catch(() => false)) return 'authenticated'
        if (await page.getByRole('heading', { name: 'Selecione a empresa' }).isVisible().catch(() => false)) return 'tenant-selection'
        if (await loginError.isVisible().catch(() => false)) return `login-error:${(await loginError.textContent()) ?? ''}`
        return `path:${new URL(page.url()).pathname}`
      },
      {
        timeout: 15000,
        message: 'Esperava shell autenticado, seleção de empresa ou erro visível de login.',
      },
    )
    .toMatch(/^(authenticated|tenant-selection)$/)

  await confirmTenantIfNeeded(page)
  await expect(page).not.toHaveURL(/\/login$/)
  await expect(accountButton).toBeVisible()
}

test.describe('Smoke pós-deploy @smoke', () => {
  test('carrega a tela de login e não deixa rota inexistente cair em tela morta', async ({ page }) => {
    await page.goto('/rota-inexistente-pos-deploy')

    await expect(page).toHaveURL(/\/login$/)
    await expect(page.getByRole('button', { name: 'Entrar no painel' })).toBeVisible()
  })

  test('autentica com credenciais reais quando informadas no ambiente', async ({ page }) => {
    test.skip(!smokeEmail || !smokePassword, 'Defina SMOKE_LOGIN_EMAIL e SMOKE_LOGIN_PASSWORD para o smoke autenticado.')

    await page.goto('/login')
    await page.getByRole('textbox', { name: 'E-mail' }).fill(smokeEmail ?? '')
    await page.locator('input[name="password"]').fill(smokePassword ?? '')
    await page.getByRole('button', { name: 'Entrar no painel' }).click()

    await expectAuthenticatedShell(page)

    await page.goto('/')
    await expectAuthenticatedShell(page)
  })
})
