export interface Venue {
  uuid: string
  name: string
  background_image_url: string | null
  width: number | null
  height: number | null
  is_active: boolean
  published_map_version?: {
    uuid: string
    version_number: number
    is_published: boolean
    created_at: string
  } | null
  created_at: string
}

export interface VenuePayload {
  name: string
  width?: number | null
  height?: number | null
  is_active?: boolean
}

export interface VenueFilters {
  name?: string
  is_active?: boolean
  per_page?: number
  page?: number
}

export type SeatKind = 'mesa' | 'assento' | 'area' | 'camarote'
export type SeatStatus = 'disponivel' | 'bloqueado' | 'indisponivel'

export const SEAT_KIND_OPTIONS: { value: SeatKind; label: string }[] = [
  { value: 'assento', label: 'Assento' },
  { value: 'mesa', label: 'Mesa' },
  { value: 'area', label: 'Área' },
  { value: 'camarote', label: 'Camarote' },
]

export const SEAT_STATUS_OPTIONS: { value: SeatStatus; label: string }[] = [
  { value: 'disponivel', label: 'Disponível' },
  { value: 'bloqueado', label: 'Bloqueado' },
  { value: 'indisponivel', label: 'Indisponível' },
]

export interface Seat {
  uuid: string
  sector_name: string | null
  label: string
  kind: SeatKind
  capacity: number
  pos_x: number
  pos_y: number
  width: number | null
  height: number | null
  geometry_points: Array<{ x: number; y: number }>
  is_accessible: boolean
  status: SeatStatus
  created_at: string
}

export interface SeatPayload {
  sector_name?: string | null
  label: string
  kind?: SeatKind
  capacity?: number | null
  pos_x?: number | null
  pos_y?: number | null
  width?: number | null
  height?: number | null
  geometry_points?: Array<{ x: number; y: number }> | null
  is_accessible?: boolean
  status?: SeatStatus
}

export interface SeatFilters {
  kind?: SeatKind
  sector_name?: string
  per_page?: number
  page?: number
}
