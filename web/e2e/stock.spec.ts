import { expect, test } from '@playwright/test'
import { mockAuthenticatedShell, mockPaginatedApiRoute } from './support/api'

test.describe('Estoque', () => {
  test('mostra estado vazio em saldos quando ainda não há posição registrada', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['stock:read'],
      tenantFunctionalities: ['stock'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/stock/balances',
      body: [],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
      },
    })

    await page.goto('/estoque/saldos')

    await expect(page.getByRole('heading', { name: 'Saldos de estoque' })).toBeVisible()
    await expect(page.getByText('Acompanhe a posição atual por produto e local.')).toBeVisible()
    await expect(page.getByText('Nenhum saldo encontrado')).toBeVisible()
    await expect(page.getByText('Os saldos aparecem conforme as movimentações forem sendo registradas.')).toBeVisible()
  })

  test('lista movimentações de estoque e permite iniciar uma nova operação', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['stock:read', 'stock:create'],
      tenantFunctionalities: ['stock'],
    })

    await mockPaginatedApiRoute(page, {
      path: '/stock/movements',
      body: [
        {
          uuid: 'stock-movement-1',
          type: 'transfer',
          quantity: 8,
          balance_before: 24,
          balance_after: 16,
          reason: 'Reposição da loja',
          notes: 'Mover para operação de vitrine',
          source_type: null,
          source_id: null,
          product: {
            uuid: 'product-stock-1',
            name: 'Água Mineral 500ml',
            sku: 'AGUA-500',
          },
          location: {
            uuid: 'stock-origin-1',
            name: 'Estoque central',
          },
          destination_location: {
            uuid: 'stock-destination-1',
            name: 'Loja física',
          },
          created_at: '2026-07-28T15:20:00Z',
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    })

    await page.goto('/estoque/movimentos')

    await expect(page.getByRole('heading', { name: 'Movimentações de estoque' })).toBeVisible()
    await expect(page.getByText('Registre entradas, saídas, reservas, transferências e ajustes.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Nova movimentação' })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Transferência', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Água Mineral 500ml', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Estoque central', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Loja física', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Reposição da loja', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: '8', exact: true })).toBeVisible()

    await page.getByRole('button', { name: 'Nova movimentação' }).click()
    await expect(page).toHaveURL(/\/estoque\/movimentos\/nova$/)
    await expect(page.getByRole('heading', { name: 'Nova movimentação de estoque' })).toBeVisible()
  })
})
