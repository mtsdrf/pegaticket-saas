import { expect, test } from '@playwright/test'
import { mockAuthenticatedShell } from './support/api'

test.describe('Balcão', () => {
  test('lista mesas, reservas e fila e abre uma comanda existente', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['balcao:read', 'balcao:open', 'balcao:create', 'balcao:update', 'balcao:add_item', 'balcao:close', 'products:read'],
      tenantFunctionalities: ['balcao', 'products'],
    })

    const tables = [
      {
        uuid: 'table-1',
        label: 'Mesa 1',
        area: 'Salão',
        seats: 4,
        status: 'free',
        created_at: '2026-07-28T12:00:00Z',
      },
      {
        uuid: 'table-2',
        label: 'Mesa 2',
        area: 'Varanda',
        seats: 4,
        status: 'occupied',
        created_at: '2026-07-28T12:00:00Z',
      },
    ]

    const reservations = [
      {
        uuid: 'reservation-1',
        customer_name: 'Carla Reserva',
        customer_phone: '11999990000',
        customer_email: null,
        party_size: 4,
        scheduled_for: '2026-07-28T20:00:00Z',
        duration_minutes: 120,
        status: 'confirmed',
        source: 'internal',
        notes: 'Mesa perto da janela',
        cancelled_reason: null,
        confirmed_at: '2026-07-28T10:00:00Z',
        seated_at: null,
        cancelled_at: null,
        no_show_at: null,
        table: { uuid: 'table-1', label: 'Mesa 1', seats: 4 },
        seated_comanda_uuid: null,
        created_at: '2026-07-28T10:00:00Z',
        updated_at: '2026-07-28T10:00:00Z',
      },
    ]

    const waitlist = [
      {
        uuid: 'waitlist-1',
        customer_name: 'Paulo Fila',
        customer_phone: '11988887777',
        party_size: 2,
        quoted_wait_minutes: 15,
        status: 'waiting',
        notes: null,
        cancelled_reason: null,
        called_at: null,
        seated_at: null,
        cancelled_at: null,
        table: null,
        seated_comanda_uuid: null,
        created_at: '2026-07-28T12:10:00Z',
        updated_at: '2026-07-28T12:10:00Z',
      },
    ]

    let comandas = [
      {
        uuid: 'comanda-1',
        label: 'Família Souza',
        status: 'open',
        opened_at: '2026-07-28T12:15:00Z',
        closed_at: null,
        service_fee_percent: 10,
        order_uuid: null,
        table: { uuid: 'table-2', label: 'Mesa 2' },
        items_subtotal: 32,
        items: [
          {
            uuid: 'comanda-item-1',
            qty: 2,
            unit_price: 16,
            line_total: 32,
            notes: 'Sem gelo',
            prep_status: 'queued',
            sent_to_station_at: null,
            preparing_at: null,
            ready_at: null,
            delivered_at: null,
            cancelled_at: null,
            cancelled_reason: null,
            product: {
              uuid: 'product-1',
              name: 'Suco de Laranja',
              unit: 'un',
            },
            station: null,
            comanda: {
              uuid: 'comanda-1',
              label: 'Família Souza',
              table_label: 'Mesa 2',
            },
            updated_at: '2026-07-28T12:15:00Z',
          },
        ],
        created_at: '2026-07-28T12:15:00Z',
        updated_at: '2026-07-28T12:15:00Z',
      },
    ]

    await page.route('**/api/v1/balcao/tables*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: tables, meta: {} }),
      })
    })

    await page.route('**/api/v1/balcao/comandas*', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: comandas, meta: {} }),
        })
        return
      }

      if (method === 'POST' && route.request().url().includes('/items')) {
        const payload = JSON.parse(route.request().postData() ?? '{}')
        expect(payload).toMatchObject({
          product_uuid: 'product-2',
          qty: 1,
          notes: 'Sem açúcar',
        })

        const nextItem = {
          uuid: 'comanda-item-2',
          qty: 1,
          unit_price: 18.5,
          line_total: 18.5,
          notes: 'Sem açúcar',
          prep_status: 'queued',
          sent_to_station_at: null,
          preparing_at: null,
          ready_at: null,
          delivered_at: null,
          cancelled_at: null,
          cancelled_reason: null,
          product: {
            uuid: 'product-2',
            name: 'Suco Natural',
            unit: 'un',
          },
          station: null,
          comanda: {
            uuid: 'comanda-1',
            label: 'Família Souza',
            table_label: 'Mesa 2',
          },
          updated_at: '2026-07-28T12:20:00Z',
        }

        comandas = comandas.map((comanda) =>
          comanda.uuid === 'comanda-1'
            ? {
                ...comanda,
                items_subtotal: 50.5,
                items: [...(comanda.items ?? []), nextItem],
                updated_at: '2026-07-28T12:20:00Z',
              }
            : comanda,
        )

        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: nextItem, meta: {} }),
        })
        return
      }

      await route.fallback()
    })

    await page.route('**/api/v1/balcao/reservas*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: reservations, meta: {} }),
      })
    })

    await page.route('**/api/v1/balcao/fila-espera*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: waitlist, meta: {} }),
      })
    })

    await page.route('**/api/v1/balcao/offline-snapshot*', async (route) => {
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
            generated_at: '2026-07-28T12:00:00Z',
            tables,
            comandas,
            products: [
              {
                uuid: 'product-2',
                name: 'Suco Natural',
                sku: 'SUC-001',
                barcode: '7891234560001',
                unit: 'un',
                price: 18.5,
                updated_at: '2026-07-28T12:00:00Z',
              },
            ],
          },
          meta: {},
        }),
      })
    })

    await page.goto('/balcao/mesas')

    await expect(page.getByRole('heading', { name: 'Balcão', exact: true })).toBeVisible()
    await expect(page.getByText('Carla Reserva')).toBeVisible()
    await expect(page.getByText('Paulo Fila')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Mesa 1 Livre' })).toBeVisible()
    await expect(page.getByRole('button', { name: /Mesa 2/ })).toBeVisible()

    await page.getByText('Mesa 2').first().click()

    await page.waitForURL('**/balcao/comandas/comanda-1')
    await expect(page.getByRole('heading', { name: 'Mesa 2' })).toBeVisible()
    await expect(page.getByText('Suco de Laranja')).toBeVisible()
    await expect(page.getByText('Subtotal R$ 32,00')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Adicionar item' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Itens da comanda' })).toBeVisible()
  })

  test('carrega o KDS por estação e mostra os itens da fila operacional', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['balcao:read', 'balcao:prep'],
      tenantFunctionalities: ['balcao'],
    })

    await page.route('**/api/v1/balcao/stations*', async (route) => {
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
              uuid: 'station-1',
              name: 'Cozinha principal',
              type: 'kitchen',
              is_active: true,
              created_at: '2026-07-28T12:00:00Z',
            },
          ],
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/balcao/stations/station-1/tickets*', async (route) => {
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
              uuid: 'ticket-1',
              qty: 1,
              unit_price: 24,
              line_total: 24,
              notes: 'Ponto da carne ao ponto',
              prep_status: 'sent_to_station',
              sent_to_station_at: '2026-07-28T12:15:00Z',
              preparing_at: null,
              ready_at: null,
              delivered_at: null,
              cancelled_at: null,
              cancelled_reason: null,
              product: {
                uuid: 'product-10',
                name: 'Hambúrguer artesanal',
                unit: 'un',
              },
              station: {
                uuid: 'station-1',
                name: 'Cozinha principal',
                type: 'kitchen',
              },
              comanda: {
                uuid: 'comanda-10',
                label: 'Pedido salão',
                table_label: 'Mesa 8',
              },
              updated_at: '2026-07-28T12:15:00Z',
            },
            {
              uuid: 'ticket-2',
              qty: 2,
              unit_price: 12,
              line_total: 24,
              notes: null,
              prep_status: 'preparing',
              sent_to_station_at: '2026-07-28T12:10:00Z',
              preparing_at: '2026-07-28T12:12:00Z',
              ready_at: null,
              delivered_at: null,
              cancelled_at: null,
              cancelled_reason: null,
              product: {
                uuid: 'product-11',
                name: 'Batata rústica',
                unit: 'porção',
              },
              station: {
                uuid: 'station-1',
                name: 'Cozinha principal',
                type: 'kitchen',
              },
              comanda: {
                uuid: 'comanda-11',
                label: 'Pedido salão',
                table_label: 'Mesa 3',
              },
              updated_at: '2026-07-28T12:12:00Z',
            },
          ],
          meta: {},
        }),
      })
    })

    await page.goto('/balcao/kds?station=station-1')

    await expect(page.getByRole('heading', { name: 'Cozinha / Bar', exact: true })).toBeVisible()
    await expect(page.getByRole('combobox', { name: 'Estação' })).toContainText('Cozinha principal')
    await expect(page.getByText('Hambúrguer artesanal')).toBeVisible()
    await expect(page.getByText('Batata rústica')).toBeVisible()
    await expect(page.getByText('Mesa 8')).toBeVisible()
    await expect(page.getByText('Mesa 3')).toBeVisible()
    await expect(page.getByText('Entregar na mesa', { exact: true })).toBeVisible()
    await expect(page.getByText('Cancelar item', { exact: true })).toBeVisible()
  })

  test('detecta conflito offline multi-dispositivo e guia a reconciliação antes de continuar operando', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['balcao:read', 'balcao:open', 'balcao:create', 'balcao:update', 'balcao:add_item', 'balcao:close', 'products:read'],
      tenantFunctionalities: ['balcao', 'products'],
    })

    const tables = [
      {
        uuid: 'table-1',
        label: 'Mesa 1',
        area: 'Salão',
        seats: 4,
        status: 'free',
        created_at: '2026-07-28T12:00:00Z',
      },
    ]

    let serverPhase: 'clean' | 'conflict' = 'clean'

    await page.route('**/api/v1/balcao/tables*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: tables, meta: {} }),
      })
    })

    await page.route('**/api/v1/balcao/comandas*', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        const data =
          serverPhase === 'clean'
            ? []
            : [
                {
                  uuid: 'server-comanda-1',
                  label: 'Mesa já aberta em outro caixa',
                  status: 'open',
                  opened_at: '2026-07-28T12:20:00Z',
                  closed_at: null,
                  service_fee_percent: 10,
                  order_uuid: null,
                  table: { uuid: 'table-1', label: 'Mesa 1' },
                  items: [],
                  items_subtotal: 0,
                  created_at: '2026-07-28T12:20:00Z',
                  updated_at: '2026-07-28T12:20:00Z',
                },
                {
                  uuid: 'server-comanda-2',
                  label: 'Mesa também aberta no tablet do salão',
                  status: 'open',
                  opened_at: '2026-07-28T12:21:00Z',
                  closed_at: null,
                  service_fee_percent: 10,
                  order_uuid: null,
                  table: { uuid: 'table-1', label: 'Mesa 1' },
                  items: [],
                  items_subtotal: 0,
                  created_at: '2026-07-28T12:21:00Z',
                  updated_at: '2026-07-28T12:21:00Z',
                },
              ]

        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data, meta: {} }),
        })
        return
      }

      if (method === 'POST') {
        await route.fulfill({
          status: 422,
          contentType: 'application/json',
          body: JSON.stringify({
            success: false,
            message: 'Este endpoint não deveria ser chamado nesse cenário de conflito offline.',
            code: 'COMANDA_ERROR',
            errors: {},
            meta: {},
          }),
        })
        return
      }

      await route.fallback()
    })

    await page.route('**/api/v1/balcao/reservas*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: [], meta: {} }),
      })
    })

    await page.route('**/api/v1/balcao/fila-espera*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: [], meta: {} }),
      })
    })

    await page.route('**/api/v1/balcao/offline-snapshot*', async (route) => {
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
            generated_at: '2026-07-28T12:00:00Z',
            tables,
            comandas: [],
            products: [
              {
                uuid: 'product-2',
                name: 'Suco Natural',
                sku: 'SUC-001',
                barcode: '7891234560001',
                unit: 'un',
                price: 18.5,
                updated_at: '2026-07-28T12:00:00Z',
              },
            ],
          },
          meta: {},
        }),
      })
    })

    await page.goto('/balcao/mesas')

    await expect(page.getByRole('heading', { name: 'Balcão', exact: true })).toBeVisible()
    await expect(page.getByText('Base offline pronta')).toBeVisible()

    await page.evaluate(() => {
      window.dispatchEvent(new Event('offline'))
    })

    await expect(page.getByText('Modo offline', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'Mesa 1 Livre' }).click()
    await page.getByRole('button', { name: 'Abrir comanda' }).click()

    await page.waitForURL('**/balcao/comandas/**')
    const localComandaUrl = page.url()
    const localComandaUuid = localComandaUrl.split('/').pop() ?? ''

    await expect(page.getByRole('heading', { name: 'Mesa 1' })).toBeVisible()
    const productSearch = page.locator('input[placeholder*="Buscar produto"]').first()
    await expect(productSearch).toBeVisible()
    await productSearch.fill('Suco')
    await page.getByText('Suco Natural').click()
    await page.getByRole('button', { name: 'Salvar item offline' }).click()

    await expect(page.getByText('Item salvo localmente. Ele será sincronizado quando a conexão voltar.')).toBeVisible()
    await expect(page.getByText('Pendente de sincronização')).toBeVisible()

    await page.getByRole('button', { name: 'Voltar ao balcão' }).click()
    await expect(page).toHaveURL(/\/balcao\/mesas$/)

    serverPhase = 'conflict'

    await page.evaluate(() => {
      window.dispatchEvent(new Event('online'))
    })

    await expect(page.getByRole('button', { name: 'Sincronizar pendências' })).toBeEnabled()
    await page.getByRole('button', { name: 'Sincronizar pendências' }).click()

    await expect(page.getByText('Painel de reconciliação offline')).toBeVisible()
    await expect(page.getByText('múltiplas comandas abertas em outros dispositivos').first()).toBeVisible()
    await expect(page.getByText('1 conflito').first()).toBeVisible()

    await page.getByRole('button', { name: 'Revisar' }).click()
    await expect(page).toHaveURL(new RegExp(`/balcao/comandas/${localComandaUuid}$`))
    await expect(page.getByText('Conflito nesta comanda')).toBeVisible()
    await expect(page.getByText('precisa de revisão manual antes de novos lançamentos sensíveis')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Fechar conta' })).toBeDisabled()

    await page.getByRole('button', { name: 'Voltar ao balcão' }).click()
    await expect(page).toHaveURL(/\/balcao\/mesas$/)

    await page.getByRole('button', { name: 'Descartar local' }).click()

    await expect(page.getByText('Lançamento local em conflito descartado neste dispositivo.')).toBeVisible()
    await expect(page.getByText('Painel de reconciliação offline')).toHaveCount(0)
    await expect(page.getByText('1 conflito')).toHaveCount(0)
  })

  test('mantém snapshot offline utilizável e sincroniza a comanda local quando a conexão retorna sem conflito', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['balcao:read', 'balcao:open', 'balcao:create', 'balcao:update', 'balcao:add_item', 'balcao:close', 'products:read'],
      tenantFunctionalities: ['balcao', 'products'],
    })

    const tables = [
      {
        uuid: 'table-sync-1',
        label: 'Mesa 7',
        area: 'Varanda',
        seats: 4,
        status: 'free',
        created_at: '2026-07-28T12:00:00Z',
      },
    ]

    let serverComandas: Array<Record<string, unknown>> = []

    await page.route('**/api/v1/balcao/tables*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: tables, meta: {} }),
      })
    })

    await page.route('**/api/v1/balcao/comandas*', async (route) => {
      const method = route.request().method()

      if (method === 'GET') {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: serverComandas, meta: {} }),
        })
        return
      }

      if (method === 'POST') {
        const body = route.request().postDataJSON() as { table_uuid?: string | null; label?: string | null; client_comanda_uuid?: string | null }
        const opened = {
          uuid: 'server-comanda-sync-1',
          label: body.label ?? 'Mesa 7 offline',
          status: 'open',
          opened_at: '2026-07-28T12:40:00Z',
          closed_at: null,
          service_fee_percent: 10,
          order_uuid: null,
          table: { uuid: body.table_uuid ?? 'table-sync-1', label: 'Mesa 7' },
          items: [],
          items_subtotal: 0,
          created_at: '2026-07-28T12:40:00Z',
          updated_at: '2026-07-28T12:40:00Z',
        }
        serverComandas = [opened]
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, message: 'OK', data: opened, meta: {} }),
        })
        return
      }

      await route.fallback()
    })

    await page.route('**/api/v1/balcao/comandas/server-comanda-sync-1/items*', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      const payload = route.request().postDataJSON() as { qty: number; notes?: string | null }
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            uuid: 'server-item-sync-1',
            qty: payload.qty,
            unit_price: 18.5,
            line_total: 18.5 * payload.qty,
            notes: payload.notes ?? null,
            prep_status: 'queued',
            product: { uuid: 'product-sync-1', name: 'Café gelado' },
            station: null,
            updated_at: '2026-07-28T12:41:00Z',
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/balcao/comandas/server-comanda-sync-1*', async (route) => {
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
            uuid: 'server-comanda-sync-1',
            label: 'Mesa 7 offline',
            status: 'open',
            opened_at: '2026-07-28T12:40:00Z',
            closed_at: null,
            service_fee_percent: 10,
            order_uuid: null,
            table: { uuid: 'table-sync-1', label: 'Mesa 7' },
            items: [
              {
                uuid: 'server-item-sync-1',
                qty: 1,
                unit_price: 18.5,
                line_total: 18.5,
                notes: null,
                prep_status: 'queued',
                product: { uuid: 'product-sync-1', name: 'Café gelado' },
                station: null,
                updated_at: '2026-07-28T12:41:00Z',
              },
            ],
            items_subtotal: 18.5,
            created_at: '2026-07-28T12:40:00Z',
            updated_at: '2026-07-28T12:41:00Z',
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/balcao/reservas*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: [], meta: {} }),
      })
    })

    await page.route('**/api/v1/balcao/fila-espera*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: true, message: 'OK', data: [], meta: {} }),
      })
    })

    await page.route('**/api/v1/balcao/offline-snapshot*', async (route) => {
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
            generated_at: '2026-07-28T12:00:00Z',
            tables,
            comandas: [],
            products: [
              {
                uuid: 'product-sync-1',
                name: 'Café gelado',
                sku: 'CAF-001',
                barcode: '7891234560099',
                unit: 'un',
                price: 18.5,
                updated_at: '2026-07-28T12:00:00Z',
              },
            ],
          },
          meta: {},
        }),
      })
    })

    await page.goto('/balcao/mesas')

    await expect(page.getByText('Base offline pronta')).toBeVisible()
    await page.getByRole('button', { name: 'Atualizar base offline' }).click()
    await expect(page.getByText('Base offline do balcão atualizada com sucesso.')).toBeVisible()

    await page.evaluate(() => {
      window.dispatchEvent(new Event('offline'))
    })

    await expect(page.getByText('Modo offline', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'Mesa 7 Livre' }).click()
    await page.getByRole('button', { name: 'Abrir comanda' }).click()
    await page.waitForURL('**/balcao/comandas/**')

    const productSearch = page.locator('input[placeholder*="Buscar produto"]').first()
    await productSearch.fill('Café')
    const offlineProductOption = page.getByRole('button', { name: /Café gelado/ }).first()
    await expect(offlineProductOption).toBeVisible()
    await offlineProductOption.click()
    await page.getByRole('button', { name: 'Salvar item offline' }).click()

    await expect(page.getByText('Item salvo localmente. Ele será sincronizado quando a conexão voltar.')).toBeVisible()
    await expect(page.getByText('Pendente de sincronização')).toBeVisible()

    await page.getByRole('button', { name: 'Voltar ao balcão' }).click()
    await expect(page).toHaveURL(/\/balcao\/mesas$/)
    await expect(page.getByText('Atividade offline do Balcão')).toBeVisible()
    await expect(page.getByText('Mesa 7').first()).toBeVisible()
    await expect(page.getByRole('button', { name: 'Sincronizar pendências' })).toBeDisabled()

    await page.evaluate(() => {
      window.dispatchEvent(new Event('online'))
    })

    await expect(page.getByText('0 pendências').first()).toBeVisible()
    await expect(page.getByRole('button', { name: /Mesa 7 .* Ocupada/ })).toBeVisible()
  })
})
