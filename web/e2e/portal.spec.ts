import { expect, test } from '@playwright/test'

test.describe('Portal do cliente final', () => {
  test('realiza login por OTP, mostra os pedidos vinculados e permite sair da conta', async ({ page }) => {
    await page.route('**/api/v1/portal/auth/request-otp*', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Código enviado.',
          data: null,
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/portal/auth/verify-otp*', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Código confirmado.',
          data: {
            access_token: 'portal-token-qa',
            token_type: 'bearer',
            expires_in: 3600,
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/portal/me*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            uuid: 'portal-customer-qa-1',
            name: 'Maria Cliente',
            email: 'maria@cliente.com',
            linked_stores: [
              {
                tenant_name: 'Loja QA',
                client_name: 'Maria Cliente',
                confirmed_at: '2026-07-28T16:00:00Z',
              },
            ],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/portal/orders*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: [
            {
              uuid: 'portal-order-1',
              tenant_name: 'Loja QA',
              tenant_slug: 'loja-qa',
              is_paid: true,
              is_delivered: false,
              is_out_for_delivery: false,
              status: 'confirmed',
              total_amount: '79.90',
              expected_delivery_date: '2026-07-28',
              is_cancelled: false,
              created_at: '2026-07-28T15:30:00Z',
            },
          ],
          meta: {},
        }),
      })
    })

    await page.goto('/portal/entrar')

    await expect(page.getByText('Minha conta PegaTicket')).toBeVisible()
    await page.getByRole('textbox', { name: 'E-mail' }).fill('maria@cliente.com')
    await page.getByRole('button', { name: 'Receber código por e-mail' }).click()

    await expect(page.getByText('Enviamos um código de 6 dígitos para maria@cliente.com.')).toBeVisible()
    await page.getByRole('textbox', { name: 'Código de 6 dígitos' }).fill('123456')
    await page.getByRole('button', { name: 'Entrar' }).click()

    await expect(page).toHaveURL(/\/portal\/pedidos$/)
    await expect(page.getByRole('tab', { name: 'Meus pedidos' })).toBeVisible()
    await expect(page.getByText('Loja QA')).toBeVisible()
    await expect(page.getByText('R$ 79,90')).toBeVisible()
    await expect(page.getByText('Pago - aguardando entrega').or(page.getByText('Pago — aguardando entrega'))).toBeVisible()
    await expect(page.getByRole('button', { name: 'Pedir de novo' })).toBeVisible()

    await page.getByRole('button', { name: 'Sair da conta' }).click()

    await expect(page).toHaveURL(/\/portal\/entrar$/)
    await expect(page.getByText('Minha conta PegaTicket')).toBeVisible()
  })
})
