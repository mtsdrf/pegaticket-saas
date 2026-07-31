import { expect, test } from '@playwright/test'
import { mockPaginatedApiRoute, mockAuthenticatedShell } from './support/api'

test.describe('Central de treinamento', () => {
  test('abre com o contexto da empresa, preserva o progresso e permite entrar na área real do módulo', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      activeTenantUuid: 'tenant-training-1',
      isTenantOwner: true,
      tenantPermissions: ['dashboard:read', 'products:read', 'products:create', 'clients:read', 'orders:read', 'stock:read'],
      tenantFunctionalities: ['dashboard', 'products', 'clients', 'orders', 'stock', 'subscription'],
      userName: 'Treinamento QA',
      userEmail: 'training@maskats.com',
      tenants: [
        {
          tenant_uuid: 'tenant-training-1',
          tenant_name: 'Empresa Escola',
          role: 'Proprietário',
          role_slug: 'owner',
          plan_slug: 'diamante',
          plan_name: 'Diamante',
        },
      ],
    })

    await page.route('**/api/v1/onboarding/checklist*', async (route) => {
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
            has_product: false,
            has_client: true,
            has_first_order: false,
            has_store_address: true,
            storefront_configured: false,
            steps: [],
            is_dismissed: false,
            dismissed_at: null,
            completed: 2,
            total: 5,
          },
          meta: {},
        }),
      })
    })

    await mockPaginatedApiRoute(page, {
      path: '/products',
      body: [
        {
          uuid: 'product-qa-1',
          name: 'Produto Escola',
          sku: 'ESC-001',
          price: 14.9,
          is_available: true,
          image_url: null,
          unit: 'un',
          product_type: {
            uuid: 'product-type-1',
            name: 'Bebidas',
            product_category: {
              uuid: 'product-category-1',
              name: 'Consumo',
            },
          },
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 25,
        total: 1,
        last_page: 1,
      },
    })

    await page.goto('/treinamentos')

    await expect(page.getByRole('heading', { name: 'Central de Treinamento' })).toBeVisible()
    await expect(page.getByText('Empresa Escola')).toBeVisible()
    await expect(page.getByText('Diamante')).toBeVisible()
    await expect(page.getByText('2 de 5 marcos concluídos')).toBeVisible()
    await expect(page.getByText('40%')).toBeVisible()
    await expect(page.getByText('Implantação guiada da empresa', { exact: true }).first()).toBeVisible()

    await page.getByText('Produtos e catálogo').first().click()
    await expect(page.getByRole('link', { name: 'Abrir área real' })).toBeVisible()
    await expect(page.getByText('Cadastrar produto operacional')).toBeVisible()

    await page.getByRole('button', { name: 'Marcar como concluído' }).click()
    await expect(page.getByText('Concluído por você')).toBeVisible()

    await expect
      .poll(() =>
        page.evaluate(() => {
          const key = 'maskats.training_center_progress.user-qa-1.tenant-training-1'
          return localStorage.getItem(key)
        }),
      )
      .not.toBeNull()

    await page.getByRole('link', { name: 'Abrir área real' }).click()

    await expect(page).toHaveURL(/\/produtos$/)
    await expect(page.getByRole('heading', { name: 'Produtos' })).toBeVisible()
    await expect(page.getByText('Produto Escola')).toBeVisible()
    await expect
      .poll(() => page.evaluate(() => localStorage.getItem('maskats.active_tenant_uuid')))
      .toBe('tenant-training-1')

    await page.goto('/treinamentos')

    await expect(page.getByRole('heading', { name: 'Central de Treinamento' })).toBeVisible()
    await expect(page.getByText('Produtos e catálogo').first()).toBeVisible()
    await expect(page.getByText('Concluído por você')).toBeVisible()
  })
})
