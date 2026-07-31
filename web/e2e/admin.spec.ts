import { expect, test } from '@playwright/test'
import { mockAuthenticatedShell, mockPaginatedApiRoute } from './support/api'

test.describe('Administração global', () => {
  test('carrega os módulos principais de administração com permissões globais', async ({ page }) => {
    test.slow()

    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      globalPermissions: ['users:read', 'users:create', 'groups:read', 'groups:create', 'tenants:read', 'tenants:create'],
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard'],
      userName: 'Admin QA',
      userEmail: 'admin.qa@pegaticket.com',
    })

    await mockPaginatedApiRoute(page, {
      path: '/users',
      body: [
        {
          uuid: 'admin-user-1',
          name: 'Administrador Master',
          email: 'master@pegaticket.com',
          is_active: true,
          created_at: '2026-07-28T12:00:00Z',
          groups: [],
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    })

    await mockPaginatedApiRoute(page, {
      path: '/groups',
      body: [
        {
          uuid: 'admin-group-1',
          name: 'Administradores',
          is_active: true,
          created_at: '2026-07-28T12:00:00Z',
          users_count: 1,
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    })

    await mockPaginatedApiRoute(page, {
      path: '/tenants',
      body: [
        {
          uuid: 'tenant-admin-1',
          name: 'Empresa Alpha',
          plan_name: 'Diamante',
          trial_ends_at: '2026-08-15',
          is_active: true,
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

    await page.goto('/admin/usuarios')

    await expect(page.getByRole('heading', { name: 'Usuários' })).toBeVisible()
    await expect(page.getByText('Gerencie os usuários do sistema e seus grupos.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Novo usuário' })).toBeVisible()
    await expect(page.getByText('Administrador Master')).toBeVisible()
    await expect(page.getByText('master@pegaticket.com')).toBeVisible()

    await page.goto('/admin/grupos')

    await expect(page.getByRole('heading', { name: 'Grupos' })).toBeVisible()
    await expect(page.getByText('Organize permissões sistêmicas por grupos.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Novo grupo' })).toBeVisible()
    await expect(page.getByText('Administradores')).toBeVisible()

    await page.goto('/admin/tenants')

    await expect(page.getByRole('heading', { name: 'Empresas' })).toBeVisible()
    await expect(page.getByText('Gerencie as empresas da plataforma.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Nova empresa' })).toBeVisible()
    await expect(page.getByText('Empresa Alpha')).toBeVisible()
    await expect(page.getByText('Diamante')).toBeVisible()
  })

  test('abre os detalhes da auditoria administrativa', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      globalPermissions: ['audit_logs:read'],
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard'],
      userName: 'Audit QA',
      userEmail: 'audit.qa@pegaticket.com',
    })

    await mockPaginatedApiRoute(page, {
      path: '/audit-logs',
      body: [
        {
          uuid: 'audit-log-1',
          created_at: '2026-07-28T15:20:00Z',
          user_name: 'Audit QA',
          event: 'updated',
          auditable_type: 'App\\Models\\Tenant\\Tenant',
          route: '/api/v1/tenants/tenant-admin-1',
          method: 'PUT',
          ip: '127.0.0.1',
          old_values: { name: 'Empresa Beta' },
          new_values: { name: 'Empresa Alpha' },
          meta: { source: 'admin-panel' },
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 20,
        total: 1,
        last_page: 1,
      },
    })

    await page.goto('/admin/auditoria')

    await expect(page.getByRole('heading', { name: 'Auditoria' })).toBeVisible()
    await expect(page.getByText('Consulte o histórico de alterações registradas no sistema.')).toBeVisible()
    await expect(page.getByText('Audit QA')).toBeVisible()
    await expect(page.getByText('Atualizado')).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Tenant', exact: true })).toBeVisible()

    await page.getByRole('button', { name: 'Ver detalhes' }).click()

    await expect(page.getByRole('dialog')).toBeVisible()
    await expect(page.getByText('Detalhes da auditoria')).toBeVisible()
    await expect(page.getByText('"Empresa Beta"')).toBeVisible()
    await expect(page.getByText('"Empresa Alpha"')).toBeVisible()
    await expect(page.getByText('"admin-panel"')).toBeVisible()
  })

  test('lista pendências de pagamento, filtra por tipo e permite reprocessar um item elegível', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      globalPermissions: ['payment_admin:read', 'payment_admin:update'],
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard'],
      userName: 'Finance Admin',
      userEmail: 'finance.admin@pegaticket.com',
    })

    let currentTypeFilter = ''

    await page.route('**/api/v1/payments/issues*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      const url = new URL(route.request().url())
      currentTypeFilter = url.searchParams.get('type') ?? ''

      const baseItems = [
        {
          issue_type: 'webhook_failed',
          reference: 'wh_001',
          tenant: { uuid: 'tenant-pay-1', name: 'Empresa Financeira' },
          amount: '89.90',
          status: 'pending',
          occurred_at: '2026-07-28T14:00:00Z',
          reprocessable: true,
          detail: { source: 'mercado-pago' },
        },
        {
          issue_type: 'invoice_disputed',
          reference: 'inv_002',
          tenant: { uuid: 'tenant-pay-2', name: 'Empresa Contestação' },
          amount: '159.00',
          status: 'open',
          occurred_at: '2026-07-28T13:00:00Z',
          reprocessable: false,
          detail: { invoice_uuid: 'invoice-2' },
        },
      ]

      const items = currentTypeFilter
        ? baseItems.filter((item) => item.issue_type === currentTypeFilter)
        : baseItems

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: items,
          meta: {
            pagination: {
              current_page: 1,
              per_page: 20,
              total: items.length,
              last_page: 1,
            },
          },
        }),
      })
    })

    await page.route('**/api/v1/payments/issues/wh_001/reprocess', async (route) => {
      if (route.request().method() !== 'POST') {
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
            reference: 'wh_001',
            status: 'reprocessed',
          },
          meta: {},
        }),
      })
    })

    await page.goto('/admin/pagamentos-pendencias')

    await expect(page.getByRole('heading', { name: 'Pendências de pagamento' })).toBeVisible()
    await expect(page.getByText('Acompanhe e resolva itens travados de pagamento e assinatura entre todas as empresas.')).toBeVisible()
    await expect(page.getByText('Empresa Financeira')).toBeVisible()
    await expect(page.getByText('Empresa Contestação')).toBeVisible()
    await expect(page.getByText('Falha no processamento')).toBeVisible()
    await expect(page.getByText('Fatura contestada')).toBeVisible()

    await page.getByLabel('Tipo').click()
    await page.getByRole('option', { name: 'Falha no processamento' }).click()

    await expect.poll(() => currentTypeFilter).toBe('webhook_failed')
    await expect(page.getByText('Empresa Financeira')).toBeVisible()
    await expect(page.getByText('Empresa Contestação')).toHaveCount(0)

    const row = page.locator('.ag-row').filter({ hasText: 'Empresa Financeira' }).first()
    await row.getByRole('button').click()

    const reprocessDialog = page.getByRole('dialog', { name: 'Reprocessar pendência' })
    await expect(reprocessDialog).toBeVisible()
    await expect(reprocessDialog.getByText('da empresa')).toBeVisible()
    await expect(reprocessDialog.getByText('Empresa Financeira', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'Reprocessar' }).click()

    await expect(page.getByText('Pendência reprocessada com sucesso.')).toBeVisible()
    await expect(page.getByRole('dialog', { name: 'Reprocessar pendência' })).toHaveCount(0)
  })
})
