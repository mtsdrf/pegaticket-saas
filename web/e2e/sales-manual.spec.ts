import { expect, test } from '@playwright/test'
import { makeSale, mockAuthenticatedShell, mockPaginatedApiRoute } from './support/api'

test.describe('Vendas manuais', () => {
  test('mostra o estado vazio focado em operação manual e sem filtros da central', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['sales:read', 'sales:create'],
      tenantFunctionalities: ['sales'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/sales',
      body: [],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
      },
    })

    await page.goto('/vendas-manuais')

    await expect(page.getByRole('heading', { name: 'Vendas manuais' })).toBeVisible()
    await expect(page.getByText('Gerencie as vendas lançadas manualmente pela equipe.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Nova venda' })).toBeVisible()
    await expect(page.getByText('Nenhuma venda manual encontrada')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Criar primeira venda' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Central de operação' })).toHaveCount(0)
    await expect(page.getByText('Ativos apenas')).toHaveCount(0)
    await expect(page.getByText('Aguardando aprovação')).toHaveCount(0)
  })

  test('lista vendas manuais sem depender dos cards operacionais da central', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['sales:read', 'sales:create', 'sales:update'],
      tenantFunctionalities: ['sales'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/sales',
      body: [
        makeSale({
          uuid: 'order-qa-manual-1',
          codigo: '1042',
          total_amount: 239.5,
          final_customer: {
            uuid: 'final-customer-qa-42',
            name: 'Maria da Silva',
          },
          notes: 'Venda porta a porta',
        }),
      ],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    })

    await page.goto('/vendas-manuais')

    await expect(page.getByRole('heading', { name: 'Vendas manuais' })).toBeVisible()
    await expect(page.getByText('Maria da Silva')).toBeVisible()
    await expect(page.getByText('1042')).toBeVisible()
    await expect(page.getByText('Manual')).toBeVisible()
    await expect(page.getByText('Central de operação')).toHaveCount(0)
    await expect(page.getByText('Nenhuma venda manual encontrada')).toHaveCount(0)
  })

  test('cria uma nova venda manual com cliente e ingresso e volta para a lista geral', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['sales:read', 'sales:create'],
      tenantFunctionalities: ['sales'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/sales',
      body: [
        makeSale({
          uuid: 'order-manual-created-1',
          codigo: '2050',
          total_amount: 39.8,
          final_customer: {
            uuid: 'final-customer-order-1',
            name: 'Comprador Venda Manual',
          },
        }),
      ],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    })

    await page.route('**/api/v1/final-customers*', async (route) => {
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
              uuid: 'tenant-final-customer-link-1',
              final_customer: {
                uuid: 'final-customer-order-1',
                name: 'Comprador Venda',
                last_name: 'Manual',
              },
              phone_primary: null,
              phone_secondary: null,
              cpf_cnpj: '12345678901',
              ie: null,
              ie_indicator: 'nao_contribuinte',
              notes: null,
              is_trusted: true,
              is_active: true,
              created_at: '2026-07-28T12:00:00Z',
            },
          ],
          meta: {
            pagination: {
              current_page: 1,
              per_page: 20,
              total: 1,
              last_page: 1,
            },
          },
        }),
      })
    })

    await mockPaginatedApiRoute(page, {
      path: '/ticket-types',
      body: [
        {
          uuid: 'ticket-type-order-1',
          name: 'Ingresso Pista Manual',
          price: 19.9,
          description: null,
          image_url: null,
          quantity_available: 100,
          min_per_order: 1,
          max_per_order: 10,
          sales_start_at: null,
          sales_end_at: null,
          status: 'ativo',
          created_at: '2026-07-28T12:00:00Z',
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    })

    await mockPaginatedApiRoute(page, {
      path: '/event-products',
      body: [
        {
          uuid: 'event-product-order-1',
          name: 'Estacionamento VIP',
          description: null,
          price: 35,
          quantity_available: 50,
          max_per_order: 2,
          sales_start_at: null,
          sales_end_at: null,
          kind: 'parking',
          requires_plate: false,
          requires_model: false,
          requires_color: false,
          status: 'ativo',
          created_at: '2026-07-28T12:00:00Z',
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    })

    await page.route('**/api/v1/sales', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      const payload = JSON.parse(route.request().postData() ?? '{}')
      expect(payload).toMatchObject({
        final_customer_uuid: 'final-customer-order-1',
        is_installment: false,
        mark_as_delivered: true,
        mark_as_paid: false,
        items: [
          {
            ticket_type_uuid: 'ticket-type-order-1',
            quantity: 2,
            unit_price: 19.9,
          },
        ],
      })

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Pedido criado com sucesso.',
          data: makeSale({
            uuid: 'order-manual-created-1',
            codigo: '2050',
            total_amount: 39.8,
            final_customer: {
              uuid: 'final-customer-order-1',
              name: 'Comprador Venda Manual',
            },
          }),
          meta: {},
        }),
      })
    })

    await page.goto('/vendas/nova')

    await expect(page.getByRole('heading', { name: 'Nova venda' })).toBeVisible()

    await page.getByRole('combobox', { name: 'Cliente' }).click()
    await page.getByRole('combobox', { name: 'Cliente' }).fill('Comprador Venda')
    await page.getByRole('option', { name: /Comprador Venda Manual/ }).click()

    await page.getByRole('combobox', { name: 'Ingresso / adicional' }).click()
    await page.getByRole('combobox', { name: 'Ingresso / adicional' }).fill('Ingresso Pista')
    await page.getByRole('option', { name: 'Ingresso Pista Manual (ingresso)' }).click()

    await page.getByLabel('Quantidade').fill('2')

    await expect(page.getByText('Total da venda')).toBeVisible()
    await expect(page.getByText('R$ 39,80')).toHaveCount(2)

    await page.getByRole('button', { name: 'Salvar' }).click()

    await page.waitForURL('**/vendas')
    await expect(page.getByText('2050').first()).toBeVisible()
    await expect(page.getByText('Comprador Venda Manual').first()).toBeVisible()
  })
})
