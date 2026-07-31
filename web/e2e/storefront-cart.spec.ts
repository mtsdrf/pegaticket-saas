import { expect, test } from '@playwright/test'

function storefrontTenant(slug: string, name: string) {
  return {
    slug,
    name,
    logo_url: null,
    business_hours: [],
    estimated_preparation_minutes: 25,
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
    allow_store_pickup: true,
    storefront_enabled: true,
  }
}

function storefrontProduct(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    uuid: 'product-loja-1',
    name: 'Bolo de cenoura',
    description: 'Fatia generosa com cobertura de chocolate.',
    image_url: null,
    price: 18.5,
    promo_price: null,
    wholesale_min_quantity: null,
    wholesale_price: null,
    is_available: true,
    is_favorited: false,
    badges: [],
    option_groups: [],
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

    await page.route('**/api/v1/loja/loja-a/produtos*', async (route) => {
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
            storefrontProduct(),
            storefrontProduct({
              uuid: 'product-loja-2',
              name: 'Torta de limão',
              price: 22,
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

    await page.route('**/api/v1/loja/loja-b/produtos*', async (route) => {
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
            storefrontProduct({
              uuid: 'product-loja-b-1',
              name: 'Empada de frango',
              price: 12,
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

    await page.goto('/loja/loja-a')

    await expect(page.getByText('Loja A')).toBeVisible()
    await expect(page.getByText('Bolo de cenoura').first()).toBeVisible()

    await page.getByRole('button', { name: 'Adicionar' }).first().click()
    await expect(page.getByLabel('Carrinho, 1 itens')).toBeVisible()

    await page.getByRole('button', { name: 'Carrinho, 1 itens' }).click()
    await expect(page).toHaveURL(/\/loja\/loja-a\/carrinho$/)
    await expect(page.getByText('Bolo de cenoura').first()).toBeVisible()
    await expect(page.getByText('R$ 18,50').first()).toBeVisible()

    await page.reload()

    await expect(page.getByText('Bolo de cenoura').first()).toBeVisible()
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

    await page.goto('/loja/loja-b/carrinho')

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
    await expect(page).toHaveURL(/\/loja\/loja-b$/)
    await expect(page.getByText('Empada de frango').first()).toBeVisible()
    await page.getByRole('button', { name: 'Adicionar' }).first().click()

    await expect(page.getByLabel('Carrinho, 1 itens')).toBeVisible()
    await page.getByRole('button', { name: 'Carrinho, 1 itens' }).click()
    await expect(page.getByText('Empada de frango').first()).toBeVisible()

    await page.goto('/loja/loja-a/carrinho')

    await expect(page.getByText('Bolo de cenoura').first()).toBeVisible()
    await expect(page.getByText('Empada de frango')).toHaveCount(0)
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
        lojaA: 'Bolo de cenoura',
        lojaB: 'Empada de frango',
      })
  })
})
