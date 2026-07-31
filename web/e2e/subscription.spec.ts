import { expect, test } from '@playwright/test'
import { mockApiRoute, mockAuthenticatedShell, mockPaginatedApiRoute } from './support/api'

async function mockSubscriptionReadSide(page: Parameters<typeof mockApiRoute>[0]) {
  await mockPaginatedApiRoute(page, {
    path: '/subscription/invoices',
    body: [],
    pagination: {
      current_page: 1,
      per_page: 15,
      total: 0,
      last_page: 1,
    },
  })

  await mockPaginatedApiRoute(page, {
    path: '/subscription/history',
    body: [],
    pagination: {
      current_page: 1,
      per_page: 10,
      total: 0,
      last_page: 1,
    },
  })

  await mockPaginatedApiRoute(page, {
    path: '/subscription/refunds',
    body: [],
    pagination: {
      current_page: 1,
      per_page: 10,
      total: 0,
      last_page: 1,
    },
  })
}

test.describe('Assinatura da empresa', () => {
  test('mostra a contratação inicial quando a empresa ainda não possui assinatura ativa', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: true,
      tenantPermissions: ['tenant-settings:read'],
      tenantFunctionalities: ['subscription'],
      tenants: [
        {
          tenant_uuid: 'tenant-qa-1',
          tenant_name: 'Empresa QA',
          role: 'Proprietário',
          role_slug: 'owner',
          plan_slug: 'prata',
          plan_name: 'Prata',
        },
      ],
    })

    await mockApiRoute(page, {
      path: '/subscription',
      body: null,
    })

    await mockApiRoute(page, {
      path: '/subscription/available-plans',
      body: [
        {
          plan: {
            uuid: 'plan-prata',
            name: 'Prata',
            slug: 'silver',
            description: 'Plano inicial para operação essencial.',
          },
          billing_periods: [
            {
              billing_period: 'monthly',
              months: 1,
              discount_percent: 0,
              list_amount: '99.90',
              total_amount: '99.90',
              monthly_equivalent: '99.90',
            },
            {
              billing_period: 'yearly',
              months: 12,
              discount_percent: 10,
              list_amount: '1198.80',
              total_amount: '1078.92',
              monthly_equivalent: '89.91',
            },
          ],
          functionalities: [],
        },
      ],
    })

    await mockSubscriptionReadSide(page)

    await page.goto('/configuracoes/assinatura')

    await expect(page.getByRole('heading', { name: 'Assinatura da empresa' })).toBeVisible()
    await expect(page.getByText('Nenhuma assinatura ativa')).toBeVisible()
    await expect(page.getByText('Sua empresa ainda não possui uma assinatura registrada.')).toBeVisible()
    await expect(page.getByText('Perfil proprietário')).toBeVisible()
    await page.getByRole('button', { name: /Prata Plano inicial para operação essencial\./ }).click()

    await expect(page.getByText('O que está incluído no plano Prata')).toBeVisible()
    await expect(page.getByRole('radiogroup', { name: 'Período de cobrança' })).toBeVisible()

    const startButton = page.getByRole('button', { name: 'Iniciar assinatura' })
    await expect(startButton).toBeDisabled()

    await page.getByRole('checkbox', { name: 'Li e concordo com os termos vigentes para iniciar a assinatura.' }).check()
    await expect(startButton).toBeEnabled()
  })

  test('mostra retomada de contratação quando a assinatura já foi cancelada', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: true,
      tenantPermissions: ['subscription:read', 'subscription:update', 'tenant-settings:read'],
      tenantFunctionalities: ['subscription'],
      tenants: [
        {
          tenant_uuid: 'tenant-qa-1',
          tenant_name: 'Empresa QA',
          role: 'Proprietário',
          role_slug: 'owner',
          plan_slug: 'prata',
          plan_name: 'Prata',
        },
      ],
    })

    await mockApiRoute(page, {
      path: '/subscription',
      body: {
        uuid: 'subscription-canceled-1',
        status: 'canceled',
        billing_period: 'monthly',
        plan: {
          uuid: 'plan-prata',
          name: 'Prata',
          slug: 'prata',
        },
        trial_ends_at: null,
        is_trial: false,
        trial_days_remaining: null,
        current_period_start: '2026-06-01T00:00:00Z',
        current_period_end: '2026-06-30T23:59:59Z',
        next_charge_at: null,
        cancel_at: null,
        canceled_at: '2026-07-15T12:00:00Z',
        grace_period_ends_at: null,
        auto_renew: false,
        accepted_terms_version: '2026.07',
        accepted_at: '2026-06-01T10:00:00Z',
        created_at: '2026-06-01T10:00:00Z',
      },
    })

    await mockApiRoute(page, {
      path: '/subscription/available-plans',
      body: [
        {
          plan: {
            uuid: 'plan-diamante',
            name: 'Diamante',
            slug: 'diamante',
            description: 'Plano completo para retomada.',
          },
          billing_periods: [
            {
              billing_period: 'monthly',
              months: 1,
              discount_percent: 0,
              list_amount: '199.90',
              total_amount: '199.90',
              monthly_equivalent: '199.90',
            },
          ],
          functionalities: [],
        },
      ],
    })

    await mockSubscriptionReadSide(page)

    await page.goto('/configuracoes/assinatura')

    await expect(page.getByText(/^Cancelada$/).first()).toBeVisible()
    await expect(page.getByText('Contratar novo plano')).toBeVisible()
    await expect(page.getByText('Esta assinatura já foi encerrada.')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Solicitar arrependimento' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Gerenciar cancelamento' })).toHaveCount(0)
  })

  test('expõe o estado suspenso com orientação de regularização sem exibir ações indevidas', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: true,
      tenantPermissions: ['subscription:read', 'subscription:update', 'tenant-settings:read'],
      tenantFunctionalities: ['subscription'],
      tenants: [
        {
          tenant_uuid: 'tenant-qa-1',
          tenant_name: 'Empresa QA',
          role: 'Proprietário',
          role_slug: 'owner',
          plan_slug: 'diamante',
          plan_name: 'Diamante',
        },
      ],
    })

    await mockApiRoute(page, {
      path: '/subscription',
      body: {
        uuid: 'subscription-suspended-1',
        status: 'suspended',
        billing_period: 'monthly',
        plan: {
          uuid: 'plan-diamante',
          name: 'Diamante',
          slug: 'diamante',
        },
        trial_ends_at: null,
        is_trial: false,
        trial_days_remaining: null,
        current_period_start: '2026-07-01T00:00:00Z',
        current_period_end: '2026-07-31T23:59:59Z',
        next_charge_at: '2026-08-01T00:00:00Z',
        cancel_at: null,
        canceled_at: null,
        grace_period_ends_at: '2026-07-31T23:59:59Z',
        auto_renew: true,
        accepted_terms_version: '2026.07',
        accepted_at: '2026-07-01T10:00:00Z',
        created_at: '2026-07-01T10:00:00Z',
      },
    })

    await mockSubscriptionReadSide(page)

    await page.goto('/configuracoes/assinatura')

    await expect(page.getByText(/^Suspensa$/).first()).toBeVisible()
    await expect(page.getByText(/Regularize a situação até/)).toBeVisible()
    await expect(page.getByText('Ações da assinatura')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Mudar de plano' })).toHaveCount(0)
    await expect(page.getByRole('button', { name: 'Gerenciar cancelamento' })).toHaveCount(0)
  })

  test('lista faturas e histórico da assinatura com ação contextual de Pix para cobranças elegíveis', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      isTenantOwner: true,
      tenantPermissions: ['subscription:read', 'subscription:update', 'tenant-settings:read'],
      tenantFunctionalities: ['subscription'],
      tenants: [
        {
          tenant_uuid: 'tenant-qa-1',
          tenant_name: 'Empresa QA',
          role: 'Proprietário',
          role_slug: 'owner',
          plan_slug: 'diamante',
          plan_name: 'Diamante',
        },
      ],
    })

    await mockApiRoute(page, {
      path: '/subscription',
      body: {
        uuid: 'subscription-active-1',
        status: 'active',
        billing_period: 'monthly',
        plan: {
          uuid: 'plan-diamante',
          name: 'Diamante',
          slug: 'diamante',
        },
        trial_ends_at: null,
        is_trial: false,
        trial_days_remaining: null,
        current_period_start: '2026-07-01T00:00:00Z',
        current_period_end: '2026-07-31T23:59:59Z',
        next_charge_at: '2026-08-01T00:00:00Z',
        cancel_at: null,
        canceled_at: null,
        grace_period_ends_at: null,
        auto_renew: true,
        accepted_terms_version: '2026.07',
        accepted_at: '2026-07-01T10:00:00Z',
        created_at: '2026-07-01T10:00:00Z',
      },
    })

    await mockPaginatedApiRoute(page, {
      path: '/subscription/invoices',
      body: [
        {
          uuid: 'invoice-open-1',
          competence_period: 'jul/2026',
          due_date: '2026-07-31T00:00:00Z',
          amount_gross: 199.9,
          discount_amount: 0,
          amount_net: 199.9,
          status: 'open',
          created_at: '2026-07-01T10:00:00Z',
        },
        {
          uuid: 'invoice-divergent-1',
          competence_period: 'jun/2026',
          due_date: '2026-06-30T00:00:00Z',
          amount_gross: 199.9,
          discount_amount: 0,
          amount_net: 199.9,
          status: 'divergent',
          created_at: '2026-06-01T10:00:00Z',
        },
        {
          uuid: 'invoice-paid-1',
          competence_period: 'mai/2026',
          due_date: '2026-05-31T00:00:00Z',
          amount_gross: 199.9,
          discount_amount: 0,
          amount_net: 199.9,
          status: 'paid',
          created_at: '2026-05-01T10:00:00Z',
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 10,
        total: 3,
        last_page: 1,
      },
    })

    await mockPaginatedApiRoute(page, {
      path: '/subscription/history',
      body: [
        {
          uuid: 'subscription-history-1',
          status: 'active',
          billing_period: 'monthly',
          plan: {
            uuid: 'plan-diamante',
            name: 'Diamante',
            slug: 'diamante',
          },
          trial_ends_at: null,
          current_period_start: '2026-07-01T00:00:00Z',
          current_period_end: '2026-07-31T23:59:59Z',
          cancel_at: null,
          canceled_at: null,
          auto_renew: true,
          created_at: '2026-07-01T10:00:00Z',
        },
        {
          uuid: 'subscription-history-0',
          status: 'canceled',
          billing_period: 'monthly',
          plan: {
            uuid: 'plan-prata',
            name: 'Prata',
            slug: 'prata',
          },
          trial_ends_at: null,
          current_period_start: '2026-06-01T00:00:00Z',
          current_period_end: '2026-06-30T23:59:59Z',
          cancel_at: '2026-06-29T12:00:00Z',
          canceled_at: '2026-06-30T18:00:00Z',
          auto_renew: false,
          created_at: '2026-06-01T10:00:00Z',
        },
      ],
      pagination: {
        current_page: 1,
        per_page: 10,
        total: 2,
        last_page: 1,
      },
    })

    await mockPaginatedApiRoute(page, {
      path: '/subscription/refunds',
      body: [],
      pagination: {
        current_page: 1,
        per_page: 10,
        total: 0,
        last_page: 1,
      },
    })

    await mockApiRoute(page, {
      method: 'POST',
      path: '/subscription/invoices/invoice-open-1/pix-charge',
      body: {
        uuid: 'invoice-payment-1',
        provider: 'mercado_pago',
        provider_charge_id: 'mp-pix-1',
        method: 'pix',
        amount: '199.90',
        status: 'pending',
        paid_at: null,
        created_at: '2026-07-28T10:00:00Z',
        metadata: {
          qr_code: '00020126580014BR.GOV.BCB.PIX0114+55119999999995204000053039865802BR5925MASKATS TESTE6009SAO PAULO62070503***6304ABCD',
          ticket_url: 'https://mercadopago.example/pix/invoice-open-1',
        },
      },
    })

    await page.goto('/configuracoes/assinatura')

    await expect(page.getByText('Pagamento e faturas')).toBeVisible()
    await expect(page.getByText('Histórico de assinaturas')).toBeVisible()
    await expect(page.getByText('Uma ou mais cobranças abaixo têm valor não confirmado.')).toBeVisible()
    await expect(page.getByRole('cell', { name: 'jul/2026' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'jun/2026' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'mai/2026' })).toBeVisible()
    await expect(page.getByText('Valor não confirmado', { exact: true })).toBeVisible()

    await page.getByText('Histórico de assinaturas').scrollIntoViewIfNeeded()
    await expect(page.getByRole('cell', { name: 'Diamante' })).toBeVisible()
    await expect(page.getByRole('cell', { name: 'Prata' })).toBeVisible()
    await expect(page.getByText(/^Ativa$/).first()).toBeVisible()
    await expect(page.getByText(/^Cancelada$/).first()).toBeVisible()

    await expect(page.getByRole('button', { name: 'Pagar com Pix' })).toHaveCount(1)
    await page.getByRole('button', { name: 'Pagar com Pix' }).click()

    await expect(page.getByRole('dialog', { name: 'Pagar fatura com Pix' })).toBeVisible()
    await expect(page.getByLabel('Código Pix copia e cola')).toHaveValue(
      /00020126580014BR\.GOV\.BCB\.PIX/,
    )
    await expect(page.getByRole('button', { name: 'Copiar código Pix' })).toBeVisible()
    await expect(page.getByRole('link', { name: 'Abrir no Mercado Pago' })).toBeVisible()
  })
})
