import { expect, test } from '@playwright/test'
import { mockAuthenticatedShell } from './support/api'

function makeDeliveryCandidates() {
  return {
    type: 'delivery',
    date: '2026-07-28',
    stops: [
      {
        client_uuid: 'route-client-1',
        client_name: 'Cliente Rota Centro',
        phone_primary: '11999990001',
        endereco: {
          logradouro: 'Rua A',
          numero: '100',
          bairro_name: 'Centro',
          cidade_name: 'São Paulo',
          lat: -23.55052,
          lng: -46.633308,
          geocode_status: 'success',
        },
        orders: [
          {
            uuid: 'route-order-1',
            total_amount: 120.5,
            is_paid: false,
            is_delivered: false,
            is_installment: false,
            expected_delivery_date: '2026-07-28',
          },
        ],
        installments: [],
      },
      {
        client_uuid: 'route-client-2',
        client_name: 'Cliente Rota Sul',
        phone_primary: '11999990002',
        endereco: {
          logradouro: 'Rua B',
          numero: '200',
          bairro_name: 'Vila Mariana',
          cidade_name: 'São Paulo',
          lat: -23.589927,
          lng: -46.634665,
          geocode_status: 'success',
        },
        orders: [
          {
            uuid: 'route-order-2',
            total_amount: 89.9,
            is_paid: true,
            is_delivered: true,
            is_installment: false,
            expected_delivery_date: '2026-07-28',
          },
        ],
        installments: [],
      },
      {
        client_uuid: 'route-client-3',
        client_name: 'Cliente Sem Geocode',
        phone_primary: null,
        endereco: {
          logradouro: 'Rua C',
          numero: '300',
          bairro_name: 'Sem Bairro',
          cidade_name: 'São Paulo',
          lat: null,
          lng: null,
          geocode_status: 'pending',
        },
        orders: [
          {
            uuid: 'route-order-3',
            total_amount: 45,
            is_paid: false,
            is_delivered: false,
            is_installment: false,
            expected_delivery_date: '2026-07-28',
          },
        ],
        installments: [],
      },
    ],
  }
}

function makeCollectionCandidates() {
  return {
    type: 'collection',
    date: '2026-07-28',
    stops: [
      {
        client_uuid: 'collection-client-1',
        client_name: 'Cliente Cobrança Norte',
        phone_primary: '11999990009',
        endereco: {
          logradouro: 'Rua D',
          numero: '400',
          bairro_name: 'Santana',
          cidade_name: 'São Paulo',
          lat: -23.500321,
          lng: -46.625111,
          geocode_status: 'success',
        },
        orders: [],
        installments: [
          {
            uuid: 'installment-1',
            order_uuid: 'collection-order-1',
            amount: 60,
            due_date: '2026-07-28',
            is_overdue: false,
            is_paid: false,
          },
          {
            uuid: 'installment-2',
            order_uuid: 'collection-order-1',
            amount: 40,
            due_date: '2026-07-20',
            is_overdue: true,
            is_paid: true,
          },
        ],
      },
    ],
  }
}

test.describe('Planejamento de rotas', () => {
  test('monta rota de entrega com paradas reais, separa itens sem localização e permite ações operacionais', async ({ page, context }) => {
    await context.grantPermissions(['geolocation'])
    await context.setGeolocation({ latitude: -23.5515, longitude: -46.6339 })

    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['routes:read', 'orders:update', 'dashboard:read'],
      tenantFunctionalities: ['routes', 'sales', 'dashboard'],
    })

    const deliveryCandidates = makeDeliveryCandidates()

    await page.route('**/api/v1/routes/candidates*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      const url = new URL(route.request().url())
      const type = url.searchParams.get('type')
      const payload = type === 'collection' ? makeCollectionCandidates() : deliveryCandidates

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: payload,
          meta: {},
        }),
      })
    })

    await page.route('https://router.project-osrm.org/**', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          code: 'Ok',
          trips: [
            {
              geometry: {
                type: 'LineString',
                coordinates: [
                  [-46.6339, -23.5515],
                  [-46.633308, -23.55052],
                  [-46.634665, -23.589927],
                ],
              },
              distance: 8200,
              duration: 1450,
            },
          ],
          waypoints: [
            {
              waypoint_index: 0,
              location: [-46.6339, -23.5515],
            },
            {
              waypoint_index: 1,
              location: [-46.633308, -23.55052],
            },
            {
              waypoint_index: 2,
              location: [-46.634665, -23.589927],
            },
          ],
        }),
      })
    })

    await page.route('**/api/v1/sales/route-order-1/deliver', async (route) => {
      if (route.request().method() !== 'PATCH') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: { uuid: 'route-order-1', is_delivered: true },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/sales/route-order-1/pay', async (route) => {
      if (route.request().method() !== 'PATCH') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: { uuid: 'route-order-1', is_paid: true },
          meta: {},
        }),
      })
    })

    await page.goto('/rotas')

    await expect(page.getByRole('heading', { name: 'Montar rota' })).toBeVisible()
    await expect(page.getByText('Paradas roteirizáveis (2)')).toBeVisible()
    await expect(page.getByText('Sem localização (1)')).toBeVisible()
    await expect(page.getByText('Cliente Rota Centro')).toBeVisible()
    await expect(page.getByText('Cliente Sem Geocode')).toBeVisible()
    await expect(page.getByText('Segunda · Manhã')).toBeVisible()

    await page.getByRole('button', { name: 'Montar rota' }).click()

    await expect(page.getByText('Itinerário otimizado')).toBeVisible()
    await expect(page.getByRole('link', { name: 'Abrir rota completa no Google Maps' })).toBeVisible()
    await expect(page.getByText('Abrir próxima parada no Waze', { exact: true })).toBeVisible()
    await expect(page.getByText('Você está aqui')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Marcar como entregue' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Marcar como pago' })).toBeVisible()

    await page.getByRole('button', { name: 'Marcar como entregue' }).click()
    await expect(page.getByText('Entregue').first()).toBeVisible()

    await page.getByRole('button', { name: 'Marcar como pago' }).click()
    await expect(page.getByText('Pago').first()).toBeVisible()
    await expect(page.getByText('Total roteirizado: R$ 210,40')).toBeVisible()
  })

  test('opera a rota de cobrança com parcelas do dia e atrasadas, incluindo marcação de pagamento', async ({ page, context }) => {
    await context.grantPermissions(['geolocation'])
    await context.setGeolocation({ latitude: -23.5009, longitude: -46.6248 })

    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['routes:read', 'orders:update', 'dashboard:read'],
      tenantFunctionalities: ['routes', 'sales', 'dashboard'],
    })

    await page.route('**/api/v1/routes/candidates*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      const url = new URL(route.request().url())
      const type = url.searchParams.get('type')
      const payload = type === 'collection' ? makeCollectionCandidates() : makeDeliveryCandidates()

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: payload,
          meta: {},
        }),
      })
    })

    await page.route('https://router.project-osrm.org/**', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          code: 'Ok',
          trips: [
            {
              geometry: {
                type: 'LineString',
                coordinates: [
                  [-46.6248, -23.5009],
                  [-46.625111, -23.500321],
                ],
              },
              distance: 1200,
              duration: 420,
            },
          ],
          waypoints: [
            {
              waypoint_index: 0,
              location: [-46.6248, -23.5009],
            },
            {
              waypoint_index: 1,
              location: [-46.625111, -23.500321],
            },
          ],
        }),
      })
    })

    await page.route('**/api/v1/sales/collection-order-1/installments/installment-1/pay', async (route) => {
      if (route.request().method() !== 'PATCH') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: { uuid: 'collection-order-1' },
          meta: {},
        }),
      })
    })

    await page.goto('/rotas')

    await page.getByRole('button', { name: 'Cobrar' }).click()

    await expect(page.getByText('Paradas roteirizáveis (1)')).toBeVisible()
    await expect(page.getByText('Cliente Cobrança Norte')).toBeVisible()
    await expect(page.getByText('Tarde')).toBeVisible()

    await page.getByRole('button', { name: 'Montar rota' }).click()

    await expect(page.getByText('Itinerário otimizado')).toBeVisible()
    await expect(page.getByText('Parcela — R$ 60,00')).toBeVisible()
    await expect(page.getByText('Parcela — R$ 40,00')).toBeVisible()
    await expect(page.getByText('Atrasada')).toBeVisible()

    const markPaidButtons = page.getByRole('button', { name: 'Marcar como pago' })
    await markPaidButtons.first().click()

    await expect(page.getByText('Pago').first()).toBeVisible()
    await expect(page.getByText('Total roteirizado: R$ 100,00')).toBeVisible()
  })
})
