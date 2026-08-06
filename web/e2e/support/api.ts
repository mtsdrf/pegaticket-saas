import type { Page, Route } from '@playwright/test'
import type { Sale } from '../../src/types/sale'

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

interface ApiEnvelope<T> {
  success: true
  message: string
  data: T
  meta: Record<string, unknown>
}

interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

interface MockRouteOptions<T> {
  method?: HttpMethod
  path: string | RegExp
  status?: number
  body: T
  message?: string
}

interface ShellMockOptions {
  tenantSelectionConfirmed?: boolean
  activeTenantUuid?: string
  isTenantOwner?: boolean
  tenantPermissions?: string[]
  globalPermissions?: string[]
  tenantFunctionalities?: string[]
  userName?: string
  userEmail?: string
  tenants?: Array<{
    tenant_uuid: string
    tenant_name: string
    role: string
    role_slug?: string | null
    plan_slug?: string | null
    plan_name?: string | null
    logo_url?: string | null
  }>
}

const DEFAULT_TENANT_UUID = 'tenant-qa-1'
const DEFAULT_TOKEN = 'playwright-access-token'
const DEFAULT_REFRESH_TOKEN = 'playwright-refresh-token'

function apiSuccess<T>(data: T, message = 'OK'): ApiEnvelope<T> {
  return {
    success: true,
    message,
    data,
    meta: {},
  }
}

function apiPaginatedSuccess<T>(data: T, pagination: PaginationMeta, message = 'OK') {
  return {
    success: true,
    message,
    data,
    meta: { pagination },
  }
}

function normalizePath(path: string | RegExp): string | RegExp {
  if (path instanceof RegExp) return path
  return `**/api/v1${path}*`
}

async function fulfillJson<T>(route: Route, status: number, body: T) {
  await route.fulfill({
    status,
    contentType: 'application/json',
    body: JSON.stringify(body),
  })
}

export async function mockApiRoute<T>(page: Page, options: MockRouteOptions<T>): Promise<void> {
  const method = options.method ?? 'GET'
  await page.route(normalizePath(options.path), async (route) => {
    if (route.request().method() !== method) {
      await route.fallback()
      return
    }

    await fulfillJson(route, options.status ?? 200, apiSuccess(options.body, options.message))
  })
}

export async function mockPaginatedApiRoute<T>(
  page: Page,
  {
    method = 'GET',
    path,
    status = 200,
    body,
    pagination,
    message = 'OK',
  }: {
    method?: HttpMethod
    path: string | RegExp
    status?: number
    body: T[]
    pagination: PaginationMeta
    message?: string
  },
): Promise<void> {
  await page.route(normalizePath(path), async (route) => {
    if (route.request().method() !== method) {
      await route.fallback()
      return
    }

    await fulfillJson(route, status, apiPaginatedSuccess(body, pagination, message))
  })
}

export async function mockApiError(
  page: Page,
  {
    method = 'GET',
    path,
    status = 422,
    message,
    code,
    errors = {},
  }: {
    method?: HttpMethod
    path: string | RegExp
    status?: number
    message: string
    code: string
    errors?: Record<string, string[]>
  },
): Promise<void> {
  await page.route(normalizePath(path), async (route) => {
    if (route.request().method() !== method) {
      await route.fallback()
      return
    }

    await fulfillJson(route, status, {
      success: false,
      message,
      code,
      errors,
      meta: {},
    })
  })
}

export async function mockAuthenticatedApiBootstrap(page: Page, options: ShellMockOptions = {}): Promise<void> {
  let currentTenantUuid = options.activeTenantUuid ?? DEFAULT_TENANT_UUID
  const tenants =
    options.tenants ??
    [
      {
        tenant_uuid: DEFAULT_TENANT_UUID,
        tenant_name: 'Empresa QA',
        role: options.isTenantOwner ? 'Proprietário' : 'Operador',
        role_slug: options.isTenantOwner ? 'owner' : 'operator',
        plan_slug: 'pegaticket',
        plan_name: 'PegaTicket',
        logo_url: null,
      },
    ]

  await page.route(normalizePath('/auth/access-profile'), async (route) => {
    if (route.request().method() !== 'GET') {
      await route.fallback()
      return
    }

    await fulfillJson(
      route,
      200,
      apiSuccess({
        is_administrator: false,
        has_tenant_context: true,
        is_tenant_owner: options.isTenantOwner ?? false,
        tenant_uuid: currentTenantUuid,
        global_permissions: options.globalPermissions ?? [],
        tenant_permissions: options.tenantPermissions ?? [],
        tenant_functionalities: options.tenantFunctionalities ?? [],
      }),
    )
  })

  await mockApiRoute(page, {
    path: '/auth/my-tenants',
    body: tenants.map((tenant) => ({
      tenant_uuid: tenant.tenant_uuid,
      tenant_name: tenant.tenant_name,
      role: tenant.role,
      role_slug: tenant.role_slug ?? null,
      plan_slug: tenant.plan_slug ?? 'pegaticket',
      plan_name: tenant.plan_name ?? 'PegaTicket',
      logo_url: tenant.logo_url ?? null,
    })),
  })

  await mockApiRoute(page, {
    path: '/auth/profile',
    body: {
      uuid: 'user-qa-1',
      name: options.userName ?? 'Usuário QA',
      email: options.userEmail ?? 'qa@pegaticket.com',
      phone: '11999998888',
      avatar_url: null,
      pending_email: null,
    },
  })

  await mockApiRoute(page, {
    path: '/release-notes',
    body: [],
  })

  await mockApiRoute(page, {
    path: '/reports/indicators',
    body: {
      total_sales: 0,
      total_sales_amount: '0.00',
      average_ticket: '0.00',
      completed_sales: 0,
      uncompleted_sales: 0,
      paid_sales: 0,
      unpaid_sales: 0,
      amount_received: '0.00',
      amount_receivable: '0.00',
      current_period_total_amount: '0.00',
      previous_period_total_amount: '0.00',
      sales_growth_percentage: null,
      comparison_current_label: 'Mês atual',
      comparison_previous_label: 'Mês anterior',
      overdue_sales_count: 0,
      net_revenue_amount: '0.00',
      tickets_issued: 0,
      commercial_capacity: 0,
      occupancy_percentage: 0,
    },
  })

  await mockApiRoute(page, {
    path: '/reports/alerts',
    body: [],
  })

  await mockApiRoute(page, {
    path: '/reports/operation-snapshot',
    body: {
      cash_session: null,
      sales_pending_approval_count: 0,
      sales_today: { count: 0, total_amount: '0.00' },
      checkins_today: { total: 0, granted: 0, warning: 0, blocked: 0 },
      checkout: { window_hours: 24, started: 0, completed: 0, error_rate_percent: 0 },
      virtual_queue: { waiting: 0, admitted: 0 },
      generated_at: new Date().toISOString(),
    },
  })

  await mockApiRoute(page, {
    path: '/reports/charts',
    body: {
      sales_by_month: [],
      paid_vs_unpaid: { paid: 0, unpaid: 0 },
      delivered_vs_undelivered: { delivered: 0, undelivered: 0 },
      received_vs_receivable: { received: '0.00', receivable: '0.00' },
      sales_by_city: [],
      sales_by_neighborhood: [],
      seasonality_matrix: [],
      top_products: [],
      top_clients: [],
      rfm_clients: [],
      late_payment_clients: [],
      overdue_sales: [],
      receivables_aging: [],
      receivables_forecast_by_month: [],
      abc_products: [],
      abc_clients: [],
    },
  })

  await mockApiRoute(page, {
    path: '/reports/operation-health',
    body: {
      internal: {
        approval: { stage: 'approval', label: 'Aprovação', total: 0, attention: 0, critical: 0, oldest_minutes: null },
        production: { stage: 'production', label: 'Produção', total: 0, attention: 0, critical: 0, oldest_minutes: null },
        dispatch: { stage: 'dispatch', label: 'Expedição', total: 0, attention: 0, critical: 0, oldest_minutes: null },
        financial_pending: { stage: 'financial_pending', label: 'Financeiro', total: 0, attention: 0, critical: 0, oldest_minutes: null },
      },
      marketplace: {
        pending_attention: 0,
        pending_critical: 0,
        import_error: 0,
        imported_without_recent_signal: 0,
        oldest_pending_minutes: null,
        oldest_import_error_minutes: null,
        oldest_imported_without_recent_signal_minutes: null,
      },
      totals: {
        items_requiring_attention: 0,
        critical_items: 0,
      },
    },
  })

  await mockApiRoute(page, {
    path: '/reports/receivables/summary',
    body: {
      open_amount: '0.00',
      open_count: 0,
      overdue_amount: '0.00',
      overdue_count: 0,
      aging_buckets: [],
    },
  })

  await mockApiRoute(page, {
    path: '/onboarding/checklist',
    body: {
      has_product: false,
      has_client: false,
      has_first_sale: false,
      steps: [],
      dismissed_at: null,
      total: 4,
      completed: 0,
      is_dismissed: false,
    },
  })

  await mockApiRoute(page, {
    method: 'POST',
    path: '/auth/logout',
    body: null,
  })

  await page.route(normalizePath('/auth/switch-tenant'), async (route) => {
    if (route.request().method() !== 'POST') {
      await route.fallback()
      return
    }

    const payload = route.request().postDataJSON() as { tenant_uuid?: string } | null
    currentTenantUuid = payload?.tenant_uuid ?? currentTenantUuid
    await fulfillJson(
      route,
      200,
      apiSuccess(
        {
          access_token: 'playwright-switched-token',
          token_type: 'bearer',
          expires_in: 3600,
          refresh_token: DEFAULT_REFRESH_TOKEN,
          tenant_uuid: currentTenantUuid,
        },
        'OK',
      ),
    )
  })
}

export async function mockAuthenticatedShell(page: Page, options: ShellMockOptions = {}): Promise<void> {
  const activeTenantUuid = options.activeTenantUuid ?? DEFAULT_TENANT_UUID

  await page.addInitScript(
    ({ tenantUuid, tenantSelectionConfirmed, accessToken, refreshToken }) => {
      localStorage.setItem('pegaticket.access_token', accessToken)
      localStorage.setItem('pegaticket.refresh_token', refreshToken)
      localStorage.setItem('pegaticket.active_tenant_uuid', tenantUuid)

      if (tenantSelectionConfirmed) {
        sessionStorage.setItem('pegaticket.tenant_selection_confirmed', '1')
      } else {
        sessionStorage.removeItem('pegaticket.tenant_selection_confirmed')
      }
    },
    {
      tenantUuid: activeTenantUuid,
      tenantSelectionConfirmed: options.tenantSelectionConfirmed ?? true,
      accessToken: DEFAULT_TOKEN,
      refreshToken: DEFAULT_REFRESH_TOKEN,
    },
  )

  await mockAuthenticatedApiBootstrap(page, options)
}

export function makeSale(overrides: Partial<Sale> = {}): Sale {
  return {
    uuid: overrides.uuid ?? 'sale-qa-1',
    codigo: overrides.codigo ?? '1000',
    is_installment: overrides.is_installment ?? false,
    total_amount: overrides.total_amount ?? 149.9,
    service_fee: overrides.service_fee ?? 0,
    discount_amount: overrides.discount_amount ?? 0,
    coupon_code: overrides.coupon_code ?? null,
    is_paid: overrides.is_paid ?? false,
    paid_amount: overrides.paid_amount ?? null,
    paid_at: overrides.paid_at ?? null,
    due_date: overrides.due_date ?? null,
    cancelled_at: overrides.cancelled_at ?? null,
    cancellation_reason: overrides.cancellation_reason ?? null,
    notes: overrides.notes ?? null,
    status: overrides.status ?? 'confirmed',
    origin: overrides.origin ?? 'staff',
    final_customer: overrides.final_customer ?? {
      uuid: 'final-customer-qa-1',
      name: 'Comprador QA',
    },
    items: overrides.items ?? [],
    installments: overrides.installments ?? [],
    created_at: overrides.created_at ?? '2026-07-28T12:00:00Z',
  }
}
