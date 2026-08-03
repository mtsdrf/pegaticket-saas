import { expect, test } from '@playwright/test'

function storefrontTenant(slug: string, name: string) {
  return {
    slug,
    name,
    logo_url: null,
    average_rating: 4.8,
    ratings_count: 18,
    email: `contato@${slug}.com`,
    phone: '1133334444',
    mobile_phone: '11999998888',
    whatsapp: '11999998888',
    instagram: `@${slug}`,
    facebook: null,
    address: `Rua principal da ${name}, 100`,
    address_lat: null,
    address_lng: null,
    accepted_payment_methods: ['cash', 'credit_card'],
    storefront_enabled: true,
    catalog_layout: 'grid',
  }
}

function storefrontEvent(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    uuid: 'event-loja-1',
    name: 'Festival de Inverno',
    slug: 'festival-de-inverno',
    description_short: 'Noite de shows com ingressos por lote.',
    description_full: 'Evento principal da temporada com pista e estacionamento opcional.',
    cover_image_url: null,
    type: 'ingresso',
    location_name: 'Arena Loja A',
    location_address: 'Rua principal da Loja A, 100',
    starts_at: '2026-08-20 20:00:00',
    ends_at: '2026-08-21 02:00:00',
    status: 'publicado',
    category: null,
    venue: null,
    is_favorited: false,
    ...overrides,
  }
}

test.describe('Carrinho público da loja', () => {
  test('persiste o carrinho após recarregar e mantém isolamento entre lojas por slug', async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('mk_age_verified_loja-a', 'true')
      localStorage.setItem('mk_age_verified_loja-b', 'true')
    })

    await page.route('**/api/v1/loja/loja-a', async (route) => {
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
          data: storefrontTenant('loja-a', 'Loja A'),
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/loja/loja-b', async (route) => {
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
          data: storefrontTenant('loja-b', 'Loja B'),
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/loja/loja-a/categorias*', async (route) => {
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
          data: [],
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/loja/loja-b/categorias*', async (route) => {
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
          data: [],
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/loja/loja-a/eventos*', async (route) => {
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
            storefrontEvent(),
            storefrontEvent({
              uuid: 'event-loja-2',
              name: 'Sunset Premium',
              slug: 'sunset-premium',
              location_name: 'Arena Loja A - Sunset',
            }),
          ],
          meta: {
            pagination: {
              current_page: 1,
              per_page: 12,
              total: 2,
              last_page: 1,
            },
          },
        }),
      })
    })

    await page.route('**/api/v1/loja/loja-b/eventos*', async (route) => {
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
            storefrontEvent({
              uuid: 'event-loja-b-1',
              name: 'Noite Eletrônica',
              slug: 'noite-eletronica',
              location_name: 'Arena Loja B',
            }),
          ],
          meta: {
            pagination: {
              current_page: 1,
              per_page: 12,
              total: 1,
              last_page: 1,
            },
          },
        }),
      })
    })

    await page.route('**/api/v1/loja/loja-a/eventos/festival-de-inverno', async (route) => {
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
            ...storefrontEvent(),
            ticket_types: [
              {
                uuid: 'ticket-type-loja-a-1',
                name: 'Pista inteira',
                description: 'Acesso único ao evento.',
                price: 18.5,
                image_url: null,
                quantity_available: 100,
                min_per_order: 1,
                max_per_order: 10,
                sales_start_at: null,
                sales_end_at: null,
                status: 'ativo',
              },
            ],
            event_products: [],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/loja/loja-b/eventos/noite-eletronica', async (route) => {
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
            ...storefrontEvent({
              uuid: 'event-loja-b-1',
              name: 'Noite Eletrônica',
              slug: 'noite-eletronica',
              location_name: 'Arena Loja B',
            }),
            ticket_types: [
              {
                uuid: 'ticket-type-loja-b-1',
                name: 'Front stage',
                description: 'Acesso à área premium.',
                price: 12,
                image_url: null,
                quantity_available: 100,
                min_per_order: 1,
                max_per_order: 10,
                sales_start_at: null,
                sales_end_at: null,
                status: 'ativo',
              },
            ],
            event_products: [],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/loja/loja-a/eventos/festival-de-inverno/disponibilidade*', async (route) => {
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
            event: storefrontEvent(),
            requires_session_selection: false,
            selected_session_uuid: null,
            sessions: [],
            ticket_types: [
              {
                uuid: 'ticket-type-loja-a-1',
                name: 'Pista inteira',
                description: 'Acesso único ao evento.',
                session_uuid: null,
                base_price: 18.5,
                effective_price: 18.5,
                available_quantity: 100,
                min_per_order: 1,
                max_per_order: 10,
                sales_start_at: null,
                sales_end_at: null,
                active_batch: null,
                requires_seat_selection: false,
              },
            ],
            event_products: [],
            seats: [],
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/loja/loja-b/eventos/noite-eletronica/disponibilidade*', async (route) => {
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
            event: storefrontEvent({
              uuid: 'event-loja-b-1',
              name: 'Noite Eletrônica',
              slug: 'noite-eletronica',
              location_name: 'Arena Loja B',
            }),
            requires_session_selection: false,
            selected_session_uuid: null,
            sessions: [],
            ticket_types: [
              {
                uuid: 'ticket-type-loja-b-1',
                name: 'Front stage',
                description: 'Acesso à área premium.',
                session_uuid: null,
                base_price: 12,
                effective_price: 12,
                available_quantity: 100,
                min_per_order: 1,
                max_per_order: 10,
                sales_start_at: null,
                sales_end_at: null,
                active_batch: null,
                requires_seat_selection: false,
              },
            ],
            event_products: [],
            seats: [],
          },
          meta: {},
        }),
      })
    })

    await page.goto('/eventos/loja-a')

    await expect(page.getByText('Loja A', { exact: true })).toBeVisible()
    await expect(page.getByText('Festival de Inverno').first()).toBeVisible()

    await page.getByText('Festival de Inverno').first().click()
    await page.getByRole('button', { name: 'Adicionar' }).first().click()
    await expect(page.getByLabel('Carrinho, 1 itens')).toBeVisible()

    await page.getByRole('button', { name: 'Carrinho, 1 itens' }).click()
    await expect(page).toHaveURL(/\/eventos\/loja-a\/carrinho$/)
    await expect(page.getByText('Pista inteira').first()).toBeVisible()
    await expect(page.getByText('R$ 18,50').first()).toBeVisible()

    await page.reload()

    await expect(page.getByText('Pista inteira').first()).toBeVisible()
    await expect(page.getByText('R$ 18,50').first()).toBeVisible()
    await expect(page.getByRole('button', { name: 'Continuar para o checkout' })).toBeVisible()
    await expect
      .poll(() =>
        page.evaluate(() => {
          const raw = localStorage.getItem('pegaticket.storefront_cart.loja-a')
          if (!raw) return 0
          return JSON.parse(raw).length
        }),
      )
      .toBe(1)

    await page.goto('/eventos/loja-b/carrinho')

    await expect(page.getByText('Seu carrinho está vazio')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Ver catálogo' })).toBeVisible()
    await expect
      .poll(() =>
        page.evaluate(() => {
          const lojaA = localStorage.getItem('pegaticket.storefront_cart.loja-a')
          const lojaB = localStorage.getItem('pegaticket.storefront_cart.loja-b')
          return {
            lojaA: lojaA ? JSON.parse(lojaA).length : 0,
            lojaB: lojaB ? JSON.parse(lojaB).length : 0,
          }
        }),
      )
      .toEqual({ lojaA: 1, lojaB: 0 })

    await page.getByRole('button', { name: 'Ver catálogo' }).click()
    await expect(page).toHaveURL(/\/eventos\/loja-b$/)
    await expect(page.getByText('Noite Eletrônica').first()).toBeVisible()
    await page.getByText('Noite Eletrônica').first().click()
    await page.getByRole('button', { name: 'Adicionar' }).first().click()

    await expect(page.getByLabel('Carrinho, 1 itens')).toBeVisible()
    await page.getByRole('button', { name: 'Carrinho, 1 itens' }).click()
    await expect(page.getByText('Front stage').first()).toBeVisible()

    await page.goto('/eventos/loja-a/carrinho')

    await expect(page.getByText('Pista inteira').first()).toBeVisible()
    await expect(page.getByText('Front stage')).toHaveCount(0)
    await expect
      .poll(() =>
        page.evaluate(() => {
          const lojaA = localStorage.getItem('pegaticket.storefront_cart.loja-a')
          const lojaB = localStorage.getItem('pegaticket.storefront_cart.loja-b')
          return {
            lojaA: lojaA ? JSON.parse(lojaA)[0]?.name ?? null : null,
            lojaB: lojaB ? JSON.parse(lojaB)[0]?.name ?? null : null,
          }
        }),
      )
      .toEqual({
        lojaA: 'Pista inteira',
        lojaB: 'Front stage',
      })
  })
})
