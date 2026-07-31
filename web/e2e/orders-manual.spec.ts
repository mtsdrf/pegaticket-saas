import { expect, test } from '@playwright/test'
import { makeOrder, mockAuthenticatedShell, mockPaginatedApiRoute } from './support/api'

test.describe('Pedidos manuais', () => {
  test('mostra o estado vazio focado em operação manual e sem filtros da central', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['orders:read', 'orders:create'],
      tenantFunctionalities: ['orders'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/orders',
      body: [],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
      },
    })

    await page.goto('/pedidos-manuais')

    await expect(page.getByRole('heading', { name: 'Pedidos manuais' })).toBeVisible()
    await expect(page.getByText('Gerencie os pedidos lançados manualmente pela equipe.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Novo pedido' })).toBeVisible()
    await expect(page.getByText('Nenhum pedido manual encontrado')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Criar primeiro pedido' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Central de operação' })).toHaveCount(0)
    await expect(page.getByText('Ativos apenas')).toHaveCount(0)
    await expect(page.getByText('Aguardando aprovação')).toHaveCount(0)
  })

  test('lista pedidos manuais sem depender dos cards operacionais da central', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['orders:read', 'orders:create', 'orders:update'],
      tenantFunctionalities: ['orders'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/orders',
      body: [
        makeOrder({
          uuid: 'order-qa-manual-1',
          codigo: '1042',
          total_amount: 239.5,
          client: {
            uuid: 'client-qa-42',
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

    await page.goto('/pedidos-manuais')

    await expect(page.getByRole('heading', { name: 'Pedidos manuais' })).toBeVisible()
    await expect(page.getByText('Maria da Silva')).toBeVisible()
    await expect(page.getByText('1042')).toBeVisible()
    await expect(page.getByText('Manual')).toBeVisible()
    await expect(page.getByText('Central de operação')).toHaveCount(0)
    await expect(page.getByText('Nenhum pedido manual encontrado')).toHaveCount(0)
  })

  test('cria um novo pedido manual com cliente e produto e volta para a lista geral', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['orders:read', 'orders:create'],
      tenantFunctionalities: ['orders'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/orders',
      body: [
        makeOrder({
          uuid: 'order-manual-created-1',
          codigo: '2050',
          total_amount: 39.8,
          client: {
            uuid: 'client-order-1',
            name: 'Cliente Pedido Manual',
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

    await mockPaginatedApiRoute(page, {
      path: '/stock-locations',
      body: [
        {
          uuid: 'stock-1',
          name: 'Estoque central',
          type: 'warehouse',
          address: 'Rua das Flores, 100',
          is_default: true,
          is_active: true,
          created_at: '2026-07-28T12:00:00Z',
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 100,
        total: 1,
        last_page: 1,
      },
    })

    await page.route('**/api/v1/clients*', async (route) => {
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
              uuid: 'client-order-1',
              name: 'Cliente Pedido Manual',
              phone_primary: null,
              phone_secondary: null,
              cpf_cnpj: '12345678901',
              ie: null,
              ie_indicator: 'nao_contribuinte',
              notes: null,
              is_trusted: true,
              is_active: true,
              endereco: {
                uuid: 'addr-1',
                logradouro: 'Rua A',
                numero: '10',
                complemento: null,
                cep: '01001000',
                estado_uuid: 'state-1',
                estado_name: 'São Paulo',
                cidade_uuid: 'city-1',
                cidade_name: 'São Paulo',
                bairro_uuid: 'district-1',
                bairro_name: 'Centro',
                lat: null,
                lng: null,
              },
              dia_ideal: null,
              periodo_ideal: null,
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
      path: '/products',
      body: [
        {
          uuid: 'product-order-1',
          name: 'Produto Pedido Manual',
          sku: 'PROD-001',
          barcode: null,
          brand: null,
          ncm: null,
          cest: null,
          origin: null,
          default_cfop: null,
          csosn_cst: null,
          unit: 'un',
          price: 19.9,
          description: null,
          image_url: null,
          is_available: true,
          stock_quantity: 100,
          surcharge_rate: null,
          is_lot_controlled: false,
          is_expiry_controlled: false,
          is_serial_controlled: false,
          min_stock: null,
          max_stock: null,
          reorder_point: null,
          reorder_qty: null,
          product_type: null,
          option_groups: [],
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

    await page.route('**/api/v1/products/product-order-1/suggested-price*', async (route) => {
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
          data: { price: '19.90' },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/products/product-order-1', async (route) => {
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
            uuid: 'product-order-1',
            name: 'Produto Pedido Manual',
            sku: 'PROD-001',
            barcode: null,
            brand: null,
            ncm: null,
            cest: null,
            origin: null,
            default_cfop: null,
            csosn_cst: null,
            unit: 'un',
            price: 19.9,
            description: null,
            image_url: null,
            is_available: true,
            stock_quantity: 100,
            surcharge_rate: null,
            is_lot_controlled: false,
            is_expiry_controlled: false,
            is_serial_controlled: false,
            min_stock: null,
            max_stock: null,
            reorder_point: null,
            reorder_qty: null,
            product_type: null,
            option_groups: [],
            created_at: '2026-07-28T12:00:00Z',
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/orders', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      const payload = JSON.parse(route.request().postData() ?? '{}')
      expect(payload).toMatchObject({
        client_uuid: 'client-order-1',
        stock_location_uuid: 'stock-1',
        is_installment: false,
        mark_as_delivered: true,
        mark_as_paid: false,
        items: [
          {
            product_uuid: 'product-order-1',
            quantity: 2,
            unit_price: 19.9,
            options: [],
          },
        ],
      })

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Pedido criado com sucesso.',
          data: makeOrder({
            uuid: 'order-manual-created-1',
            codigo: '2050',
            total_amount: 39.8,
            client: {
              uuid: 'client-order-1',
              name: 'Cliente Pedido Manual',
            },
            stock_location: {
              uuid: 'stock-1',
              name: 'Estoque central',
            },
          }),
          meta: {},
        }),
      })
    })

    await page.goto('/pedidos/novo')

    await expect(page.getByRole('heading', { name: 'Novo pedido' })).toBeVisible()

    await page.getByRole('combobox', { name: 'Cliente' }).click()
    await page.getByRole('combobox', { name: 'Cliente' }).fill('Cliente Pedido')
    await page.getByRole('option', { name: 'Cliente Pedido Manual' }).click()

    await page.getByLabel('Local de estoque').click()
    await page.getByRole('option', { name: 'Estoque central' }).click()

    await page.getByRole('combobox', { name: 'Produto' }).click()
    await page.getByRole('combobox', { name: 'Produto' }).fill('Produto Pedido')
    await page.getByRole('option', { name: 'Produto Pedido Manual' }).click()

    await page.getByLabel('Quantidade').fill('2')

    await expect(page.getByText('Total do pedido')).toBeVisible()
    await expect(page.getByText('R$ 39,80')).toHaveCount(2)

    await page.getByRole('button', { name: 'Salvar' }).click()

    await page.waitForURL('**/pedidos')
    await expect(page.getByText('2050').first()).toBeVisible()
    await expect(page.getByText('Cliente Pedido Manual').first()).toBeVisible()
  })
})
