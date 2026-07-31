import type { PaginationMeta } from './pagination'

export interface ClientReportOrder {
  uuid: string
  total_amount: number
  is_paid: boolean
  paid_at: string | null
  is_delivered: boolean
  delivered_at: string | null
  created_at: string
}

export interface ClientReportItem {
  uuid: string
  name: string
  phone_primary: string | null
  phone_secondary: string | null
  endereco: {
    logradouro: string | null
    cep: string | null
    estado_name: string | null
    cidade_name: string | null
    bairro_name: string | null
  } | null
  orders_count: number
  orders?: ClientReportOrder[]
}

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

export interface ClientReportFilters {
  name?: string
  phone_primary?: string
  cidade_uuid?: string
  bairro_uuid?: string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface ClientReportListResult {
  items: ClientReportItem[]
  pagination: PaginationMeta
}

export interface ReceivableReportItem {
  row_key: string
  source: 'order' | 'installment'
  source_label: string
  order_uuid: string
  installment_uuid: string | null
  client_name: string
  client_phone_primary: string | null
  amount: number
  due_date: string
  created_at: string
  is_overdue: boolean
  days_overdue: number
}

export interface ReceivableReportFilters {
  client_name?: string
  amount_min?: number
  amount_max?: number
  is_overdue?: boolean
  aging_bucket?: 'current' | 'overdue_1_30' | 'overdue_31_60' | 'overdue_61_90' | 'overdue_90_plus'
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface ReceivableSummary {
  open_amount: string
  open_count: number
  overdue_amount: string
  overdue_count: number
  aging_buckets: Array<{
    bucket: 'current' | 'overdue_1_30' | 'overdue_31_60' | 'overdue_61_90' | 'overdue_90_plus'
    label: string
    amount: string
    count: number
  }>
}

export interface ReceivableInteraction {
  uuid: string
  interaction_type: 'note' | 'promise' | 'whatsapp'
  interaction_type_label: string
  channel: 'manual' | 'phone' | 'whatsapp' | null
  notes: string | null
  promised_amount: string | null
  promised_due_date: string | null
  contacted_at: string
  created_by_name: string | null
  installment_uuid: string | null
  created_at: string
}

export interface CreateReceivableInteractionPayload {
  installment_uuid?: string
  interaction_type: 'note' | 'promise' | 'whatsapp'
  channel?: 'manual' | 'phone' | 'whatsapp'
  notes?: string
  promised_amount?: number
  promised_due_date?: string
  contacted_at?: string
}
