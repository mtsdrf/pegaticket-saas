/**
 * Construtor de relatórios personalizados (roadmap 5.6). Fontes de dados,
 * dimensões e métricas SEMPRE vêm do endpoint `/custom-report-definitions/schema`
 * (espelho de `App\Support\Report\CustomReportFieldWhitelist` no backend) —
 * o frontend nunca inventa uma chave, só oferece as que o backend informou.
 */
export type CustomReportDataSource = 'sales' | 'payments' | 'checkins' | 'finance' | 'crm'

export interface CustomReportFieldOption {
  key: string
  label: string
}

export interface CustomReportSchemaEntry {
  data_source: CustomReportDataSource
  dimensions: CustomReportFieldOption[]
  metrics: CustomReportFieldOption[]
}

export interface CustomReportCalculatedMetric {
  name: string
  formula: string
}

export interface CustomReportFilters {
  date_from?: string
  date_to?: string
  [dimensionKey: string]: string | undefined
}

export interface CustomReportDefinition {
  uuid: string
  name: string
  data_source: CustomReportDataSource
  dimensions: string[]
  metrics: string[]
  calculated_metrics: CustomReportCalculatedMetric[]
  filters: CustomReportFilters
  created_at: string | null
  updated_at: string | null
}

export interface CustomReportBuilderState {
  data_source: CustomReportDataSource | ''
  dimensions: string[]
  metrics: string[]
  calculated_metrics: CustomReportCalculatedMetric[]
  filters: CustomReportFilters
}

export type CustomReportResultRow = Record<string, string | number | boolean | null>

export interface CustomReportPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface CustomReportExecutionResult {
  rows: CustomReportResultRow[]
  pagination: CustomReportPagination
}

export const CUSTOM_REPORT_LIMITS = {
  maxDimensions: 3,
  maxMetrics: 10,
  maxCalculatedMetrics: 5,
  maxFilters: 10,
  maxPerPage: 100,
} as const
