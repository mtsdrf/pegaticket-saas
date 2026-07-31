export interface Estado {
  uuid: string
  name: string
  uf: string
  is_active: boolean
  created_at: string
}

export interface EstadoPayload {
  name: string
  uf: string
  is_active?: boolean
}

export interface EstadoFilters {
  name?: string
  uf?: string
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface Cidade {
  uuid: string
  name: string
  is_active: boolean
  estado_uuid: string
  estado_name: string
  created_at: string
}

export interface CidadePayload {
  name: string
  estado_uuid: string
  is_active?: boolean
}

export interface CidadeFilters {
  name?: string
  estado_name?: string
  estado_uuid?: string
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface Bairro {
  uuid: string
  name: string
  is_active: boolean
  cidade_uuid: string
  cidade_name: string
  created_at: string
}

export interface BairroPayload {
  name: string
  cidade_uuid: string
  is_active?: boolean
}

export interface BairroFilters {
  name?: string
  cidade_name?: string
  cidade_uuid?: string
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface Endereco {
  uuid: string
  logradouro: string
  numero: string | null
  complemento: string | null
  cep: string | null
  is_active: boolean
  estado_uuid: string
  estado_name: string
  cidade_uuid: string
  cidade_name: string
  bairro_uuid: string
  bairro_name: string
  lat: number | null
  lng: number | null
  geocode_status: GeocodeStatus
  created_at: string
}

/** `manual` = lat/lng informados diretamente (bypassa o Nominatim), ver `EnderecoService::create()/update()`. */
export type GeocodeStatus = 'pending' | 'success' | 'failed' | 'manual'

export interface EnderecoPayload {
  logradouro: string
  numero?: string
  complemento?: string
  cep?: string
  is_active?: boolean
  estado_uuid: string
  cidade_uuid: string
  bairro_uuid: string
}

export interface EnderecoFilters {
  logradouro?: string
  cep?: string
  cidade_name?: string
  bairro_name?: string
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

/** Resposta de `GET /location/reverse-geocode` — qualquer campo pode vir `null` se não achou correspondência. */
export interface ReverseGeocodeResult {
  estado_uuid: string | null
  cidade_uuid: string | null
  bairro_uuid: string | null
  logradouro: string | null
  cep: string | null
}
