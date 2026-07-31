import { expect, test } from '@playwright/test'
import { mockApiRoute } from './support/api'

test.describe('Checkout público da loja', () => {
  test('identifica o cliente por OTP, confirma o pedido e abre o rastreio público', async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem(
        'maskats.storefront_cart.qa-loja',
        JSON.stringify([
          {
            id: 'cart-item-1',
            product_uuid: 'product-bolo-1',
            name: 'Bolo de cenoura',
            image_url: null,
            quantity: 2,
            configuration_label: null,
            unit_price: 25,
            price: 25,
            promo_price: null,
            wholesale_min_quantity: null,
            wholesale_price: null,
            options: [],
          },
        ]),
      )
    })

    await mockApiRoute(page, {
      path: '/loja/qa-loja',
      body: {
        slug: 'qa-loja',
        name: 'Loja QA',
        logo_url: null,
        business_hours: [],
        estimated_preparation_minutes: 35,
        average_rating: 4.9,
        ratings_count: 12,
        email: 'contato@lojaqa.com',
        phone: '1133334444',
        mobile_phone: '11999998888',
        whatsapp: '11999998888',
        instagram: '@lojaqa',
        facebook: null,
        address: 'Rua das Flores, 100 - Centro',
        address_lat: null,
        address_lng: null,
        accepted_payment_methods: ['cash', 'credit_card'],
        allow_store_pickup: false,
        allow_table_reservations: false,
        storefront_enabled: true,
      },
    })

    await mockApiRoute(page, {
      method: 'POST',
      path: '/portal/auth/request-otp',
      body: null,
      message: 'Código enviado.',
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
            access_token: 'portal-otp-token',
            token_type: 'bearer',
            expires_in: 3600,
          },
          meta: {},
        }),
      })
    })

    await mockApiRoute(page, {
      path: '/portal/me',
      body: {
        uuid: 'portal-customer-1',
        name: 'Maria Cliente',
        email: 'maria@cliente.com',
        linked_stores: [],
      },
    })

    await mockApiRoute(page, {
      path: '/portal/addresses',
      body: [
        {
          client_uuid: 'client-1',
          tenant_name: 'Loja QA',
          tenant_slug: 'qa-loja',
          client_name: 'Maria Cliente',
          client_phone: '11987654321',
          endereco: {
            logradouro: 'Rua das Flores',
            numero: '100',
            complemento: 'Apto 12',
            cep: '01001000',
            estado_uuid: 'estado-sp',
            estado_name: 'Sao Paulo',
            cidade_uuid: 'cidade-sp',
            cidade_name: 'Sao Paulo',
            bairro_uuid: 'bairro-centro',
            bairro_name: 'Centro',
          },
        },
      ],
    })

    await mockApiRoute(page, {
      path: '/portal/cashback',
      body: {
        enabled: false,
        program_name: 'Cashback',
        available_amount: 0,
        pending_amount: 0,
        next_expiration: null,
        earn_percentage: null,
        earn_max_per_order: null,
        redeem_max_percentage: null,
      },
    })

    await mockApiRoute(page, {
      path: '/loja/localizacoes/estados',
      body: [
        {
          uuid: 'estado-sp',
          name: 'Sao Paulo',
          uf: 'SP',
          is_active: true,
          created_at: '2026-01-01T00:00:00Z',
        },
      ],
    })

    await mockApiRoute(page, {
      path: '/loja/localizacoes/cidades',
      body: [
        {
          uuid: 'cidade-sp',
          name: 'Sao Paulo',
          is_active: true,
          estado_uuid: 'estado-sp',
          estado_name: 'Sao Paulo',
          created_at: '2026-01-01T00:00:00Z',
        },
      ],
    })

    await mockApiRoute(page, {
      path: '/loja/localizacoes/bairros',
      body: [
        {
          uuid: 'bairro-centro',
          name: 'Centro',
          is_active: true,
          cidade_uuid: 'cidade-sp',
          cidade_name: 'Sao Paulo',
          created_at: '2026-01-01T00:00:00Z',
        },
      ],
    })

    await mockApiRoute(page, {
      path: '/loja/qa-loja/taxa-entrega/bairro-centro',
      body: {
        fee: 8,
      },
    })

    await page.route('**/api/v1/loja/qa-loja/checkout*', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Pedido criado com sucesso.',
          data: {
            order: {
              uuid: 'tracking-order-1',
            },
          },
          meta: {},
        }),
      })
    })

    await mockApiRoute(page, {
      path: '/rastreio/tracking-order-1',
      body: {
        uuid: 'tracking-order-1',
        tenant_name: 'Loja QA',
        client_name: 'Maria Cliente',
        is_installment: false,
        total_amount: '58.00',
        is_paid: false,
        paid_at: null,
        is_delivered: false,
        delivered_at: null,
        is_out_for_delivery: false,
        out_for_delivery_at: null,
        status: 'confirmed',
        expected_delivery_date: '2026-07-28',
        is_cancelled: false,
        created_at: '2026-07-28T18:30:00Z',
        items: [
          {
            product_name: 'Bolo de cenoura',
            quantity: '2',
            unit: null,
            unit_price: '25.00',
            line_total: '50.00',
          },
        ],
        installments: [],
      },
    })

    await page.goto('/loja/qa-loja/checkout')

    await page.getByRole('button', { name: 'Sim, tenho 18 anos ou mais' }).click()
    await expect(page.getByText('Identifique-se para continuar')).toBeVisible()
    await page.getByRole('textbox', { name: 'E-mail' }).fill('maria@cliente.com')
    await page.getByRole('button', { name: 'Receber código por e-mail' }).click()

    await expect(page.getByText('Enviamos um código de 6 dígitos para maria@cliente.com.')).toBeVisible()
    await page.getByRole('textbox', { name: 'Código de 6 dígitos' }).fill('123456')
    await page.getByRole('button', { name: 'Confirmar código' }).click()

    await expect(page.getByText('Dados de entrega')).toBeVisible()
    await expect(page.getByRole('textbox', { name: 'Seu nome' })).toHaveValue('Maria Cliente')
    await page.getByRole('textbox', { name: 'Sobrenome' }).fill('Cliente Sobrenome')
    await expect(page.getByRole('textbox', { name: 'Telefone (com DDD)' })).toHaveValue('11987654321')
    await expect(page.getByRole('textbox', { name: 'Logradouro' })).toHaveValue('Rua das Flores')
    await expect(page.getByRole('textbox', { name: 'Número' })).toHaveValue('100')
    await expect(page.getByRole('textbox', { name: 'Complemento' })).toHaveValue('Apto 12')

    await expect(page.getByText('R$ 8,00')).toBeVisible()
    await expect(page.getByText('R$ 58,00')).toBeVisible()

    await page.getByLabel('Como você pretende pagar').click()
    await page.getByRole('option', { name: 'Dinheiro' }).click()

    await page.getByRole('button', { name: 'Confirmar pedido' }).click()

    await expect(page).toHaveURL(/\/rastreio\/tracking-order-1$/)
    await expect(page.getByText('Pedido em preparação')).toBeVisible()
    await expect(page.getByText('Loja QA')).toBeVisible()
    await expect(page.getByText('Maria Cliente')).toBeVisible()
    await expect(page.getByText('R$ 58,00')).toBeVisible()
    await expect
      .poll(() => page.evaluate(() => localStorage.getItem('maskats.storefront_cart.qa-loja')))
      .toBe('[]')
  })
})
