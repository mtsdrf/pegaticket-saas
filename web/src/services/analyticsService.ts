import { apiClient } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type {
  AbcDimension,
  AbcItem,
  AnalyticsPeriodParams,
  ChurnClients,
  CouponRoi,
  DeliveryOtif,
  LocationSales,
  MarginSummary,
  OverdueSale,
  OverdueType,
  PaymentDelayClient,
  RevenueConcentration,
  SalesByHour,
  SalesByLocation,
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
 * podem variar levemente (ex.: `count` vs `orders_count`). Este service é a
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

const COUNT_KEYS = ['count', 'orders_count', 'order_count', 'quantity', 'qty']
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
 * Contrato real do backend: `{ group_by, current: { from, to, total_orders,
 * total_revenue, average_ticket, buckets: [...] }, previous: { ...idem } }`
 * (`AnalyticsService::salesSummaryPeriod`). Os totais do período vêm no
 * próprio objeto do período, não num sub-objeto `totals`.
 */
function normalizePeriodTotals(raw: Raw, buckets: SalesSummaryBucket[]): SalesSummaryTotals {
  const count = pick(raw, ['total_orders', ...COUNT_KEYS])
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
      order_count: toNumber(pick(raw, ['order_count', 'orders_count', 'frequency', 'count'])),
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
    paid_orders_count: toNumber(pick(raw, ['order_count', 'paid_orders_count', 'orders_count', 'count'])),
  }))
}

const OVERDUE_TYPES = ['pagamento', 'entrega'] as const

/**
 * Divergência conhecida vs brief original: o backend NÃO expõe filtro por
 * `type` nem retorna `due_date` — o tipo (`pagamento`/`entrega`) vem como
 * campo de cada linha (`open_amount` = valor em aberto). Um pedido atrasado
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
      order_uuid: toText(pick(raw, ['order_uuid', 'uuid'])),
      client_name: toText(pick(raw, ['client_name', ...NAME_KEYS])),
      amount: toNumber(pick(raw, ['open_amount', ...AMOUNT_KEYS])),
      due_date: toText(pick(raw, ['due_date', 'expected_delivery_date', 'date'])) || null,
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

export async function getDeliveryOtif(params: AnalyticsPeriodParams): Promise<DeliveryOtif> {
  const response = await apiClient.get<ApiSuccess<DeliveryOtif>>('/reports/analytics/delivery-otif', { params })
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
