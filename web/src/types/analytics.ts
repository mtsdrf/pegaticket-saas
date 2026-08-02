export type SalesGroupBy = 'day' | 'month'

export interface SalesSummaryBucket {
  period: string
  count: number
  total_amount: number
  average_ticket: number
}

export interface SalesSummaryTotals {
  count: number
  total_amount: number
  average_ticket: number
}

export interface SalesSummary {
  buckets: SalesSummaryBucket[]
  totals: SalesSummaryTotals
  previous: SalesSummaryTotals | null
}

export interface TopProduct {
  name: string
  quantity_sold: number
  revenue: number
}

export interface LocationSales {
  name: string
  count: number
  total_amount: number
}

export interface SalesByLocation {
  cities: LocationSales[]
  neighborhoods: LocationSales[]
}

export interface SalesHistoryMonth {
  month: number
  count: number
  total_amount: number
}

export interface SalesHistoryYear {
  year: number
  months: SalesHistoryMonth[]
}

export type RfmSegment = 'vip' | 'recorrente' | 'em_risco' | 'inativo'

export interface TopClient {
  name: string
  order_count: number
  total_amount: number
  rfm: RfmSegment | null
}

export interface PaymentDelayClient {
  name: string
  avg_days_to_pay: number
  paid_orders_count: number
}

export type OverdueType = 'pagamento' | 'entrega'

export interface OverdueSale {
  order_uuid: string
  client_name: string
  amount: number
  due_date: string | null
  days_overdue: number
  type: OverdueType | null
}

export type AbcDimension = 'products' | 'clients'

export type AbcClass = 'A' | 'B' | 'C'

export interface AbcItem {
  name: string
  revenue: number
  participation_percentage: number
  cumulative_percentage: number
  curve_class: AbcClass
}

export interface AnalyticsPeriodParams {
  from: string
  to: string
}

// ---------------------------------------------------------------------------
// Indicadores de negócio da aba "Financeiro" (dinheiro sempre string decimal).
// ---------------------------------------------------------------------------

export interface MarginSummary {
  total_revenue: string
  total_revenue_with_known_cost: string
  total_cost: string
  gross_margin_amount: string
  gross_margin_percentage: number
  coverage_percentage: number
}

export interface RevenueConcentration {
  total_revenue: string
  top10_revenue: string
  concentration_percentage: number
}

export interface CouponRoiGroup {
  count: number
  total_amount: string
  average_ticket: string
}

export interface CouponRoi {
  orders_with_coupon: CouponRoiGroup
  orders_without_coupon: CouponRoiGroup
  total_discount_amount: string
  ticket_lift_percentage: number
}

export interface ChurnTopClient {
  client_name: string
  last_order_at: string
  monthly_revenue_at_risk: string
}

export interface ChurnClients {
  churned_clients_count: number
  estimated_monthly_revenue_at_risk: string
  top_clients: ChurnTopClient[]
}

// ---------------------------------------------------------------------------
// Mapa de calor dia da semana × hora (aba Sazonalidade).
// ---------------------------------------------------------------------------

export interface SalesByHourCell {
  day_of_week: number
  hour: number
  count: number
  total_amount: string
}

export interface SalesByHour {
  cells: SalesByHourCell[]
}
