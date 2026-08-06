export interface ReportIndicators {
  total_sales: number
  total_sales_amount: string
  average_ticket: string
  completed_sales: number
  uncompleted_sales: number
  paid_sales: number
  unpaid_sales: number
  amount_received: string
  amount_receivable: string
  current_period_total_amount: string
  previous_period_total_amount: string
  sales_growth_percentage: number | null
  comparison_current_label: string
  comparison_previous_label: string
  net_revenue_amount: string
  tickets_issued: number
  commercial_capacity: number
  occupancy_percentage: number
}

export interface SalesByMonthPoint {
  month: string
  count: number
  total_amount: string
}

export interface TopTicketTypePoint {
  ticket_type_name: string
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

/** Rótulo em português de cada `sales.origin` — usado no relatório "Resultado por canal" e no drill-down até o relatório de vendas. */
export const CHANNEL_LABELS: Record<string, string> = {
  staff: 'Lançado internamente',
  storefront: 'Bilheteria online',
}

export interface ReportCharts {
  sales_by_month: SalesByMonthPoint[]
  paid_vs_unpaid: { paid: number; unpaid: number }
  completed_vs_uncompleted: { completed: number; uncompleted: number }
  received_vs_receivable: { received: string; receivable: string }
  seasonality_matrix: SeasonalityYearRow[]
  top_ticket_types: TopTicketTypePoint[]
  top_clients: TopClientPoint[]
  rfm_clients: RfmClientPoint[]
}

/** Alertas básicos do Home (roadmap Fase A1) — estoque baixo e pagamento, calculados on-the-fly. */
export type AlertType =
  | 'low_stock'
  | 'payment_rejection_rate'
  | 'payment_pending_queue'
  | 'daily_revenue_anomaly'
  | 'daily_sales_count_anomaly'
  | 'daily_payment_rejection_rate_anomaly'
export type AlertSeverity = 'warning' | 'critical'

export interface ReportAlert {
  type: AlertType
  severity: AlertSeverity
  title: string
  message: string
  meta: Record<string, unknown>
}
