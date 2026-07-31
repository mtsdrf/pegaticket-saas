export type EventSessionStatus =
  | 'rascunho'
  | 'agendado'
  | 'vendas_abertas'
  | 'vendas_encerradas'
  | 'realizado'
  | 'cancelado'

export const EVENT_SESSION_STATUS_OPTIONS: { value: EventSessionStatus; label: string }[] = [
  { value: 'rascunho', label: 'Rascunho' },
  { value: 'agendado', label: 'Agendado' },
  { value: 'vendas_abertas', label: 'Vendas abertas' },
  { value: 'vendas_encerradas', label: 'Vendas encerradas' },
  { value: 'realizado', label: 'Realizado' },
  { value: 'cancelado', label: 'Cancelado' },
]

export interface EventSessionEventRef {
  uuid: string
  name: string
}

export interface EventSession {
  uuid: string
  name: string | null
  starts_at: string
  ends_at: string
  gate_opens_at: string | null
  capacity: number | null
  status: EventSessionStatus
  sales_start_at: string | null
  sales_end_at: string | null
  event: EventSessionEventRef | null
  created_at: string
}

export interface EventSessionPayload {
  name?: string | null
  starts_at: string
  ends_at: string
  gate_opens_at?: string | null
  capacity?: number | null
  status?: EventSessionStatus
  sales_start_at?: string | null
  sales_end_at?: string | null
}

export interface EventSessionFilters {
  status?: EventSessionStatus
  sort_by?: 'starts_at' | 'status'
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}
