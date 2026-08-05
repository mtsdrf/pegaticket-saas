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
  paid_sales_count: number
}

export type OverdueType = 'pagamento' | 'entrega'

export interface OverdueSale {
  sale_uuid: string
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
  sales_with_coupon: CouponRoiGroup
  sales_without_coupon: CouponRoiGroup
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

export interface CheckinInsightsTotals {
  total_reads: number
  granted_reads: number
  warning_reads: number
  blocked_reads: number
  reentries: number
  unique_granted_tickets: number
  attendance_rate: number
}

export interface CheckinInsightsSessionRow {
  session_uuid: string | null
  session_name: string
  event_uuid: string
  event_name: string
  total_reads: number
  granted_reads: number
  warning_reads: number
  blocked_reads: number
  unique_granted_tickets: number
  attendance_rate: number
}

export interface CheckinInsightsTicketTypeRow {
  ticket_type_uuid: string
  ticket_type_name: string
  event_uuid: string
  event_name: string
  total_reads: number
  granted_reads: number
  warning_reads: number
  blocked_reads: number
  unique_granted_tickets: number
  attendance_rate: number
}

export interface CheckinInsights {
  totals: CheckinInsightsTotals
  by_session: CheckinInsightsSessionRow[]
  by_ticket_type: CheckinInsightsTicketTypeRow[]
}

// ---------------------------------------------------------------------------
// Vendas por dimensão configurável (roadmap Fase A1) — unifica
// top-products/top-clients/by-channel num único endpoint aditivo.
// ---------------------------------------------------------------------------

export type SalesDimension = 'ticket_type' | 'client' | 'origin'

export interface SalesByDimensionItem {
  key: string
  label: string
  order_count: number | null
  quantity_sold: number | null
  revenue: number
}

export interface SalesByDimension {
  dimension: SalesDimension
  items: SalesByDimensionItem[]
}

// ---------------------------------------------------------------------------
// Relatório de pagamentos básico (roadmap Fase A1) — aprovação/recusa por
// período, sem roteamento entre gateways (só PagBank existe).
// ---------------------------------------------------------------------------

export interface PaymentsSummaryGroup {
  count: number
  total_amount: number
}

export interface PaymentsSummary {
  from: string
  to: string
  confirmed: PaymentsSummaryGroup
  rejected: PaymentsSummaryGroup
  pending_approval: PaymentsSummaryGroup
  approval_rate_percentage: number
  rejection_rate_percentage: number
}

// ---------------------------------------------------------------------------
// Relatório de afiliados consolidado (roadmap Fase A2).
// ---------------------------------------------------------------------------

export interface AffiliateReportItem {
  affiliate_uuid: string
  affiliate_name: string
  tracking_code: string
  affiliate_status: string
  attributed_sales_count: number
  attributed_revenue: number
  commission_amount: number
  commission_paid_amount: number
  roi_percentage: number | null
}

export interface AffiliatesReport {
  from: string
  to: string
  totals: {
    affiliates_count: number
    attributed_revenue: number
    commission_amount: number
    commission_paid_amount: number
    roi_percentage: number | null
  }
  items: AffiliateReportItem[]
}

// ---------------------------------------------------------------------------
// Relatório de cupons consolidado (roadmap Fase A2).
// ---------------------------------------------------------------------------

export interface CouponReportItem {
  coupon_uuid: string
  coupon_code: string
  coupon_type: string
  coupon_is_active: boolean
  usage_count: number
  distinct_customers_count: number
  paid_usage_count: number
  conversion_rate_percentage: number
  total_discount_amount: number
  revenue_generated: number
  avg_uses_per_customer: number
  abuse_signal: boolean
}

export interface CouponsReport {
  from: string
  to: string
  totals: {
    coupons_used_count: number
    total_redemptions: number
    total_discount_amount: number
    coupons_with_abuse_signal_count: number
  }
  items: CouponReportItem[]
}

// ---------------------------------------------------------------------------
// Relatório de reembolsos/chargebacks dedicado (roadmap Fase A2).
// ---------------------------------------------------------------------------

export interface RefundsReportTypeGroup {
  count: number
  total_amount: number
}

export interface RefundsReportReason {
  reason: string
  count: number
  total_amount: number
}

export interface RefundsReport {
  from: string
  to: string
  totals: {
    refunds_count: number
    total_refunded_amount: number
    refund_rate_percentage: number
    refund_amount_rate_percentage: number
  }
  by_type: {
    total: RefundsReportTypeGroup
    parcial: RefundsReportTypeGroup
  }
  top_reasons: RefundsReportReason[]
}

// ---------------------------------------------------------------------------
// Relatório de inventário/assentos avançado (roadmap Fase A2) — ocupação
// agregada por setor de um evento específico (event_uuid obrigatório).
// ---------------------------------------------------------------------------

export interface InventorySector {
  sector_name: string
  total_seats: number
  sold_seats: number
  held_seats_now: number
  blocked_seats: number
  available_seats: number
  occupancy_percentage: number
}

export interface InventoryReport {
  event_uuid: string
  event_name: string
  has_seat_map: boolean
  totals: {
    total_seats: number
    sold_seats: number
    held_seats_now: number
    blocked_seats: number
    available_seats: number
    occupancy_percentage: number
  }
  sectors: InventorySector[]
}

// ---------------------------------------------------------------------------
// Funil de conversão anônimo (roadmap A2) — sessões únicas por etapa do
// storefront (visualização, seleção de ingresso, reserva, checkout,
// pagamento confirmado) e taxa de conversão entre etapas consecutivas.
// ---------------------------------------------------------------------------

export interface FunnelStepResult {
  step: string
  label: string
  session_count: number
  conversion_from_previous_percentage: number | null
  conversion_from_first_percentage: number | null
}

export interface FunnelReport {
  from: string
  to: string
  event_uuid: string | null
  steps: FunnelStepResult[]
}

// ---------------------------------------------------------------------------
// Comparação entre eventos (roadmap A2, último item) — curva de vendas
// indexada por "dias desde abertura de vendas" de cada evento (não data
// de calendário), mais totais comparativos.
// ---------------------------------------------------------------------------

export interface CompareEventsSeriesPoint {
  day: number
  order_count: number
  quantity_sold: number
  revenue: number
}

export interface CompareEventsEntry {
  event_uuid: string
  event_name: string
  sales_opened_at: string
  totals: {
    total_orders: number
    total_quantity_sold: number
    total_revenue: number
    average_ticket: number
    tickets_issued: number
    commercial_capacity: number
    occupancy_percentage: number
  }
  series: CompareEventsSeriesPoint[]
}

export interface CompareEventsReport {
  events: CompareEventsEntry[]
}
