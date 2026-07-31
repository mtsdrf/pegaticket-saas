import { expect, test } from '@playwright/test'
import { mockApiRoute, mockAuthenticatedShell } from './support/api'

interface MarketplaceIntegrationMock {
  uuid: string
  provider: 'ifood'
  name: string
  environment: 'sandbox' | 'production'
  auth_mode: string
  status: string
  is_active: boolean
  client_id: string | null
  merchant_id: string | null
  webhook_url: string | null
  generated_webhook_url: string | null
  polling_merchant_ids: string | null
  access_token_expires_at: string | null
  refresh_token_expires_at: string | null
  last_connected_at: string | null
  last_synced_at: string | null
  last_polled_at: string | null
  last_error_at: string | null
  last_error_message: string | null
  settings: Record<string, unknown> | null
  merchants_count?: number
  events_count?: number
  orders_count?: number
}

interface MarketplaceOrderMock {
  uuid: string
  external_id: string
  display_id: string | null
  order_number: string | null
  status: string | null
  queue_status: 'imported' | 'pending_import' | 'import_error'
  customer_name: string | null
  total_amount: number | null
  raw_updated_at: string | null
  last_synced_at: string | null
  imported_at: string | null
  import_error_message: string | null
  events_count?: number
  latest_event_at?: string | null
  payload: Record<string, unknown>
  merchant?: {
    uuid: string
    external_id: string
    name: string
  } | null
  actions?: Array<{
    uuid: string
    action: string
    status: string
    request_payload: Record<string, unknown> | null
    response_payload: Record<string, unknown> | null
    executed_at: string | null
    error_message: string | null
  }>
  events?: Array<{
    uuid: string
    external_event_id: string | null
    external_order_id: string | null
    event_type: string
    event_full_code: string | null
    status: string
    processing_attempts: number
    occurred_at: string | null
    acknowledged_at: string | null
    processed_at: string | null
    last_attempted_at: string | null
    dead_lettered_at: string | null
    error_message: string | null
    payload: Record<string, unknown>
  }>
  internal_order?: {
    uuid: string
    codigo: string | null
    status: string
    origin: string
    is_paid: boolean
    is_delivered: boolean
    client_name: string | null
  } | null
}

function makeIntegration(overrides: Partial<MarketplaceIntegrationMock> = {}): MarketplaceIntegrationMock {
  return {
    uuid: overrides.uuid ?? 'marketplace-int-qa-1',
    provider: 'ifood',
    name: overrides.name ?? 'iFood principal',
    environment: overrides.environment ?? 'sandbox',
    auth_mode: overrides.auth_mode ?? 'authorization_code',
    status: overrides.status ?? 'connected',
    is_active: overrides.is_active ?? true,
    client_id: overrides.client_id ?? 'client-qa-ifood',
    merchant_id: overrides.merchant_id ?? 'merchant-qa-ifood',
    webhook_url: overrides.webhook_url ?? null,
    generated_webhook_url: overrides.generated_webhook_url ?? null,
    polling_merchant_ids: overrides.polling_merchant_ids ?? null,
    access_token_expires_at: overrides.access_token_expires_at ?? null,
    refresh_token_expires_at: overrides.refresh_token_expires_at ?? null,
    last_connected_at: overrides.last_connected_at ?? '2026-07-28T11:55:00Z',
    last_synced_at: overrides.last_synced_at ?? '2026-07-28T12:00:00Z',
    last_polled_at: overrides.last_polled_at ?? '2026-07-28T12:01:00Z',
    last_error_at: overrides.last_error_at ?? null,
    last_error_message: overrides.last_error_message ?? null,
    settings: overrides.settings ?? null,
    merchants_count: overrides.merchants_count ?? 1,
    events_count: overrides.events_count ?? 0,
    orders_count: overrides.orders_count ?? 0,
  }
}

function makeMarketplaceOrder(overrides: Partial<MarketplaceOrderMock> = {}): MarketplaceOrderMock {
  return {
    uuid: overrides.uuid ?? 'marketplace-order-qa-1',
    external_id: overrides.external_id ?? 'ifood-external-3001',
    display_id: overrides.display_id ?? '3001',
    order_number: overrides.order_number ?? '3001',
    status: overrides.status ?? 'PLACED',
    queue_status: overrides.queue_status ?? 'pending_import',
    customer_name: overrides.customer_name ?? 'Cliente Marketplace QA',
    total_amount: overrides.total_amount ?? 79.9,
    raw_updated_at: overrides.raw_updated_at ?? '2026-07-28T12:00:00Z',
    last_synced_at: overrides.last_synced_at ?? '2026-07-28T12:01:00Z',
    imported_at: overrides.imported_at ?? null,
    import_error_message: overrides.import_error_message ?? null,
    events_count: overrides.events_count ?? 1,
    latest_event_at: overrides.latest_event_at ?? '2026-07-28T12:00:30Z',
    payload: overrides.payload ?? { source: 'ifood', items: 2 },
    merchant: overrides.merchant ?? {
      uuid: 'merchant-qa-1',
      external_id: 'ifood-merchant-1',
      name: 'Loja Centro',
    },
    actions: overrides.actions ?? [],
    events: overrides.events ?? [
      {
        uuid: 'event-marketplace-1',
        external_event_id: 'evt-1',
        external_order_id: 'ifood-external-3001',
        event_type: 'PLACED',
        event_full_code: 'PLC',
        status: 'processed',
        processing_attempts: 1,
        occurred_at: '2026-07-28T12:00:00Z',
        acknowledged_at: '2026-07-28T12:00:10Z',
        processed_at: '2026-07-28T12:00:10Z',
        last_attempted_at: '2026-07-28T12:00:10Z',
        dead_lettered_at: null,
        error_message: null,
        payload: { type: 'PLACED' },
      },
    ],
    internal_order: overrides.internal_order ?? null,
  }
}

function buildOperationsSummary(order: MarketplaceOrderMock) {
  const isPending = order.queue_status === 'pending_import'
  const isImported = order.queue_status === 'imported'
  const isImportError = order.queue_status === 'import_error'

  return {
    events_total: order.events?.length ?? 0,
    events_processed: (order.events ?? []).filter((item) => item.status === 'processed').length,
    events_failed: (order.events ?? []).filter((item) => item.status === 'failed').length,
    events_dead_letter: (order.events ?? []).filter((item) => item.dead_lettered_at).length,
    events_unacknowledged: (order.events ?? []).filter((item) => !item.acknowledged_at).length,
    orders_total: 1,
    orders_imported: isImported ? 1 : 0,
    orders_pending_import: isPending ? 1 : 0,
    orders_with_import_error: isImportError ? 1 : 0,
    orders_pending_import_attention: isPending ? 1 : 0,
    orders_pending_import_critical: isPending ? 1 : 0,
    orders_imported_without_recent_signal: isImported ? 0 : 0,
    oldest_pending_import_minutes: isPending ? 22 : null,
    oldest_import_error_minutes: isImportError ? 18 : null,
    oldest_imported_without_signal_minutes: null,
    last_poll_at: '2026-07-28T12:01:00Z',
    last_webhook_received_at: '2026-07-28T12:00:10Z',
    last_error_at: null,
    last_error_message: null,
    silent_since_minutes: 0,
    is_stale: false,
    needs_attention: isPending || isImportError,
  }
}

test.describe('Pedidos iFood', () => {
  test('mostra estado vazio quando a empresa ainda não cadastrou integração de delivery', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['api-access:read'],
      tenantFunctionalities: ['api-access'],
    })

    await mockApiRoute(page, {
      path: '/marketplace/integrations',
      body: [],
    })

    await page.goto('/pedidos-ifood')

    await expect(page.getByText('Nenhuma integração de delivery cadastrada', { exact: true })).toBeVisible()
    await expect(page.getByText('Cadastre e conecte a integração do iFood primeiro.')).toBeVisible()
    await expect(page.getByRole('link', { name: 'Abrir integrações' })).toBeVisible()
  })

  test('abre a fila com foco operacional vindo do dashboard e exibe o resumo crítico correto', async ({ page }) => {
    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['api-access:read'],
      tenantFunctionalities: ['api-access'],
    })

    const integration = makeIntegration()

    await mockApiRoute(page, {
      path: '/marketplace/integrations',
      body: [integration],
    })

    await mockApiRoute(page, {
      path: `/marketplace/integrations/${integration.uuid}/operations-summary`,
      body: {
        events_total: 2,
        events_processed: 1,
        events_failed: 1,
        events_dead_letter: 0,
        events_unacknowledged: 1,
        orders_total: 1,
        orders_imported: 0,
        orders_pending_import: 1,
        orders_with_import_error: 0,
        orders_pending_import_attention: 1,
        orders_pending_import_critical: 1,
        orders_imported_without_recent_signal: 0,
        oldest_pending_import_minutes: 19,
        oldest_import_error_minutes: null,
        oldest_imported_without_signal_minutes: null,
        last_poll_at: '2026-07-28T12:05:00Z',
        last_webhook_received_at: '2026-07-28T12:04:00Z',
        last_error_at: null,
        last_error_message: null,
        silent_since_minutes: 0,
        is_stale: false,
        needs_attention: true,
      },
    })

    await page.route(`**/api/v1/marketplace/integrations/${integration.uuid}/orders*`, async (route) => {
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
          data: [],
          meta: {
            pagination: {
              current_page: 1,
              per_page: 20,
              total: 0,
              last_page: 1,
            },
          },
        }),
      })
    })

    await page.goto('/pedidos-ifood?focus=pending_critical&queue_status=pending_import&source=dashboard')

    await expect(page.getByRole('heading', { name: 'Pedidos iFood' })).toBeVisible()
    await expect(page.getByText('Esta fila externa foi aberta a partir do dashboard para tratamento operacional contextualizado.')).toBeVisible()
    await expect(page.getByText('A tela foi aberta focando pedidos pendentes que já estouraram o SLA crítico de importação.')).toBeVisible()
    await expect(page.getByText('Existem exceções operacionais em aberto nesta integração.')).toBeVisible()
    await expect(page.getByRole('paragraph').filter({ hasText: /^Pendentes críticos$/ })).toBeVisible()
    await expect(page.getByText('Maior fila pendente')).toBeVisible()
    await expect(page.getByText('Nenhum pedido externo encontrado')).toBeVisible()
  })

  test('opera a fila iFood com importação, ações operacionais e cancelamento auditável', async ({ page }) => {
    test.slow()

    await mockAuthenticatedShell(page, {
      tenantSelectionConfirmed: true,
      tenantPermissions: ['api-access:read', 'api-access:update'],
      tenantFunctionalities: ['api-access'],
    })

    const integration = makeIntegration()
    let currentOrder = makeMarketplaceOrder()
    let actionCounter = 1

    await mockApiRoute(page, {
      path: '/marketplace/integrations',
      body: [integration],
    })

    await page.route(`**/api/v1/marketplace/integrations/${integration.uuid}/operations-summary*`, async (route) => {
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
          data: buildOperationsSummary(currentOrder),
          meta: {},
        }),
      })
    })

    await page.route(`**/api/v1/marketplace/integrations/${integration.uuid}/orders*`, async (route) => {
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
          data: [currentOrder],
          meta: {
            pagination: {
              current_page: 1,
              per_page: 20,
              total: 1,
              last_page: 1,
            },
          },
        }),
      })
    })

    await page.route(`**/api/v1/marketplace/orders/${currentOrder.uuid}`, async (route) => {
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
          data: currentOrder,
          meta: {},
        }),
      })
    })

    await page.route(`**/api/v1/marketplace/orders/${currentOrder.uuid}/import`, async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      currentOrder = {
        ...currentOrder,
        queue_status: 'imported',
        imported_at: '2026-07-28T12:08:00Z',
        internal_order: {
          uuid: 'internal-order-6101',
          codigo: '6101',
          status: 'confirmed',
          origin: 'ifood',
          is_paid: false,
          is_delivered: false,
          client_name: currentOrder.customer_name,
        },
      }

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: currentOrder,
          meta: {},
        }),
      })
    })

    await page.route(`**/api/v1/marketplace/orders/${currentOrder.uuid}/actions`, async (route) => {
      if (route.request().method() !== 'POST') {
        await route.fallback()
        return
      }

      const payload = route.request().postDataJSON() as { action: string; code?: string; reason?: string }
      const executedAt = payload.action === 'cancel' ? '2026-07-28T12:10:00Z' : '2026-07-28T12:09:00Z'

      currentOrder = {
        ...currentOrder,
        status: payload.action === 'cancel' ? 'CANCELLATION_REQUESTED' : 'CONFIRMED',
        actions: [
          {
            uuid: `action-${actionCounter}`,
            action: payload.action,
            status: 'success',
            request_payload: payload,
            response_payload: { accepted: true },
            executed_at: executedAt,
            error_message: null,
          },
          ...(currentOrder.actions ?? []),
        ],
      }
      actionCounter += 1

      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          message: 'OK',
          data: null,
          meta: {},
        }),
      })
    })

    await page.route(`**/api/v1/marketplace/orders/${currentOrder.uuid}/cancellation-reasons`, async (route) => {
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
          data: [
            { code: 'CUSTOMER_REQUEST', description: 'Solicitação do cliente' },
            { code: 'OUT_OF_STOCK', description: 'Produto indisponível' },
          ],
          meta: {},
        }),
      })
    })

    await page.goto('/pedidos-ifood')

    await expect(page.getByRole('heading', { name: 'Pedidos iFood' })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: 'Cliente Marketplace QA', exact: true })).toBeVisible()
    await expect(page.getByRole('gridcell', { name: '3001', exact: true })).toBeVisible()

    const row = page.locator('.ag-row').filter({ hasText: '3001' }).first()
    await row.locator('button').first().click()

    const detailDialog = page.getByRole('dialog', { name: 'Operação do pedido iFood' })
    await expect(detailDialog).toBeVisible()
    await expect(detailDialog.getByText('Cliente Marketplace QA • Loja Centro')).toBeVisible()
    await expect(detailDialog.getByText('Aguardando importação', { exact: true })).toBeVisible()

    await page.getByRole('button', { name: 'Importar pedido' }).click()
    await expect(page.getByText('Pedido externo importado com sucesso para o fluxo interno.')).toBeVisible()
    await expect(detailDialog.getByRole('button', { name: 'Abrir pedido interno' })).toBeVisible()
    await expect(detailDialog.getByText(/^6101$/)).toBeVisible()
    await expect(page.getByText('Pedido convertido para o fluxo interno')).toBeVisible()

    await page.getByRole('button', { name: 'Confirmar no iFood' }).click()
    await expect(page.getByText('Ação enviada ao iFood com sucesso.')).toBeVisible()
    await expect(detailDialog.getByText('Ação enviada: confirm')).toBeVisible()
    await expect(detailDialog.getByText('CONFIRMED', { exact: true })).toBeVisible()

    await page.getByRole('button', { name: 'Solicitar cancelamento' }).click()
    await expect(page.getByRole('dialog', { name: 'Solicitar cancelamento no iFood' })).toBeVisible()
    await page.getByLabel('Descrição para auditoria').fill('Cliente pediu ajuste de endereço antes da expedição.')
    await page.getByRole('button', { name: 'Solicitar cancelamento' }).click()

    await expect(page.getByText('Solicitação de cancelamento enviada ao iFood com sucesso.')).toBeVisible()
    await expect(detailDialog.getByText('Ação enviada: cancel')).toBeVisible()
    await expect(detailDialog.getByText('CANCELLATION_REQUESTED', { exact: true })).toBeVisible()
  })
})
