export interface SaleReportFilters {
  client_uuid?: string
  client_name?: string
  is_paid?: boolean
  is_completed?: boolean
  date_from?: string
  date_to?: string
  /** Drill-down do relatório "Resultado por canal" (`/relatorios/canais`) — `?origin=X` na URL. */
  origin?: string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface SaleReportSummary {
  total: number
  completed_percentage: number
  paid_percentage: number
  overdue_percentage: number
}
