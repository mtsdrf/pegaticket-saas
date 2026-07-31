export type TicketBatchStatus = 'rascunho' | 'ativo' | 'pausado' | 'esgotado' | 'encerrado'

export const TICKET_BATCH_STATUS_OPTIONS: { value: TicketBatchStatus; label: string }[] = [
  { value: 'rascunho', label: 'Rascunho' },
  { value: 'ativo', label: 'Ativo' },
  { value: 'pausado', label: 'Pausado' },
  { value: 'esgotado', label: 'Esgotado' },
  { value: 'encerrado', label: 'Encerrado' },
]

export interface TicketBatchTicketTypeRef {
  uuid: string
  name: string
}

export interface TicketBatch {
  uuid: string
  name: string
  price: number
  quantity: number
  quantity_sold: number
  quantity_available: number
  starts_at: string | null
  ends_at: string | null
  priority: number | null
  auto_advance: boolean
  status: TicketBatchStatus
  ticket_type: TicketBatchTicketTypeRef | null
  created_at: string
}

export interface TicketBatchPayload {
  name: string
  price: number
  quantity: number
  starts_at?: string | null
  ends_at?: string | null
  priority?: number | null
  auto_advance?: boolean
  status?: TicketBatchStatus
}

export interface TicketBatchFilters {
  status?: TicketBatchStatus
  sort_by?: 'name' | 'priority' | 'status'
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}
