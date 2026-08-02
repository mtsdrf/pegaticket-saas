export type EventType = 'ingresso' | 'inscricao' | 'mesa' | 'assento' | 'misto'
export type EventVisibility = 'public' | 'hidden' | 'private' | 'exclusive'
export type EventStatus =
  | 'rascunho'
  | 'agendado'
  | 'publicado'
  | 'vendas_pausadas'
  | 'esgotado'
  | 'encerrado'
  | 'cancelado'
  | 'arquivado'

export const EVENT_TYPE_OPTIONS: { value: EventType; label: string }[] = [
  { value: 'ingresso', label: 'Ingresso' },
  { value: 'inscricao', label: 'Inscrição' },
  { value: 'mesa', label: 'Mesa' },
  { value: 'assento', label: 'Assento' },
  { value: 'misto', label: 'Misto' },
]

export const EVENT_VISIBILITY_OPTIONS: { value: EventVisibility; label: string }[] = [
  { value: 'public', label: 'Público' },
  { value: 'hidden', label: 'Oculto (só por link)' },
  { value: 'private', label: 'Privado' },
  { value: 'exclusive', label: 'Exclusivo' },
]

export const EVENT_STATUS_OPTIONS: { value: EventStatus; label: string }[] = [
  { value: 'rascunho', label: 'Rascunho' },
  { value: 'agendado', label: 'Agendado' },
  { value: 'publicado', label: 'Publicado' },
  { value: 'vendas_pausadas', label: 'Vendas pausadas' },
  { value: 'esgotado', label: 'Esgotado' },
  { value: 'encerrado', label: 'Encerrado' },
  { value: 'cancelado', label: 'Cancelado' },
  { value: 'arquivado', label: 'Arquivado' },
]

export interface EventCategoryRef {
  uuid: string
  name: string
}

export interface EventTicketTypeRef {
  uuid: string
  name: string
}

export interface EventProductRef {
  uuid: string
  name: string
}

export interface EventVenueRef {
  uuid: string
  name: string
  map_version_uuid: string
  map_version_number: number
}

export interface Event {
  uuid: string
  name: string
  slug: string
  description_short: string | null
  description_full: string | null
  cover_image_url: string | null
  type: EventType
  location_name: string | null
  location_address: string | null
  /** Latitude do local do evento — opcional, usada para exibir o pin no mapa. */
  location_lat: number | null
  /** Longitude do local do evento — opcional, usada para exibir o pin no mapa. */
  location_lng: number | null
  starts_at: string
  ends_at: string
  visibility: EventVisibility
  status: EventStatus
  reentry_enabled: boolean
  max_reentries: number | null
  reentry_cooldown_minutes: number | null
  category: EventCategoryRef | null
  venue: EventVenueRef | null
  /** Só presente quando o registro é carregado com o detalhe completo (relação `ticketTypes` carregada). */
  ticket_types?: EventTicketTypeRef[]
  /** Só presente quando o registro é carregado com o detalhe completo (relação `eventProducts` carregada). */
  event_products?: EventProductRef[]
  created_at: string
}

export interface EventPayload {
  name: string
  slug: string
  event_category_uuid?: string | null
  venue_uuid?: string | null
  description_short?: string
  description_full?: string
  type?: EventType
  location_name?: string
  location_address?: string
  location_lat?: number | null
  location_lng?: number | null
  starts_at: string
  ends_at: string
  visibility?: EventVisibility
  status?: EventStatus
  reentry_enabled?: boolean
  max_reentries?: number | null
  reentry_cooldown_minutes?: number | null
}

export interface EventFilters {
  q?: string
  name?: string
  event_category_uuid?: string
  type?: EventType
  status?: EventStatus
  visibility?: EventVisibility
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}
