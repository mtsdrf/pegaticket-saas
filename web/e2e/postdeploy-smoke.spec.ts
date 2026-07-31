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

    await confirmTenantIfNeeded(page)

    await expect(page).not.toHaveURL(/\/login$/)
    await expect(page.getByRole('button', { name: 'Abrir menu da conta' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()

    await page.goto('/')
    await expect(page.getByRole('button', { name: 'Abrir menu da conta' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
  })
})
