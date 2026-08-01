export type TicketTypeStatus = 'rascunho' | 'ativo' | 'pausado' | 'esgotado' | 'encerrado'

export const TICKET_TYPE_STATUS_OPTIONS: { value: TicketTypeStatus; label: string }[] = [
  { value: 'rascunho', label: 'Rascunho' },
  { value: 'ativo', label: 'Ativo' },
  { value: 'pausado', label: 'Pausado' },
  { value: 'esgotado', label: 'Esgotado' },
  { value: 'encerrado', label: 'Encerrado' },
]

export interface TicketTypeEventRef {
  uuid: string
  name: string
}

export interface TicketType {
  uuid: string
  name: string
  description: string | null
  price: number
  image_url: string | null
  quantity_available: number | null
  min_per_order: number | null
  max_per_order: number | null
  sales_start_at: string | null
  sales_end_at: string | null
  status: TicketTypeStatus
  sku: string | null
  barcode: string | null
  brand: string | null
  unit: string | null
  stock_quantity: number
  event: TicketTypeEventRef | null
  created_at: string
  /** Só presente se o usuário tiver a permissão `stock.view_costs` — a chave some, não vem `null`. */
  last_purchase_cost?: number | null
}

export interface TicketTypePayload {
  name: string
  description?: string
  price: number
  event_uuid: string
  quantity_available?: number | null
  min_per_order?: number | null
  max_per_order?: number | null
  sales_start_at?: string | null
  sales_end_at?: string | null
  status?: TicketTypeStatus
  sku?: string
  barcode?: string
  brand?: string
  unit?: string
  last_purchase_cost?: number | null
}

export interface TicketTypeFilters {
  /** Busca genérica OR-contains (name, event_name) — usada pela caixa "Buscar em todos os campos". */
  q?: string
  name?: string
  event_uuid?: string
  status?: TicketTypeStatus
  price_min?: number
  price_max?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}
