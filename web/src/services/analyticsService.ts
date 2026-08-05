import { apiClient } from './apiClient'
import type { ApiSuccess } from '../types/api'
import { extractFilenameFromContentDisposition, triggerBlobDownload } from '../utils/fileDownload'
import type {
  AbcDimension,
  AbcItem,
  AffiliatesReport,
  AnalyticsPeriodParams,
  ChurnClients,
  CheckinInsights,
  CompareEventsReport,
  CouponRoi,
  CouponsReport,
  FunnelReport,
  InventoryReport,
  LocationSales,
  MarginSummary,
  OverdueSale,
  OverdueType,
  PaymentDelayClient,
  PaymentsSummary,
  RefundsReport,
  RevenueConcentration,
  SalesByDimension,
  SalesByHour,
  SalesByLocation,
  SalesDimension,
  SalesGroupBy,
  SalesHistoryYear,
  SalesSummary,
  SalesSummaryBucket,
  SalesSummaryTotals,
  TopClient,
  TopProduct,
} from '../types/analytics'
import type { PaginatedResult, PaginationMeta } from '../types/pagination'

/**
 * Os endpoints `/reports/analytics/*` estão sendo desenvolvidos em paralelo
 * no backend — os nomes de endpoint são finais, mas os nomes exatos de campo
 * podem variar levemente (ex.: `count` vs `sales_count`). Este service é a
 * única camada que conhece o shape cru da resposta: normaliza tudo para os
 * view models de `types/analytics.ts` aceitando os sinônimos prováveis, de
 * forma que uma divergência pequena de contrato não quebre página nenhuma.
 */
type Raw = Record<string, unknown>

function pick(raw: Raw, keys: string[]): unknown {
  for (const key of keys) {
    if (raw[key] !== undefined && raw[key] !== null) return raw[key]
  }
  return undefined
}

function toNumber(value: unknown): number {
  const numeric = typeof value === 'string' ? Number(value) : (value as number)
  return Number.isFinite(numeric) ? numeric : 0
}

function toText(value: unknown): string {
  return typeof value === 'string' ? value : value === undefined || value === null ? '' : String(value)
}

function asArray(value: unknown): Raw[] {
  return Array.isArray(value) ? (value as Raw[]) : []
}

const COUNT_KEYS = ['count', 'sales_count', 'order_count', 'quantity', 'qty']
const AMOUNT_KEYS = ['total_amount', 'revenue', 'amount', 'faturamento']
const TICKET_KEYS = ['average_ticket', 'avg_ticket', 'ticket_medio', 'ticket']
const NAME_KEYS = ['name', 'label']

function normalizeBucket(raw: Raw): SalesSummaryBucket {
  return {
    period: toText(pick(raw, ['period', 'bucket', 'date', 'month', 'day'])),
    count: toNumber(pick(raw, COUNT_KEYS)),
    total_amount: toNumber(pick(raw, AMOUNT_KEYS)),
    average_ticket: toNumber(pick(raw, TICKET_KEYS)),
  }
}

function totalsFromBuckets(buckets: SalesSummaryBucket[]): SalesSummaryTotals {
  const count = buckets.reduce((sum, bucket) => sum + bucket.count, 0)
  const totalAmount = buckets.reduce((sum, bucket) => sum + bucket.total_amount, 0)
  return {
    count,
    total_amount: totalAmount,
    average_ticket: count > 0 ? totalAmount / count : 0,
  }
}

/**
 * Contrato real do backend: `{ group_by, current: { from, to, total_sales,
 * total_revenue, average_ticket, buckets: [...] }, previous: { ...idem } }`
 * (`AnalyticsService::salesSummaryPeriod`). Os totais do período vêm no
 * próprio objeto do período, não num sub-objeto `totals`.
 */
function normalizePeriodTotals(raw: Raw, buckets: SalesSummaryBucket[]): SalesSummaryTotals {
  const count = pick(raw, ['total_sales', ...COUNT_KEYS])
  const amount = pick(raw, ['total_revenue', ...AMOUNT_KEYS])
  if (count === undefined && amount === undefined) return totalsFromBuckets(buckets)

  return {
    count: toNumber(count),
    total_amount: toNumber(amount),
    average_ticket: toNumber(pick(raw, TICKET_KEYS)),
  }
}

export async function getSalesSummary(
  params: AnalyticsPeriodParams & { group_by: SalesGroupBy },
): Promise<SalesSummary> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/sales-summary', { params })
  const raw = response.data.data

  const current = (pick(raw, ['current']) as Raw | undefined) ?? raw
  const buckets = asArray(pick(current, ['buckets', 'items'])).map(normalizeBucket)
  const totals = normalizePeriodTotals(current, buckets)

  const previousRaw = pick(raw, ['previous', 'previous_period']) as Raw | undefined
  const previous = previousRaw
    ? normalizePeriodTotals(previousRaw, asArray(pick(previousRaw, ['buckets', 'items'])).map(normalizeBucket))
    : null

  return { buckets, totals, previous }
}

export async function getTopProducts(params: AnalyticsPeriodParams & { limit: number }): Promise<TopProduct[]> {
  const response = await apiClient.get<ApiSuccess<unknown>>('/reports/analytics/top-products', { params })
  const items = Array.isArray(response.data.data)
    ? (response.data.data as Raw[])
    : asArray(pick(response.data.data as Raw, ['items', 'products']))

  return items.map((raw) => ({
    name: toText(pick(raw, ['product_name', ...NAME_KEYS])),
    quantity_sold: toNumber(pick(raw, ['quantity_sold', ...COUNT_KEYS])),
    revenue: toNumber(pick(raw, AMOUNT_KEYS)),
  }))
}

function normalizeLocation(raw: Raw, nameKeys: string[]): LocationSales {
  return {
    name: toText(pick(raw, [...nameKeys, ...NAME_KEYS])),
    count: toNumber(pick(raw, COUNT_KEYS)),
    total_amount: toNumber(pick(raw, AMOUNT_KEYS)),
  }
}

export async function getSalesByLocation(params: AnalyticsPeriodParams): Promise<SalesByLocation> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/sales-by-location', { params })
  const raw = response.data.data

  return {
    cities: asArray(pick(raw, ['cities', 'by_city', 'cidades'])).map((item) =>
      normalizeLocation(item, ['city_name', 'cidade_name', 'city']),
    ),
    neighborhoods: asArray(pick(raw, ['neighborhoods', 'by_neighborhood', 'bairros'])).map((item) =>
      normalizeLocation(item, ['neighborhood_name', 'bairro_name', 'neighborhood']),
    ),
  }
}

export async function getSalesHistory(): Promise<SalesHistoryYear[]> {
  const response = await apiClient.get<ApiSuccess<unknown>>('/reports/analytics/sales-history')
  const rows = Array.isArray(response.data.data)
    ? (response.data.data as Raw[])
    : asArray(pick(response.data.data as Raw, ['years', 'matrix', 'items']))

  return rows.map((raw) => ({
    year: toNumber(pick(raw, ['year', 'ano'])),
    months: asArray(pick(raw, ['months', 'meses'])).map((month) => ({
      month: toNumber(pick(month, ['month', 'mes'])),
      count: toNumber(pick(month, COUNT_KEYS)),
      total_amount: toNumber(pick(month, AMOUNT_KEYS)),
    })),
  }))
}

const RFM_SEGMENTS = ['vip', 'recorrente', 'em_risco', 'inativo'] as const

export async function getTopClients(params: AnalyticsPeriodParams & { limit: number }): Promise<TopClient[]> {
  const response = await apiClient.get<ApiSuccess<unknown>>('/reports/analytics/top-clients', { params })
  const items = Array.isArray(response.data.data)
    ? (response.data.data as Raw[])
    : asArray(pick(response.data.data as Raw, ['items', 'clients']))

  return items.map((raw) => {
    const segment = toText(pick(raw, ['rfm', 'segment', 'rfm_segment', 'rfm_label'])).toLowerCase()
    return {
      name: toText(pick(raw, ['client_name', ...NAME_KEYS])),
      order_count: toNumber(pick(raw, ['order_count', 'sales_count', 'frequency', 'count'])),
      total_amount: toNumber(pick(raw, [...AMOUNT_KEYS, 'monetary'])),
      rfm: (RFM_SEGMENTS as readonly string[]).includes(segment) ? (segment as TopClient['rfm']) : null,
    }
  })
}

export async function getPaymentDelays(
  params: AnalyticsPeriodParams & { limit: number },
): Promise<PaymentDelayClient[]> {
  const response = await apiClient.get<ApiSuccess<unknown>>('/reports/analytics/payment-delays', { params })
  const items = Array.isArray(response.data.data)
    ? (response.data.data as Raw[])
    : asArray(pick(response.data.data as Raw, ['items', 'clients']))

  return items.map((raw) => ({
    name: toText(pick(raw, ['client_name', ...NAME_KEYS])),
    avg_days_to_pay: toNumber(pick(raw, ['avg_days_to_pay', 'average_days_to_pay', 'avg_delay_days', 'avg_days_late'])),
    paid_sales_count: toNumber(pick(raw, ['order_count', 'paid_sales_count', 'sales_count', 'count'])),
  }))
}

const OVERDUE_TYPES = ['pagamento', 'entrega'] as const

/**
 * Divergência conhecida vs brief original: o backend NÃO expõe filtro por
 * `type` nem retorna `due_date` — o tipo (`pagamento`/`entrega`) vem como
 * campo de cada linha (`open_amount` = valor em aberto). Uma venda atrasada
 * em pagamento E entrega aparece duas vezes, uma por tipo (decisão do backend).
 */
export async function listOverdueOrders(
  params: AnalyticsPeriodParams & { page: number; per_page: number },
): Promise<PaginatedResult<OverdueSale>> {
  const response = await apiClient.get<ApiSuccess<unknown>>('/reports/analytics/overdue-sales', { params })
  const meta = response.data.meta as { pagination?: PaginationMeta }
  const items = Array.isArray(response.data.data)
    ? (response.data.data as Raw[])
    : asArray(pick(response.data.data as Raw, ['items', 'sales']))

  const rows = items.map((raw) => {
    const type = toText(pick(raw, ['type', 'tipo', 'source'])).toLowerCase()
    return {
      sale_uuid: toText(pick(raw, ['sale_uuid', 'uuid'])),
      client_name: toText(pick(raw, ['client_name', ...NAME_KEYS])),
      amount: toNumber(pick(raw, ['open_amount', ...AMOUNT_KEYS])),
      due_date: toText(pick(raw, ['due_date', 'date'])) || null,
      days_overdue: toNumber(pick(raw, ['days_overdue', 'days_late'])),
      type: (OVERDUE_TYPES as readonly string[]).includes(type) ? (type as OverdueType) : null,
    }
  })

  return {
    items: rows,
    pagination: meta.pagination ?? {
      current_page: params.page,
      per_page: params.per_page,
      total: rows.length,
      last_page: 1,
    },
  }
}

const ABC_CLASSES = ['A', 'B', 'C'] as const

export async function getAbcAnalysis(
  params: AnalyticsPeriodParams & { dimension: AbcDimension },
): Promise<AbcItem[]> {
  const response = await apiClient.get<ApiSuccess<unknown>>('/reports/analytics/abc-analysis', { params })
  const items = Array.isArray(response.data.data)
    ? (response.data.data as Raw[])
    : asArray(pick(response.data.data as Raw, ['items', 'curve']))

  return items.map((raw) => {
    const curveClass = toText(pick(raw, ['curve_class', 'class', 'classe'])).toUpperCase()
    return {
      name: toText(pick(raw, ['product_name', 'client_name', ...NAME_KEYS])),
      revenue: toNumber(pick(raw, AMOUNT_KEYS)),
      participation_percentage: toNumber(pick(raw, ['participation_percentage', 'participation', 'share'])),
      cumulative_percentage: toNumber(pick(raw, ['cumulative_percentage', 'cumulative', 'accumulated_percentage'])),
      curve_class: (ABC_CLASSES as readonly string[]).includes(curveClass) ? (curveClass as AbcItem['curve_class']) : 'C',
    }
  })
}

/**
 * Indicadores de negócio abaixo têm contrato final e testado no backend
 * (`AnalyticsService`) — desembrulham `data` direto, sem a normalização
 * defensiva de sinônimos usada nos endpoints acima. Dinheiro fica como string.
 */
export async function getMarginSummary(params: AnalyticsPeriodParams): Promise<MarginSummary> {
  const response = await apiClient.get<ApiSuccess<MarginSummary>>('/reports/analytics/margin-summary', { params })
  return response.data.data
}

export async function getRevenueConcentration(params: AnalyticsPeriodParams): Promise<RevenueConcentration> {
  const response = await apiClient.get<ApiSuccess<RevenueConcentration>>('/reports/analytics/revenue-concentration', { params })
  return response.data.data
}

export async function getCouponRoi(params: AnalyticsPeriodParams): Promise<CouponRoi> {
  const response = await apiClient.get<ApiSuccess<CouponRoi>>('/reports/analytics/coupon-roi', { params })
  return response.data.data
}

export async function getChurnClients(): Promise<ChurnClients> {
  const response = await apiClient.get<ApiSuccess<ChurnClients>>('/reports/analytics/churn-clients')
  return response.data.data
}

export async function getSalesByHour(params?: AnalyticsPeriodParams): Promise<SalesByHour> {
  const response = await apiClient.get<ApiSuccess<SalesByHour>>('/reports/analytics/sales-by-hour', { params })
  return response.data.data
}

export async function getCheckinInsights(params: AnalyticsPeriodParams): Promise<CheckinInsights> {
  const response = await apiClient.get<ApiSuccess<CheckinInsights>>('/reports/analytics/checkin-insights', { params })
  return response.data.data
}

/**
 * Vendas por dimensão configurável (roadmap Fase A1) — unifica
 * top-products/top-clients/by-channel num único endpoint; `revenue` vem
 * como string decimal do backend (`formatMoney`), normalizado para number
 * aqui como o resto deste service.
 */
export async function getSalesByDimension(
  params: AnalyticsPeriodParams & { dimension: SalesDimension; limit: number },
): Promise<SalesByDimension> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/sales-by-dimension', { params })
  const raw = response.data.data
  const items = asArray(pick(raw, ['items'])).map((item) => ({
    key: toText(pick(item, ['key'])),
    label: toText(pick(item, ['label'])),
    order_count: item.order_count === null || item.order_count === undefined ? null : toNumber(item.order_count),
    quantity_sold: item.quantity_sold === null || item.quantity_sold === undefined ? null : toNumber(item.quantity_sold),
    revenue: toNumber(pick(item, ['revenue'])),
  }))

  return {
    dimension: (toText(pick(raw, ['dimension'])) || params.dimension) as SalesDimension,
    items,
  }
}

/** Relatório de pagamentos básico (roadmap Fase A1): dinheiro fica como string do backend, normalizado aqui. */
export async function getPaymentsSummary(params: AnalyticsPeriodParams): Promise<PaymentsSummary> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/payments', { params })
  const raw = response.data.data
  const group = (key: string) => {
    const g = (pick(raw, [key]) as Raw | undefined) ?? {}
    return { count: toNumber(pick(g, ['count'])), total_amount: toNumber(pick(g, ['total_amount'])) }
  }

  return {
    from: toText(pick(raw, ['from'])),
    to: toText(pick(raw, ['to'])),
    confirmed: group('confirmed'),
    rejected: group('rejected'),
    pending_approval: group('pending_approval'),
    approval_rate_percentage: toNumber(pick(raw, ['approval_rate_percentage'])),
    rejection_rate_percentage: toNumber(pick(raw, ['rejection_rate_percentage'])),
  }
}

/** Relatório de afiliados consolidado (roadmap Fase A2): dinheiro vem como string decimal do backend. */
export async function getAffiliatesReport(params: AnalyticsPeriodParams & { limit: number }): Promise<AffiliatesReport> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/affiliates', { params })
  const raw = response.data.data
  const totals = (pick(raw, ['totals']) as Raw | undefined) ?? {}

  const items = asArray(pick(raw, ['items'])).map((item) => ({
    affiliate_uuid: toText(pick(item, ['affiliate_uuid'])),
    affiliate_name: toText(pick(item, ['affiliate_name'])),
    tracking_code: toText(pick(item, ['tracking_code'])),
    affiliate_status: toText(pick(item, ['affiliate_status'])),
    attributed_sales_count: toNumber(pick(item, ['attributed_sales_count'])),
    attributed_revenue: toNumber(pick(item, ['attributed_revenue'])),
    commission_amount: toNumber(pick(item, ['commission_amount'])),
    commission_paid_amount: toNumber(pick(item, ['commission_paid_amount'])),
    roi_percentage: item.roi_percentage === null || item.roi_percentage === undefined ? null : toNumber(item.roi_percentage),
  }))

  return {
    from: toText(pick(raw, ['from'])),
    to: toText(pick(raw, ['to'])),
    totals: {
      affiliates_count: toNumber(pick(totals, ['affiliates_count'])),
      attributed_revenue: toNumber(pick(totals, ['attributed_revenue'])),
      commission_amount: toNumber(pick(totals, ['commission_amount'])),
      commission_paid_amount: toNumber(pick(totals, ['commission_paid_amount'])),
      roi_percentage: totals.roi_percentage === null || totals.roi_percentage === undefined ? null : toNumber(totals.roi_percentage),
    },
    items,
  }
}

/** Relatório de cupons consolidado (roadmap Fase A2): dinheiro vem como string decimal do backend. */
export async function getCouponsReport(params: AnalyticsPeriodParams & { limit: number }): Promise<CouponsReport> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/coupons', { params })
  const raw = response.data.data
  const totals = (pick(raw, ['totals']) as Raw | undefined) ?? {}

  const items = asArray(pick(raw, ['items'])).map((item) => ({
    coupon_uuid: toText(pick(item, ['coupon_uuid'])),
    coupon_code: toText(pick(item, ['coupon_code'])),
    coupon_type: toText(pick(item, ['coupon_type'])),
    coupon_is_active: Boolean(pick(item, ['coupon_is_active'])),
    usage_count: toNumber(pick(item, ['usage_count'])),
    distinct_customers_count: toNumber(pick(item, ['distinct_customers_count'])),
    paid_usage_count: toNumber(pick(item, ['paid_usage_count'])),
    conversion_rate_percentage: toNumber(pick(item, ['conversion_rate_percentage'])),
    total_discount_amount: toNumber(pick(item, ['total_discount_amount'])),
    revenue_generated: toNumber(pick(item, ['revenue_generated'])),
    avg_uses_per_customer: toNumber(pick(item, ['avg_uses_per_customer'])),
    abuse_signal: Boolean(pick(item, ['abuse_signal'])),
  }))

  return {
    from: toText(pick(raw, ['from'])),
    to: toText(pick(raw, ['to'])),
    totals: {
      coupons_used_count: toNumber(pick(totals, ['coupons_used_count'])),
      total_redemptions: toNumber(pick(totals, ['total_redemptions'])),
      total_discount_amount: toNumber(pick(totals, ['total_discount_amount'])),
      coupons_with_abuse_signal_count: toNumber(pick(totals, ['coupons_with_abuse_signal_count'])),
    },
    items,
  }
}

/** Relatório de reembolsos/chargebacks dedicado (roadmap Fase A2). */
export async function getRefundsReport(params: AnalyticsPeriodParams): Promise<RefundsReport> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/refunds', { params })
  const raw = response.data.data
  const totals = (pick(raw, ['totals']) as Raw | undefined) ?? {}
  const byType = (pick(raw, ['by_type']) as Raw | undefined) ?? {}

  const typeGroup = (key: string) => {
    const g = (pick(byType, [key]) as Raw | undefined) ?? {}
    return { count: toNumber(pick(g, ['count'])), total_amount: toNumber(pick(g, ['total_amount'])) }
  }

  const topReasons = asArray(pick(raw, ['top_reasons'])).map((item) => ({
    reason: toText(pick(item, ['reason'])),
    count: toNumber(pick(item, ['count'])),
    total_amount: toNumber(pick(item, ['total_amount'])),
  }))

  return {
    from: toText(pick(raw, ['from'])),
    to: toText(pick(raw, ['to'])),
    totals: {
      refunds_count: toNumber(pick(totals, ['refunds_count'])),
      total_refunded_amount: toNumber(pick(totals, ['total_refunded_amount'])),
      refund_rate_percentage: toNumber(pick(totals, ['refund_rate_percentage'])),
      refund_amount_rate_percentage: toNumber(pick(totals, ['refund_amount_rate_percentage'])),
    },
    by_type: {
      total: typeGroup('total'),
      parcial: typeGroup('parcial'),
    },
    top_reasons: topReasons,
  }
}

/**
 * Relatório de inventário/assentos avançado (roadmap Fase A2) —
 * `event_uuid` sempre obrigatório (regra transversal de filtro ativo).
 */
export async function getInventoryReport(params: { event_uuid: string }): Promise<InventoryReport> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/inventory', { params })
  const raw = response.data.data
  const totals = (pick(raw, ['totals']) as Raw | undefined) ?? {}

  const sectors = asArray(pick(raw, ['sectors'])).map((item) => ({
    sector_name: toText(pick(item, ['sector_name'])),
    total_seats: toNumber(pick(item, ['total_seats'])),
    sold_seats: toNumber(pick(item, ['sold_seats'])),
    held_seats_now: toNumber(pick(item, ['held_seats_now'])),
    blocked_seats: toNumber(pick(item, ['blocked_seats'])),
    available_seats: toNumber(pick(item, ['available_seats'])),
    occupancy_percentage: toNumber(pick(item, ['occupancy_percentage'])),
  }))

  return {
    event_uuid: toText(pick(raw, ['event_uuid'])),
    event_name: toText(pick(raw, ['event_name'])),
    has_seat_map: Boolean(pick(raw, ['has_seat_map'])),
    totals: {
      total_seats: toNumber(pick(totals, ['total_seats'])),
      sold_seats: toNumber(pick(totals, ['sold_seats'])),
      held_seats_now: toNumber(pick(totals, ['held_seats_now'])),
      blocked_seats: toNumber(pick(totals, ['blocked_seats'])),
      available_seats: toNumber(pick(totals, ['available_seats'])),
      occupancy_percentage: toNumber(pick(totals, ['occupancy_percentage'])),
    },
    sectors,
  }
}

/**
 * Funil de conversão anônimo (roadmap A2) — período opcional (default 30
 * dias no backend) + `event_uuid` opcional.
 */
export async function getFunnelReport(params: Partial<AnalyticsPeriodParams> & { event_uuid?: string }): Promise<FunnelReport> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/funnel', { params })
  const raw = response.data.data

  const steps = asArray(pick(raw, ['steps'])).map((item) => ({
    step: toText(pick(item, ['step'])),
    label: toText(pick(item, ['label'])),
    session_count: toNumber(pick(item, ['session_count'])),
    conversion_from_previous_percentage:
      pick(item, ['conversion_from_previous_percentage']) === null ? null : toNumber(pick(item, ['conversion_from_previous_percentage'])),
    conversion_from_first_percentage:
      pick(item, ['conversion_from_first_percentage']) === null ? null : toNumber(pick(item, ['conversion_from_first_percentage'])),
  }))

  return {
    from: toText(pick(raw, ['from'])),
    to: toText(pick(raw, ['to'])),
    event_uuid: (pick(raw, ['event_uuid']) as string | null) ?? null,
    steps,
  }
}

/**
 * Comparação entre eventos (roadmap A2, último item) — 2 a 5 event_uuids
 * do mesmo tenant. Sem período: a base de comparação é "dias desde
 * abertura de vendas" de cada evento, calculada no backend.
 */
export async function getCompareEventsReport(params: { event_uuids: string[] }): Promise<CompareEventsReport> {
  const response = await apiClient.get<ApiSuccess<Raw>>('/reports/analytics/compare-events', {
    params,
    paramsSerializer: {
      indexes: false,
    },
  })
  const raw = response.data.data

  const events = asArray(pick(raw, ['events'])).map((item) => {
    const totals = (pick(item, ['totals']) as Raw | undefined) ?? {}
    const series = asArray(pick(item, ['series'])).map((point) => ({
      day: toNumber(pick(point, ['day'])),
      order_count: toNumber(pick(point, ['order_count'])),
      quantity_sold: toNumber(pick(point, ['quantity_sold'])),
      revenue: toNumber(pick(point, ['revenue'])),
    }))

    return {
      event_uuid: toText(pick(item, ['event_uuid'])),
      event_name: toText(pick(item, ['event_name'])),
      sales_opened_at: toText(pick(item, ['sales_opened_at'])),
      totals: {
        total_orders: toNumber(pick(totals, ['total_orders'])),
        total_quantity_sold: toNumber(pick(totals, ['total_quantity_sold'])),
        total_revenue: toNumber(pick(totals, ['total_revenue'])),
        average_ticket: toNumber(pick(totals, ['average_ticket'])),
        tickets_issued: toNumber(pick(totals, ['tickets_issued'])),
        commercial_capacity: toNumber(pick(totals, ['commercial_capacity'])),
        occupancy_percentage: toNumber(pick(totals, ['occupancy_percentage'])),
      },
      series,
    }
  })

  return { events }
}

async function downloadXlsx(url: string, params: object, fallbackFilename: string): Promise<void> {
  const response = await apiClient.get(url, { params, responseType: 'blob' })
  const filename = extractFilenameFromContentDisposition(response.headers['content-disposition'], fallbackFilename)
  triggerBlobDownload(response.data, filename)
}

/** Exportação XLSX de vendas por dimensão (roadmap A2) — MESMA base de getSalesByDimension. */
export function exportSalesByDimensionXlsx(
  params: AnalyticsPeriodParams & { dimension: SalesDimension; limit: number },
): Promise<void> {
  return downloadXlsx('/reports/analytics/sales-by-dimension/xlsx', params, 'vendas-por-dimensao.xlsx')
}

/** Exportação XLSX do relatório de pagamentos (roadmap A2) — MESMA base de getPaymentsSummary. */
export function exportPaymentsXlsx(params: AnalyticsPeriodParams): Promise<void> {
  return downloadXlsx('/reports/analytics/payments/xlsx', params, 'relatorio-pagamentos.xlsx')
}
