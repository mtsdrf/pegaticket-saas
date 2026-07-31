import { expect, test } from '@playwright/test'
import { makeOrder, mockAuthenticatedShell } from './support/api'

async function mockPdvReadSide(page: Parameters<typeof test>[0]['page']) {
  await page.route('**/api/v1/pdv/cash-sessions/current*', async (route) => {
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
          uuid: 'cash-session-1',
          status: 'open',
          opened_at: '2026-07-28T12:00:00Z',
          opening_amount: 100,
          closed_at: null,
          closing_amount_declared: null,
          closing_amount_expected: null,
          difference: null,
          cash_register: {
            uuid: 'cash-register-1',
            name: 'Caixa principal',
            is_active: true,
          },
          movements: [],
        },
        meta: {},
      }),
    })
  })

  await page.route('**/api/v1/pdv/offline-snapshot*', async (route) => {
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
          generated_at: '2026-07-28T12:05:00Z',
          offline_payment_methods: ['cash'],
          blocked_payment_methods: ['pix', 'credit', 'debit'],
          cash_session: {
            uuid: 'cash-session-1',
            status: 'open',
            opened_at: '2026-07-28T12:00:00Z',
            opening_amount: 100,
            closed_at: null,
            closing_amount_declared: null,
            closing_amount_expected: null,
            difference: null,
            cash_register: {
              uuid: 'cash-register-1',
              name: 'Caixa principal',
              is_active: true,
            },
            movements: [],
          },
          products: [
            {
              uuid: 'pdv-product-1',
              name: 'Refrigerante Lata',
              sku: 'REFRI-001',
              barcode: '7890001112223',
              unit: 'un',
              price: 15.5,
              stock_quantity: 30,
              updated_at: '2026-07-28T12:04:00Z',
            },
          ],
        },
        meta: {},
      }),
    })
  })

  await page.route('**/api/v1/products*', async (route) => {
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
            uuid: 'pdv-product-1',
            name: 'Refrigerante Lata',
            sku: 'REFRI-001',
            barcode: '7890001112223',
            brand: null,
            ncm: null,
            cest: null,
            origin: null,
            default_cfop: null,
            csosn_cst: null,
            unit: 'un',
            price: 15.5,
            description: null,
            image_url: null,
            is_available: true,
            stock_quantity: 30,
            surcharge_rate: null,
            is_lot_controlled: false,
            is_expiry_controlled: false,
            is_serial_controlled: false,
            min_stock: null,
            max_stock: null,
            reorder_point: null,
            reorder_qty: null,
            product_type: null,
            created_at: '2026-07-28T12:00:00Z',
          },
        ],
        meta: {
          pagination: {
            current_page: 1,
            per_page: 8,
            total: 1,
            last_page: 1,
          },
        },
      }),
    })
  })
}

test.describe('PDV', () => {
  test('realiza uma venda no PDV com caixa aberto e mostra o recibo', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['pdv:read', 'pdv:open', 'pdv:sell', 'products:read'],
      tenantFunctionalities: ['pdv', 'products'],
    })

    await mockPdvReadSide(page)

    await page.route('**/api/v1/pdv/sales', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      const payload = JSON.parse(route.request().postData() ?? '{}')
      expect(payload).toMatchObject({
        cash_session_uuid: 'cash-session-1',
        items: [
          {
            product_uuid: 'pdv-product-1',
            quantity: 2,
            unit_price: 15.5,
          },
        ],
        payments: [
          {
            method: 'cash',
            amount: 31,
          },
        ],
      })

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: makeOrder({
            uuid: 'pdv-order-1',
            codigo: '3001',
            total_amount: 31,
            is_paid: true,
            paid_amount: 31,
            paid_at: '2026-07-28T12:10:00Z',
            is_delivered: true,
            delivered_at: '2026-07-28T12:10:00Z',
            origin: 'pdv',
            client: undefined,
            items: [
              {
                uuid: 'pdv-item-1',
                product: {
                  uuid: 'pdv-product-1',
                  name: 'Refrigerante Lata',
                  unit: 'un',
                },
                quantity: 2,
                unit_price: 15.5,
                line_total: 31,
              },
            ],
          }),
          meta: {},
        }),
      })
    })

    await page.goto('/pdv')

    await expect(page.getByRole('heading', { name: 'Ponto de venda' })).toBeVisible()
    await expect(page.getByText('Caixa aberto')).toBeVisible()
    await expect(page.getByText('Base offline pronta')).toBeVisible()

    await page.getByPlaceholder('Código de barras, nome ou SKU — Enter para adicionar').fill('Refri')
    await page.getByRole('button', { name: /Refrigerante Lata/ }).click()

    await expect(page.getByText('Itens (1)')).toBeVisible()
    await expect(page.getByText('Refrigerante Lata')).toBeVisible()

    await page.getByRole('spinbutton').fill('2')
    await expect(page.getByText('R$ 31,00').first()).toBeVisible()

    await page.getByRole('button', { name: 'Finalizar venda (F4)' }).click()

    await expect(page.getByRole('heading', { name: 'Finalizar venda' })).toBeVisible()
    await expect(page.getByText('Pago R$ 31,00')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Confirmar venda' })).toBeEnabled()

    await page.getByRole('button', { name: 'Confirmar venda' }).click()

    await page.waitForURL('**/pdv/recibo')
    await expect(page.getByText('Recibo não-fiscal')).toBeVisible()
    await expect(page.getByText('Pedido 3001')).toBeVisible()
    await expect(page.getByText('Refrigerante Lata')).toBeVisible()
    await expect(page.getByText('Dinheiro')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Nova venda' })).toBeVisible()
  })

  test('opera em modo offline controlado com base local válida e fila a venda para sincronização posterior', async ({ page }) => {
    test.slow()

    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['pdv:read', 'pdv:open', 'pdv:sell', 'products:read'],
      tenantFunctionalities: ['pdv', 'products'],
    })

    await mockPdvReadSide(page)

    let salesPostCalls = 0
    await page.route('**/api/v1/pdv/sales', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      salesPostCalls += 1
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          success: false,
          message: 'Este endpoint não deveria ser chamado durante o fluxo offline.',
          code: 'UNEXPECTED_ONLINE_CALL',
          errors: {},
          meta: {},
        }),
      })
    })

    await page.goto('/pdv')

    await expect(page.getByRole('heading', { name: 'Ponto de venda' })).toBeVisible()
    await expect(page.getByText('Base offline pronta')).toBeVisible()

    await page.evaluate(() => {
      Object.defineProperty(window.navigator, 'onLine', {
        configurable: true,
        get: () => false,
      })
      window.dispatchEvent(new Event('offline'))
    })

    await expect(page.getByText('Modo offline', { exact: true })).toBeVisible()
    await expect(page.getByText('PDV em modo offline controlado. Somente dinheiro pode ser registrado até a sincronização.')).toBeVisible()

    const searchField = page.getByPlaceholder('Código de barras, nome ou SKU — Enter para adicionar')
    await searchField.fill('7890001112223')
    await searchField.press('Enter')

    await expect(page.getByText('Itens (1)')).toBeVisible()
    await expect(page.getByText('Refrigerante Lata')).toBeVisible()

    await page.getByRole('button', { name: 'Finalizar venda (F4)' }).click()

    await expect(page.getByRole('heading', { name: 'Finalizar venda' })).toBeVisible()
    await expect(page.getByText('A identificação de cliente fica limitada no modo offline. Esta venda será registrada como consumidor final e sincronizada depois.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Salvar venda offline' })).toBeEnabled()
    await expect(page.getByRole('option', { name: 'Pix' })).toHaveCount(0)

    await page.getByRole('button', { name: 'Salvar venda offline' }).click()

    await page.waitForURL('**/pdv/recibo')
    await expect(page.getByText('Venda salva offline, aguardando sincronização')).toBeVisible()
    await expect(page.getByText('Este comprovante foi emitido no modo offline do PDV. A venda será enviada ao servidor assim que a conexão voltar.')).toBeVisible()
    await expect(page.getByText('Dinheiro')).toBeVisible()
    await expect.poll(() => salesPostCalls).toBe(0)

    await page.getByRole('button', { name: 'Nova venda' }).click()
    await expect(page).toHaveURL(/\/pdv$/)
    await expect(page.getByText('Atividade offline do PDV')).toBeVisible()
    await expect(page.getByText('Consumidor final')).toBeVisible()
    await expect(page.getByText('1 pendência')).toBeVisible()
  })
})
