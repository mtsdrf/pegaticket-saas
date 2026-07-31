export interface OrderReportFilters {
  client_uuid?: string
  client_name?: string
  cidade_uuid?: string
  bairro_uuid?: string
  is_paid?: boolean
  is_delivered?: boolean
  date_from?: string
  date_to?: string
  /** Drill-down do relatório "Resultado por canal" (`/relatorios/canais`) — `?origin=X` na URL. */
  origin?: string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface OrderReportSummary {
  total: number
  delivered_percentage: number
  paid_percentage: number
  overdue_percentage: number
}
