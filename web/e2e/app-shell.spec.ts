import { expect, test } from '@playwright/test'
import { mockAuthenticatedShell } from './support/api'

test.describe('Shell autenticado', () => {
  test('oculta links sem permissão e permite logout pelo menu do usuário', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: false,
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard'],
      userName: 'Operador QA',
      userEmail: 'operador.qa@pegaticket.com',
    })

    await page.goto('/')

    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Abrir menu da conta' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Configurações' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Pedidos manuais' })).toHaveCount(0)
    await expect(page.getByText('Administração')).toHaveCount(0)

    await page.getByRole('button', { name: 'Abrir menu da conta' }).click()
    await expect(page.getByText('Operador QA')).toBeVisible()
    await expect(page.getByText('operador.qa@pegaticket.com')).toBeVisible()
    await page.getByRole('menuitem', { name: 'Sair' }).click()

    await expect(page).toHaveURL(/\/login$/)
    await expect(page.getByRole('button', { name: 'Entrar no painel' })).toBeVisible()
  })

  test('exige confirmação da empresa quando a sessão ainda não escolheu a empresa ativa', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: false,
      activeTenantUuid: 'tenant-qa-1',
      isTenantOwner: true,
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard'],
      tenants: [
        {
          tenant_uuid: 'tenant-qa-1',
          tenant_name: 'Empresa QA 1',
          role: 'Proprietário',
          role_slug: 'owner',
          plan_slug: 'pegaticket',
          plan_name: 'PegaTicket',
        },
        {
          tenant_uuid: 'tenant-qa-2',
          tenant_name: 'Empresa QA 2',
          role: 'Proprietário',
          role_slug: 'owner',
          plan_slug: 'pegaticket',
          plan_name: 'PegaTicket',
        },
      ],
    })

    await page.goto('/')

    await expect(page.getByRole('dialog', { name: 'Selecione a empresa' })).toBeVisible()
    await page.getByText('Empresa QA 2').click()
    await page.getByRole('button', { name: 'Entrar' }).click()

    await expect(page.getByRole('dialog', { name: 'Selecione a empresa' })).toHaveCount(0)
    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
  })

  test('mostra assinatura da empresa no hub de configurações para o proprietário mesmo sem permissão explícita do grupo', async ({
    page,
  }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: true,
      tenantPermissions: ['tenant-profile:read'],
      tenantFunctionalities: ['dashboard'],
      userName: 'Dono QA',
      userEmail: 'dono.qa@pegaticket.com',
    })

    await page.goto('/configuracoes')

    await expect(page.getByRole('link', { name: 'Assinatura da empresa' })).toBeVisible()
  })

  test('oculta assinatura da empresa no hub e bloqueia acesso direto para usuário comum da empresa', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: false,
      tenantPermissions: ['tenant-profile:read', 'dashboard:read'],
      tenantFunctionalities: ['dashboard'],
      userName: 'Operador QA',
      userEmail: 'operador.qa@pegaticket.com',
    })

    await page.goto('/configuracoes')

    await expect(page.getByRole('link', { name: 'Assinatura da empresa' })).toHaveCount(0)

    await page.goto('/configuracoes/assinatura')

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
  })

  test('bloqueia acesso direto a rota protegida comum quando o perfil não possui a permissão exigida', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: false,
      tenantPermissions: ['dashboard:read'],
      tenantFunctionalities: ['dashboard', 'clients'],
      userName: 'Operador Restrito',
      userEmail: 'restrito.qa@pegaticket.com',
    })

    await page.goto('/clientes')

    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('heading', { name: 'Visão geral' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Clientes' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Novo cliente' })).toHaveCount(0)
  })
})
