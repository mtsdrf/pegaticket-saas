export interface ReportIndicators {
  total_orders: number
  total_sales_amount: string
  average_ticket: string
  delivered_orders: number
  undelivered_orders: number
  paid_orders: number
  unpaid_orders: number
  amount_received: string
  amount_receivable: string
  current_period_total_amount: string
  previous_period_total_amount: string
  sales_growth_percentage: number | null
  comparison_current_label: string
  comparison_previous_label: string
  overdue_orders_count: number
}

export interface SalesByMonthPoint {
  month: string
  count: number
  total_amount: string
}

export interface SalesByCityPoint {
  city_name: string
  count: number
  total_amount: string
}

export interface SalesByNeighborhoodPoint {
  neighborhood_name: string
  count: number
  total_amount: string
}

export interface TopProductPoint {
  product_name: string
  quantity_sold: number
  revenue: string
}

export interface TopClientPoint {
  client_name: string
  order_count: number
  total_amount: string
}

export interface RfmClientPoint {
  client_name: string
  frequency: number
  monetary: string
  recency_days: number
  segment: string
}

export interface LatePaymentClientPoint {
  client_name: string
  avg_days_to_pay: number
  paid_orders_count: number
}

export interface OverdueSalePoint {
  order_uuid: string
  client_name: string
  amount: string
  due_date: string
  days_overdue: number
  source: 'order' | 'installment'
}

export interface ReceivablesAgingPoint {
  bucket: 'current' | 'overdue_1_30' | 'overdue_31_60' | 'overdue_61_90' | 'overdue_90_plus'
  label: string
  amount: string
  count: number
}

export interface AbcProductPoint {
  product_name: string
  revenue: string
  participation_percentage: number
  cumulative_percentage: number
  curve_class: 'A' | 'B' | 'C'
}

export interface AbcClientPoint {
  client_name: string
  revenue: string
  participation_percentage: number
  cumulative_percentage: number
  curve_class: 'A' | 'B' | 'C'
}

export interface SeasonalityMonthPoint {
  month: number
  count: number
  total_amount: string
}

export interface SeasonalityYearRow {
  year: number
  months: SeasonalityMonthPoint[]
}

export type SaleOrigin = 'staff' | 'storefront'

export interface ChannelResultPoint {
  origin: string
  order_count: number
  total_amount: string
  average_ticket: string
}

export type OperationHealthStageKey = 'approval' | 'production' | 'dispatch' | 'financial_pending'

export interface OperationHealthStageSummary {
  stage: OperationHealthStageKey
  label: string
  total: number
  attention: number
  critical: number
  oldest_minutes: number | null
}

export interface OperationHealthSummary {
  internal: Record<OperationHealthStageKey, OperationHealthStageSummary>
  totals: {
    items_requiring_attention: number
    critical_items: number
  }
}

/** Rótulo em português de cada `orders.origin` — usado no relatório "Resultado por canal" e no drill-down até o relatório de pedidos. */
export const CHANNEL_LABELS: Record<string, string> = {
  staff: 'Lançado internamente',
  storefront: 'Bilheteria online',
}

export interface ReportCharts {
  orders_by_month: SalesByMonthPoint[]
  paid_vs_unpaid: { paid: number; unpaid: number }
  delivered_vs_undelivered: { delivered: number; undelivered: number }
  received_vs_receivable: { received: string; receivable: string }
  orders_by_city: SalesByCityPoint[]
  orders_by_neighborhood: SalesByNeighborhoodPoint[]
  seasonality_matrix: SeasonalityYearRow[]
  top_products: TopProductPoint[]
  top_clients: TopClientPoint[]
  rfm_clients: RfmClientPoint[]
  late_payment_clients: LatePaymentClientPoint[]
  overdue_orders: OverdueSalePoint[]
  receivables_aging: ReceivablesAgingPoint[]
  receivables_forecast_by_month: SalesByMonthPoint[]
  abc_products: AbcProductPoint[]
  abc_clients: AbcClientPoint[]
}
