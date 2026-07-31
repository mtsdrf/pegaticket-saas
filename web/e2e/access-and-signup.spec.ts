import { expect, test, type Page } from '@playwright/test'
import { mockApiRoute, mockAuthenticatedApiBootstrap, mockAuthenticatedShell } from './support/api'

test.describe('Acesso, empresa ativa e cadastro self-service', () => {
  function tenantTrigger(page: Page) {
    return page.getByRole('menu').locator('button').filter({ hasText: /Empresa QA [12]/ }).first()
  }

  test('permite trocar a empresa ativa pelo seletor lateral sem perder o contexto', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      activeTenantUuid: 'tenant-qa-1',
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard'],
      tenants: [
        {
          tenant_uuid: 'tenant-qa-1',
          tenant_name: 'Empresa QA 1',
          role: 'Operador',
          role_slug: 'operator',
          plan_slug: 'diamante',
          plan_name: 'Diamante',
        },
        {
          tenant_uuid: 'tenant-qa-2',
          tenant_name: 'Empresa QA 2',
          role: 'Operador',
          role_slug: 'operator',
          plan_slug: 'prata',
          plan_name: 'Prata',
        },
      ],
    })

    await page.goto('/')

    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
    await page.getByRole('button', { name: 'Abrir menu da conta' }).click()
    await expect(tenantTrigger(page)).toBeVisible()

    await tenantTrigger(page).click()
    await page.getByRole('menuitem', { name: /Empresa QA 2/i }).click()

    await expect(page).toHaveURL(/\/$/)
    await page.getByRole('button', { name: 'Abrir menu da conta' }).click()
    await expect(page.getByRole('menu').locator('button').filter({ hasText: 'Empresa QA 2' }).first()).toBeVisible()
    await expect
      .poll(() => page.evaluate(() => localStorage.getItem('maskats.active_tenant_uuid')))
      .toBe('tenant-qa-2')
  })

  test('mantém a sessão carregada quando access-profile falha de forma transitória após trocar a empresa', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      activeTenantUuid: 'tenant-qa-1',
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard'],
      userName: 'Operador resiliente',
      userEmail: 'resiliente@maskats.com',
      tenants: [
        {
          tenant_uuid: 'tenant-qa-1',
          tenant_name: 'Empresa QA 1',
          role: 'Operador',
          role_slug: 'operator',
          plan_slug: 'diamante',
          plan_name: 'Diamante',
        },
        {
          tenant_uuid: 'tenant-qa-2',
          tenant_name: 'Empresa QA 2',
          role: 'Operador',
          role_slug: 'operator',
          plan_slug: 'prata',
          plan_name: 'Prata',
        },
      ],
    })

    let failNextAccessProfile = false

    await page.route('**/api/v1/auth/switch-tenant*', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      failNextAccessProfile = true
      await route.fallback()
    })

    await page.route('**/api/v1/auth/access-profile*', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback()
        return
      }

      if (!failNextAccessProfile) {
        await route.fallback()
        return
      }

      failNextAccessProfile = false
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({
          success: false,
          message: 'Erro interno no servidor.',
          code: 'INTERNAL_SERVER_ERROR',
          errors: {},
          meta: {},
        }),
      })
    })

    await page.goto('/')

    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()

    await page.getByRole('button', { name: 'Abrir menu da conta' }).click()
    await tenantTrigger(page).click()
    await page.getByRole('menuitem', { name: /Empresa QA 2/i }).click()

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Abrir menu da conta' })).toBeVisible()
    await page.getByRole('button', { name: 'Abrir menu da conta' }).click()
    await expect(page.getByRole('menu').locator('button').filter({ hasText: 'Empresa QA 2' }).first()).toBeVisible()
    await expect
      .poll(() => page.evaluate(() => localStorage.getItem('maskats.active_tenant_uuid')))
      .toBe('tenant-qa-2')
  })

  test('conclui o cadastro self-service e entra no ambiente inicial da empresa', async ({ page }) => {
    await mockApiRoute(page, {
      path: '/auth/signup/plans',
      body: [
        {
          uuid: 'plan-prata',
          name: 'Prata',
          slug: 'prata',
          trial_days: 15,
          is_trial_default: true,
        },
      ],
    })

    await page.route('**/api/v1/auth/signup*', async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'Cadastro concluído.',
          data: {
            access_token: 'signup-access-token',
            token_type: 'bearer',
            expires_in: 3600,
            refresh_token: 'signup-refresh-token',
            tenant_uuid: 'tenant-signup-1',
          },
          meta: {},
        }),
      })
    })

    await mockAuthenticatedApiBootstrap(page, {
      activeTenantUuid: 'tenant-signup-1',
      tenantSelectionConfirmed: true,
      isTenantOwner: true,
      tenantPermissions: ['dashboard:read', 'tenant-profile:read'],
      tenantFunctionalities: ['dashboard', 'subscription'],
      userName: 'Nova Proprietária',
      userEmail: 'nova@maskats.com',
      tenants: [
        {
          tenant_uuid: 'tenant-signup-1',
          tenant_name: 'Padaria Nova Era',
          role: 'Proprietário',
          role_slug: 'owner',
          plan_slug: 'prata',
          plan_name: 'Prata',
        },
      ],
    })

    await page.goto('/cadastro')

    await expect(page.getByText('15 dias de teste')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Criar empresa e iniciar teste' })).toBeDisabled()

    await page.getByRole('textbox', { name: 'Nome do proprietário' }).fill('Nova Proprietária')
    await page.getByRole('textbox', { name: 'E-mail do proprietário' }).fill('nova@maskats.com')
    await page.getByRole('textbox', { name: 'Senha', exact: true }).fill('Maskats@2026Segura')
    await page.getByRole('textbox', { name: 'Confirmar senha', exact: true }).fill('Maskats@2026Segura')
    await page.getByRole('textbox', { name: 'Nome da empresa' }).fill('Padaria Nova Era')
    await expect(page.getByRole('textbox', { name: 'Identificador da empresa' })).toHaveValue('padaria-nova-era')

    await page.getByRole('checkbox', { name: /Termos de Uso/i }).check()
    await page.getByRole('checkbox', { name: /Política de Privacidade/i }).check()
    await expect(page.getByRole('button', { name: 'Criar empresa e iniciar teste' })).toBeEnabled()

    await page.getByRole('button', { name: 'Criar empresa e iniciar teste' }).click()

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('dialog', { name: 'Selecione a empresa' })).toBeVisible()
    await page.getByRole('button', { name: 'Entrar' }).click()
    await expect(page.getByRole('dialog', { name: 'Selecione a empresa' })).toHaveCount(0)
    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
    await expect
      .poll(() => page.evaluate(() => localStorage.getItem('maskats.active_tenant_uuid')))
      .toBe('tenant-signup-1')
    await page.getByRole('button', { name: 'Abrir menu da conta' }).click()
    await expect(page.getByRole('menu').locator('button').filter({ hasText: 'Padaria Nova Era' }).first()).toBeVisible()
  })
})
