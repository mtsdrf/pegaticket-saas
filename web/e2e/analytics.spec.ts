import { expect, test } from '@playwright/test'
import { mockAuthenticatedShell } from './support/api'

test.describe('Análises', () => {
  test('carrega as abas principais com dados reais de analytics e navega entre os recortes do período', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['reports:read', 'dashboard:read'],
      tenantFunctionalities: ['reports', 'dashboard'],
    })

    await page.route('**/api/v1/reports/analytics/sales-summary*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            group_by: 'month',
            current: {
              from: '2025-08-01',
              to: '2026-07-31',
              total_orders: 18,
              total_revenue: 4820.5,
              average_ticket: 267.81,
              buckets: [
                { period: '2026-06', count: 9, total_amount: 2210.2, average_ticket: 245.58 },
                { period: '2026-07', count: 9, total_amount: 2610.3, average_ticket: 290.03 },
              ],
            },
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/margin-summary*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            revenue_total: '4820.50',
            revenue_with_cost: '3615.38',
            product_cost_total: '1446.15',
            gross_profit_total: '2169.23',
            gross_margin_percentage: 59.99,
            coverage_percentage: 75,
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/revenue-concentration*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            concentration_percentage: 41.8,
            top_clients: [],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/delivery-otif*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            eligible_orders: 12,
            on_time_orders: 10,
            in_full_orders: 11,
            otif_orders: 9,
            otif_percentage: 75,
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/coupon-roi*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            total_discount_amount: '211.40',
            orders_with_coupon: { count: 6, revenue: '1640.00', average_ticket: '273.33' },
            orders_without_coupon: { count: 12, revenue: '3180.50', average_ticket: '265.04' },
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/churn-clients*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            churned_clients_count: 4,
            estimated_monthly_revenue_at_risk: '840.00',
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/top-products*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: [
            { product_name: 'Pizza Calabresa', quantity_sold: 32, revenue: 1664.0 },
            { product_name: 'Lasanha Artesanal', quantity_sold: 19, revenue: 1140.5 },
          ],
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/abc-analysis*', async (route) => {
      const dimension = new URL(route.request().url()).searchParams.get('dimension')
      const items =
        dimension === 'clients'
          ? [
              {
                client_name: 'Cliente Horizonte',
                revenue: 1800,
                participation_percentage: 37.3,
                cumulative_percentage: 37.3,
                curve_class: 'A',
              },
              {
                client_name: 'Cliente Aurora',
                revenue: 940,
                participation_percentage: 19.5,
                cumulative_percentage: 56.8,
                curve_class: 'B',
              },
            ]
          : [
              {
                product_name: 'Pizza Calabresa',
                revenue: 1664,
                participation_percentage: 34.5,
                cumulative_percentage: 34.5,
                curve_class: 'A',
              },
              {
                product_name: 'Lasanha Artesanal',
                revenue: 1140.5,
                participation_percentage: 23.7,
                cumulative_percentage: 58.2,
                curve_class: 'B',
              },
            ]

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: items, meta: {} }),
      })
    })

    await page.route('**/api/v1/reports/analytics/stalled-products*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            total_value_tied_up: '420.00',
            items: [
              { product_name: 'Suco Verde', quantity_on_hand: 14, value_tied_up: 210, cost_is_estimated: true },
            ],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/stock-ruptures*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            items: [{ product_name: 'Água com gás', units_sold_last_90_days: 27 }],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/sales-by-location*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            cities: [{ city_name: 'São Paulo', orders_count: 11, total_amount: 3020.4 }],
            neighborhoods: [{ neighborhood_name: 'Mooca', orders_count: 5, total_amount: 1310.2 }],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/sales-history*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: [
            {
              year: 2025,
              months: [
                { month: 6, count: 7, total_amount: 1820.55 },
                { month: 7, count: 8, total_amount: 1940.1 },
              ],
            },
            {
              year: 2026,
              months: [
                { month: 6, count: 9, total_amount: 2210.2 },
                { month: 7, count: 9, total_amount: 2610.3 },
              ],
            },
          ],
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/sales-by-hour*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            cells: [
              { day_of_week: 2, hour: 12, count: 4, total_amount: '320.00' },
              { day_of_week: 2, hour: 19, count: 7, total_amount: '810.00' },
              { day_of_week: 6, hour: 20, count: 3, total_amount: '450.00' },
            ],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/top-clients*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: [
            { client_name: 'Cliente Horizonte', order_count: 8, total_amount: 1800, rfm: 'vip' },
            { client_name: 'Cliente Aurora', order_count: 5, total_amount: 940, rfm: 'recorrente' },
          ],
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/payment-delays*', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: [
            { client_name: 'Cliente Horizonte', avg_days_to_pay: 2, paid_orders_count: 5 },
            { client_name: 'Cliente Aurora', avg_days_to_pay: 7, paid_orders_count: 3 },
          ],
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/reports/analytics/overdue-sales*', async (route) => {
      const url = new URL(route.request().url())
      const pageNumber = Number(url.searchParams.get('page') ?? '1')
      const rows =
        pageNumber === 1
          ? [
              {
                order_uuid: 'order-1',
                client_name: 'Cliente Horizonte',
                open_amount: 210.4,
                days_overdue: 12,
                type: 'pagamento',
              },
              {
                order_uuid: 'order-2',
                client_name: 'Cliente Aurora',
                open_amount: 98,
                days_overdue: 33,
                type: 'entrega',
              },
            ]
          : [
              {
                order_uuid: 'order-3',
                client_name: 'Cliente Horizonte Sul',
                open_amount: 55,
                days_overdue: 4,
                type: 'pagamento',
              },
            ]

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: rows,
          meta: {
            pagination: {
              current_page: pageNumber,
              per_page: 15,
              total: 3,
              last_page: 2,
            },
          },
        }),
      })
    })

    await page.goto('/analises')

    await expect(page.getByRole('heading', { name: 'Análises' })).toBeVisible()
    await expect(page.getByText('Margem bruta (aprox.)')).toBeVisible()
    await expect(page.getByText('59,99%')).toBeVisible()
    await expect(page.getByText('Cashback em aberto')).toBeVisible()
    await expect(page.getByText('R$ 128,30')).toBeVisible()

    await page.getByRole('tab', { name: 'Produtos' }).click()
    await expect(page.getByText('Produtos mais vendidos')).toBeVisible()
    await expect(page.getByText('Pizza Calabresa').first()).toBeVisible()
    await expect(page.getByText('Curva ABC de produtos')).toBeVisible()
    await expect(page.getByText('Ruptura de estoque')).toBeVisible()

    await page.getByRole('tab', { name: 'Locais' }).click()
    await expect(page.getByText('Vendas por cidade')).toBeVisible()
    await expect(page.getByText('São Paulo')).toBeVisible()
    await expect(page.getByText('Vendas por bairro')).toBeVisible()
    await expect(page.getByText('Mooca')).toBeVisible()

    await page.getByRole('tab', { name: 'Sazonalidade' }).click()
    await expect(page.getByText('Movimento por dia e hora')).toBeVisible()
    await expect(page.getByText('Sazonalidade').last()).toBeVisible()
    await expect(page.getByText('2025')).toBeVisible()
    await expect(page.getByText('2026')).toBeVisible()

    await page.getByRole('tab', { name: 'Clientes' }).click()
    await expect(page.getByText('Melhores clientes')).toBeVisible()
    await expect(page.getByText('Cliente Horizonte').first()).toBeVisible()
    await expect(page.getByText('Atrasos de pagamento')).toBeVisible()

    await page.getByRole('tab', { name: 'Atrasos' }).click()
    await expect(page.getByText('Pedidos em atraso')).toBeVisible()
    await expect(page.getByRole('cell', { name: 'Cliente Aurora' })).toBeVisible()
    await expect(page.getByText('33 dias')).toBeVisible()

    await page.getByRole('button', { name: 'Go to page 2' }).click()
    await expect(page.getByRole('cell', { name: 'Cliente Horizonte Sul' })).toBeVisible()
    await expect(page.getByText('4 dias')).toBeVisible()
  })
})
