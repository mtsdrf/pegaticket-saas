import { expect, test } from '@playwright/test'
import { makeOrder, mockAuthenticatedShell, mockPaginatedApiRoute } from './support/api'

test.describe('Vendas online', () => {
  test('mostra fila online com board e grid sem depender da central manual', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['storefront-sales:read', 'orders:update'],
      tenantFunctionalities: ['storefront-sales'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/storefront-sales',
      body: [
        makeOrder({
          uuid: 'storefront-order-1',
          codigo: '2001',
          status: 'pending_approval',
          origin: 'storefront',
          total_amount: 89.9,
          client: {
            uuid: 'client-storefront-1',
            name: 'Cliente Online QA',
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

    await page.goto('/vendas-online')

    await expect(page.getByRole('heading', { name: 'Vendas online' })).toBeVisible()
    await expect(page.getByText('Acompanhe e gerencie os pedidos recebidos pelo canal online.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Aprovação' })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Cliente Online QA', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: '2001', exact: true })).toBeVisible()
    await expect(page.getByText('Pedidos manuais')).toHaveCount(0)
    await expect(page.getByText('Nenhum pedido pendente de ação no momento')).toHaveCount(0)
  })

  test('move pedidos no board entre etapas e exige motivo ao cancelar', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['storefront-sales:read', 'orders:update'],
      tenantFunctionalities: ['storefront-sales'],
    })

    let boardOrders = [
      makeOrder({
        uuid: 'storefront-order-approval-1',
        codigo: '2101',
        status: 'pending_approval',
        origin: 'storefront',
        total_amount: 64.9,
        client: {
          uuid: 'client-storefront-1',
          name: 'Cliente Aprovação',
        },
      }),
      makeOrder({
        uuid: 'storefront-order-production-1',
        codigo: '2102',
        status: 'confirmed',
        origin: 'storefront',
        total_amount: 84.5,
        client: {
          uuid: 'client-storefront-2',
          name: 'Cliente Produção',
        },
      }),
    ]
    await page.route('**/api/v1/storefront-sales/storefront-order-approval-1/approve', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      boardOrders = boardOrders.map((order) =>
        order.uuid === 'storefront-order-approval-1'
          ? {
              ...order,
              status: 'confirmed',
            }
          : order,
      )

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: boardOrders.find((order) => order.uuid === 'storefront-order-approval-1'),
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/storefront-sales/storefront-order-production-1/cancel', async (route) => {
      if (route.request().method() !== 'PATCH') {
        await route.fallback()
        return
      }

      const cancelled = boardOrders.find((order) => order.uuid === 'storefront-order-production-1')
      boardOrders = boardOrders.filter((order) => order.uuid !== 'storefront-order-production-1')

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            ...cancelled,
            cancelled_at: '2026-07-28T12:30:00Z',
            cancellation_reason: 'Cliente pediu cancelamento antes da saída.',
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/storefront-sales*', async (route) => {
      const url = route.request().url()
      const method = route.request().method()

      if (method === 'GET' && /\/api\/v1\/storefront-sales(\?.*)?$/.test(url)) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'OK',
            data: boardOrders,
            meta: {
              pagination: {
                current_page: 1,
                per_page: 50,
                total: boardOrders.length,
                last_page: 1,
              },
            },
          }),
        })
        return
      }

      await route.fallback()
    })

    await page.goto('/vendas-online')

    const approvalCard = page.locator('div[draggable="true"]').filter({ hasText: '2101' })
    const productionCard = page.locator('div[draggable="true"]').filter({ hasText: '2102' })

    await expect(approvalCard).toBeVisible()
    await expect(productionCard).toBeVisible()

    const approvalDragData = await page.evaluateHandle(() => new DataTransfer())
    const approveResponse = page.waitForResponse(
      (response) =>
        response.url().includes('/storefront-sales/storefront-order-approval-1/approve')
        && response.request().method() === 'POST',
    )
    await approvalCard.dispatchEvent('dragstart', { dataTransfer: approvalDragData })
    await page.getByRole('paragraph').filter({ hasText: /^Produção$/ }).dispatchEvent('dragover', { dataTransfer: approvalDragData })
    await page.getByRole('paragraph').filter({ hasText: /^Produção$/ }).dispatchEvent('drop', { dataTransfer: approvalDragData })
    await approvalCard.dispatchEvent('dragend', { dataTransfer: approvalDragData })
    await approveResponse

    await expect(page.getByText('2 pedido(s)').first()).toBeVisible()
    await expect(page.getByText('Não foi possível mover o pedido agora.')).toHaveCount(0)

    const productionDragData = await page.evaluateHandle(() => new DataTransfer())
    await productionCard.dispatchEvent('dragstart', { dataTransfer: productionDragData })
    await page.getByText('Cancelar / recusar', { exact: true }).dispatchEvent('dragover', { dataTransfer: productionDragData })
    await page.getByText('Cancelar / recusar', { exact: true }).dispatchEvent('drop', { dataTransfer: productionDragData })

    await expect(page.getByRole('dialog')).toBeVisible()
    await expect(page.getByText('Informe o motivo do cancelamento.')).toBeVisible()
    await page.getByLabel('Motivo').fill('Cliente pediu cancelamento antes da saída.')
    const cancelResponse = page.waitForResponse(
      (response) =>
        response.url().includes('/storefront-sales/storefront-order-production-1/cancel')
        && response.request().method() === 'PATCH',
    )
    await page.getByRole('button', { name: 'Cancelar pedido' }).click()
    await cancelResponse

    await expect(page.locator('div[draggable="true"]').filter({ hasText: '2102' })).toHaveCount(0)
    await expect(page.locator('div[draggable="true"]').filter({ hasText: 'Cliente Produção' })).toHaveCount(0)
  })

  test('aprova ou rejeita solicitações de cancelamento com reflexo operacional na fila', async ({ page }) => {
    test.slow()

    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['storefront-sales:read', 'orders:update'],
      tenantFunctionalities: ['storefront-sales'],
    })

    let storefrontOrders = [
      makeOrder({
        uuid: 'storefront-cancel-approve-1',
        codigo: '2201',
        status: 'cancellation_requested',
        origin: 'storefront',
        total_amount: 54.9,
        cancellation_reason: 'Cliente desistiu da compra',
        client: {
          uuid: 'client-storefront-approve-1',
          name: 'Cliente Aprovar Cancelamento',
        },
      }),
      makeOrder({
        uuid: 'storefront-cancel-reject-1',
        codigo: '2202',
        status: 'cancellation_requested',
        origin: 'storefront',
        total_amount: 74.9,
        cancellation_reason: 'Vai buscar mais tarde',
        client: {
          uuid: 'client-storefront-reject-1',
          name: 'Cliente Rejeitar Cancelamento',
        },
      }),
    ]

    await page.route('**/api/v1/sales/storefront-cancel-approve-1/approve-cancellation', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      const approved = storefrontOrders.find((order) => order.uuid === 'storefront-cancel-approve-1')
      storefrontOrders = storefrontOrders.filter((order) => order.uuid !== 'storefront-cancel-approve-1')

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: {
            ...approved,
            cancelled_at: '2026-07-28T16:10:00Z',
            status: 'cancelled',
          },
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/sales/storefront-cancel-reject-1/reject-cancellation', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      storefrontOrders = storefrontOrders.map((order) =>
        order.uuid === 'storefront-cancel-reject-1'
          ? {
              ...order,
              status: 'confirmed',
              cancellation_reason: null,
            }
          : order,
      )

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: storefrontOrders.find((order) => order.uuid === 'storefront-cancel-reject-1'),
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/storefront-sales/storefront-cancel-approve-1*', async (route) => {
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
          data: storefrontOrders.find((item) => item.uuid === 'storefront-cancel-approve-1'),
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/storefront-sales/storefront-cancel-reject-1*', async (route) => {
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
          data: storefrontOrders.find((item) => item.uuid === 'storefront-cancel-reject-1'),
          meta: {},
        }),
      })
    })

    await page.route('**/api/v1/storefront-sales*', async (route) => {
      const url = route.request().url()
      const method = route.request().method()

      if (method === 'GET' && /\/api\/v1\/storefront-sales(\?.*)?$/.test(url)) {
        const query = new URL(url).searchParams
        const filteredOrders = query.get('status') === 'cancellation_requested'
          ? storefrontOrders.filter((order) => order.status === 'cancellation_requested')
          : storefrontOrders

        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            success: true,
            message: 'OK',
            data: filteredOrders,
            meta: {
              pagination: {
                current_page: 1,
                per_page: 50,
                total: filteredOrders.length,
                last_page: 1,
              },
            },
          }),
        })
        return
      }

      await route.fallback()
    })

    await page.goto('/vendas-online')

    await expect(page.getByRole('gridcell', { name: 'Cliente Aprovar Cancelamento', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Cliente Rejeitar Cancelamento', exact: true })).toBeVisible()

    await page.getByRole('button', { name: /Gerenciar pedido do cliente Cliente Aprovar Cancelamento/ }).click()
    await expect(page.getByText('O cliente solicitou o cancelamento deste pedido: "Cliente desistiu da compra"')).toBeVisible()
    await page.getByRole('button', { name: 'Aprovar cancelamento' }).click()
    await expect(page.getByText('Ao aprovar, o pedido é cancelado de verdade agora')).toBeVisible()
    const approveResponse = page.waitForResponse(
      (response) =>
        response.url().includes('/orders/storefront-cancel-approve-1/approve-cancellation')
        && response.request().method() === 'POST',
    )
    await page.getByRole('button', { name: 'Confirmar' }).click()
    await approveResponse
    await page.getByRole('button', { name: 'Fechar' }).click()

    await expect(page.getByRole('gridcell', { name: 'Cliente Aprovar Cancelamento', exact: true })).toHaveCount(0)
    await expect(page.locator('div[draggable="true"]').filter({ hasText: '2201' })).toHaveCount(0)

    await page.getByRole('button', { name: /Gerenciar pedido do cliente Cliente Rejeitar Cancelamento/ }).click()
    await expect(page.getByText('O cliente solicitou o cancelamento deste pedido: "Vai buscar mais tarde"')).toBeVisible()
    const rejectResponse = page.waitForResponse(
      (response) =>
        response.url().includes('/orders/storefront-cancel-reject-1/reject-cancellation')
        && response.request().method() === 'POST',
    )
    await page.getByRole('button', { name: 'Rejeitar cancelamento' }).click()
    await page.getByRole('button', { name: 'Confirmar' }).click()
    await rejectResponse

    await expect(page.getByText('Cancelamento solicitado', { exact: true })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Cancelar pedido' })).toBeVisible()
    await page.mouse.click(10, 10)
    await expect(page.getByRole('dialog')).toHaveCount(0)

    await expect(page.getByRole('gridcell', { name: 'Cliente Rejeitar Cancelamento', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Cancelamento solicitado', exact: true })).toHaveCount(0)

    await page.getByRole('button', { name: 'Só cancelamento pendente' }).click()
    await expect(page.getByText('Nenhum cancelamento pendente no momento')).toBeVisible()
  })
})
