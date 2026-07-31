import { expect, test } from '@playwright/test'
import { mockApiError } from './support/api'

test.describe('Autenticação web', () => {
  test('redireciona usuário não autenticado para /login ao abrir a raiz', async ({ page }) => {
    await page.goto('/')

    await expect(page).toHaveURL(/\/login$/)
    await expect(page.getByRole('button', { name: 'Entrar no painel' })).toBeVisible()
  })

  test('exibe mensagem amigável quando o login falha', async ({ page }) => {
    await mockApiError(page, {
      method: 'POST',
      path: '/auth/login',
      status: 401,
      code: 'INVALID_CREDENTIALS',
      message: 'Credenciais inválidas.',
      errors: {
        email: ['Revise o e-mail informado.'],
      },
    })

    await page.goto('/login')
    await page.getByRole('textbox', { name: 'E-mail' }).fill('falha@maskats.com')
    await page.locator('input[name="password"]').fill('senha-invalida')
    await page.getByRole('button', { name: 'Entrar no painel' }).click()

    await expect(page.getByText('E-mail ou senha inválidos. Revise os dados e tente novamente.')).toBeVisible()
    await expect(page.getByText('Revise o e-mail informado.')).toBeVisible()
  })
})
