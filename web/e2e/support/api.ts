import type { Page, Route } from '@playwright/test'
import { STORAGE_KEYS } from '../../src/constants/storage'
import type { Order } from '../../src/types/order'

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
    send_tracking_link_whatsapp?: boolean
    logo_url?: string | null
  }>
}

interface AccountingShellMockOptions {
  office?: {
    uuid: string
    cnpj: string
    company_name: string
    responsible_name: string
    email: string
    totp_enabled: boolean
    totp_enabled_at: string | null
    created_at: string | null
  }
  links?: Array<{
    uuid: string
    status: 'pending' | 'approved' | 'revoked'
    scopes: Array<'financial.read' | 'fiscal.read' | 'fiscal.write' | 'reports.read'>
    requested_at: string | null
    approved_at: string | null
    revoked_at: string | null
    tenant?: {
      uuid: string
      name: string
      cnpj: string | null
    }
    accounting_office?: {
      uuid: string
      cnpj: string
      company_name: string
      responsible_name: string
      email: string
    }
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
        plan_slug: 'diamante',
        plan_name: 'Diamante',
        send_tracking_link_whatsapp: true,
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
      plan_slug: tenant.plan_slug ?? 'diamante',
      plan_name: tenant.plan_name ?? 'Diamante',
      send_tracking_link_whatsapp: tenant.send_tracking_link_whatsapp ?? true,
      logo_url: tenant.logo_url ?? null,
    })),
  })

  await mockApiRoute(page, {
    path: '/auth/profile',
    body: {
      uuid: 'user-qa-1',
      name: options.userName ?? 'Usuário QA',
      email: options.userEmail ?? 'qa@maskats.com',
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
      total_orders: 0,
      total_sales_amount: '0.00',
      average_ticket: '0.00',
      delivered_orders: 0,
      undelivered_orders: 0,
      paid_orders: 0,
      unpaid_orders: 0,
      amount_received: '0.00',
      amount_receivable: '0.00',
      current_period_total_amount: '0.00',
      previous_period_total_amount: '0.00',
      sales_growth_percentage: null,
      comparison_current_label: 'Mês atual',
      comparison_previous_label: 'Mês anterior',
      overdue_orders_count: 0,
    },
  })

  await mockApiRoute(page, {
    path: '/reports/charts',
    body: {
      orders_by_month: [],
      paid_vs_unpaid: { paid: 0, unpaid: 0 },
      delivered_vs_undelivered: { delivered: 0, undelivered: 0 },
      received_vs_receivable: { received: '0.00', receivable: '0.00' },
      orders_by_city: [],
      orders_by_neighborhood: [],
      seasonality_matrix: [],
      top_products: [],
      top_clients: [],
      rfm_clients: [],
      late_payment_clients: [],
      overdue_orders: [],
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
      has_first_order: false,
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
      localStorage.setItem('maskats.access_token', accessToken)
      localStorage.setItem('maskats.refresh_token', refreshToken)
      localStorage.setItem('maskats.active_tenant_uuid', tenantUuid)

      if (tenantSelectionConfirmed) {
        sessionStorage.setItem('maskats.tenant_selection_confirmed', '1')
      } else {
        sessionStorage.removeItem('maskats.tenant_selection_confirmed')
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

export async function mockAccountingShell(page: Page, options: AccountingShellMockOptions = {}): Promise<void> {
  const office =
    options.office ?? {
      uuid: 'office-qa-1',
      cnpj: '12345678000190',
      company_name: 'Contabilidade QA',
      responsible_name: 'Maria QA',
      email: 'contador.qa@maskats.com',
      totp_enabled: true,
      totp_enabled_at: '2026-07-28T10:00:00Z',
      created_at: '2026-07-01T10:00:00Z',
    }

  const links =
    options.links ?? [
      {
        uuid: 'accounting-link-1',
        status: 'approved' as const,
        scopes: ['financial.read', 'fiscal.read', 'fiscal.write', 'reports.read'] as const,
        requested_at: '2026-07-20T10:00:00Z',
        approved_at: '2026-07-21T10:00:00Z',
        revoked_at: null,
        tenant: {
          uuid: 'tenant-qa-1',
          name: 'Empresa QA',
          cnpj: '11222333000144',
        },
      },
    ]

  await page.addInitScript((storageKey) => {
    localStorage.setItem(storageKey, 'playwright-accounting-token')
  }, STORAGE_KEYS.accountingAccessToken)

  await page.route('**/api/v1/accounting/me*', async (route) => {
    if (route.request().method() !== 'GET') {
      await route.fallback()
      return
    }

    await fulfillJson(route, 200, apiSuccess(office))
  })

  await page.route('**/api/v1/accounting/access-requests*', async (route) => {
    const method = route.request().method()

    if (method === 'GET') {
      await fulfillJson(route, 200, apiSuccess(links))
      return
    }

    await route.fallback()
  })
}

export function makeOrder(overrides: Partial<Order> = {}): Order {
  return {
    uuid: overrides.uuid ?? 'order-qa-1',
    codigo: overrides.codigo ?? '1000',
    is_installment: overrides.is_installment ?? false,
    total_amount: overrides.total_amount ?? 149.9,
    delivery_fee: overrides.delivery_fee ?? 0,
    service_fee: overrides.service_fee ?? 0,
    discount_amount: overrides.discount_amount ?? 0,
    coupon_code: overrides.coupon_code ?? null,
    is_paid: overrides.is_paid ?? false,
    paid_amount: overrides.paid_amount ?? null,
    paid_at: overrides.paid_at ?? null,
    is_delivered: overrides.is_delivered ?? false,
    delivered_at: overrides.delivered_at ?? null,
    due_date: overrides.due_date ?? null,
    expected_delivery_date: overrides.expected_delivery_date ?? null,
    cancelled_at: overrides.cancelled_at ?? null,
    cancellation_reason: overrides.cancellation_reason ?? null,
    notes: overrides.notes ?? null,
    status: overrides.status ?? 'confirmed',
    origin: overrides.origin ?? 'staff',
    is_out_for_delivery: overrides.is_out_for_delivery ?? false,
    out_for_delivery_at: overrides.out_for_delivery_at ?? null,
    client: overrides.client ?? {
      uuid: 'client-qa-1',
      name: 'Cliente QA',
    },
    stock_location: overrides.stock_location ?? null,
    items: overrides.items ?? [],
    installments: overrides.installments ?? [],
    created_at: overrides.created_at ?? '2026-07-28T12:00:00Z',
  }
}
