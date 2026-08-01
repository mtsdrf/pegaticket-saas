import { expect, test } from '@playwright/test'
import { mockPaginatedApiRoute, mockAuthenticatedShell } from './support/api'

test.describe('Central de treinamento', () => {
  test('abre com o contexto da empresa, preserva o progresso e permite entrar na área real do módulo', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      activeTenantUuid: 'tenant-training-1',
      isTenantOwner: true,
      tenantPermissions: ['dashboard:read', 'events:read', 'events:create', 'sales:read'],
      tenantFunctionalities: ['dashboard', 'events', 'sales', 'subscription'],
      userName: 'Treinamento QA',
      userEmail: 'training@pegaticket.com',
      tenants: [
        {
          tenant_uuid: 'tenant-training-1',
          tenant_name: 'Empresa Escola',
          role: 'Proprietário',
          role_slug: 'owner',
          plan_slug: 'pegaticket',
          plan_name: 'PegaTicket',
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
      path: '/events',
      body: [
        {
          uuid: 'event-qa-1',
          name: 'Festival Escola',
          slug: 'festival-escola',
          type: 'ingresso',
          status: 'publicado',
          location_name: 'Arena Escola',
          location_address: 'Rua do Evento, 100',
          starts_at: '2026-08-20 20:00:00',
          ends_at: '2026-08-21 02:00:00',
          cover_image_url: null,
          category: {
            uuid: 'event-category-1',
            name: 'Festival',
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
    await expect(page.getByText('PegaTicket')).toBeVisible()
    await expect(page.getByText('2 de 5 marcos concluídos')).toBeVisible()
    await expect(page.getByText('40%')).toBeVisible()
    await expect(page.getByText('Implantação guiada da empresa', { exact: true }).first()).toBeVisible()

    await page.getByText('Eventos e catálogo').first().click()
    await expect(page.getByRole('link', { name: 'Abrir área real' })).toBeVisible()
    await expect(page.getByText('Cadastrar evento operacional')).toBeVisible()

    await page.getByRole('button', { name: 'Marcar como concluído' }).click()
    await expect(page.getByText('Concluído por você')).toBeVisible()

    await expect
      .poll(() =>
        page.evaluate(() => {
          const key = 'pegaticket.training_center_progress.user-qa-1.tenant-training-1'
          return localStorage.getItem(key)
        }),
      )
      .not.toBeNull()

    await page.getByRole('link', { name: 'Abrir área real' }).click()

    await expect(page).toHaveURL(/\/eventos$/)
    await expect(page.getByRole('heading', { name: 'Eventos' })).toBeVisible()
    await expect(page.getByText('Festival Escola')).toBeVisible()
    await expect
      .poll(() => page.evaluate(() => localStorage.getItem('pegaticket.active_tenant_uuid')))
      .toBe('tenant-training-1')

    await page.goto('/treinamentos')

    await expect(page.getByRole('heading', { name: 'Central de Treinamento' })).toBeVisible()
    await expect(page.getByText('Eventos e catálogo').first()).toBeVisible()
    await expect(page.getByText('Concluído por você')).toBeVisible()
  })
})
