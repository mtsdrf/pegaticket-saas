export type EventProductStatus = 'rascunho' | 'ativo' | 'pausado' | 'esgotado' | 'encerrado'
export type EventProductKind = 'addon' | 'parking'

export const EVENT_PRODUCT_STATUS_OPTIONS: { value: EventProductStatus; label: string }[] = [
  { value: 'rascunho', label: 'Rascunho' },
  { value: 'ativo', label: 'Ativo' },
  { value: 'pausado', label: 'Pausado' },
  { value: 'esgotado', label: 'Esgotado' },
  { value: 'encerrado', label: 'Encerrado' },
]

export const EVENT_PRODUCT_KIND_OPTIONS: { value: EventProductKind; label: string }[] = [
  { value: 'addon', label: 'Adicional' },
  { value: 'parking', label: 'Estacionamento' },
]

export interface EventProductEventRef {
  uuid: string
  name: string
}

export interface EventProduct {
  uuid: string
  name: string
  description: string | null
  price: number
  quantity_available: number | null
  max_per_order: number | null
  sales_start_at: string | null
  sales_end_at: string | null
  kind: EventProductKind
  requires_plate: boolean
  requires_model: boolean
  requires_color: boolean
  status: EventProductStatus
  event: EventProductEventRef | null
  created_at: string
}

export interface EventProductPayload {
  name: string
  description?: string
  price: number
  event_uuid: string
  quantity_available?: number | null
  max_per_order?: number | null
  sales_start_at?: string | null
  sales_end_at?: string | null
  kind?: EventProductKind
  requires_plate?: boolean
  requires_model?: boolean
  requires_color?: boolean
  status?: EventProductStatus
}

export interface EventProductFilters {
  q?: string
  name?: string
  event_uuid?: string
  kind?: EventProductKind
  status?: EventProductStatus
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}
