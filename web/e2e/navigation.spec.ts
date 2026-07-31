import { expect, test } from '@playwright/test'
import { mockAuthenticatedShell } from './support/api'

test.describe('Navegação e responsividade', () => {
  test('redireciona rota inexistente autenticada para a raiz', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: false,
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard'],
    })

    await page.goto('/rota-que-nao-existe')

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
  })

  test('mantém shell operacional em viewport mobile com menu lateral e menu da conta acessíveis', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })

    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: false,
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard'],
      userName: 'Operador Mobile',
      userEmail: 'mobile.qa@pegaticket.com',
    })

    await page.goto('/')

    await expect(page.getByRole('button', { name: 'Abrir menu', exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Abrir menu da conta' })).toBeVisible()

    await page.getByRole('button', { name: 'Abrir menu', exact: true }).click()
    await expect(page.getByRole('link', { name: 'Configurações' })).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.getByRole('link', { name: 'Configurações' })).toHaveCount(0)

    await page.getByRole('button', { name: 'Abrir menu da conta' }).click()
    await expect(page.getByText('Operador Mobile')).toBeVisible()
    await expect(page.getByText('mobile.qa@pegaticket.com')).toBeVisible()
  })
})
