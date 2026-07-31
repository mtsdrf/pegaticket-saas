import type { GeocodeStatus } from './location'

export type RouteType = 'delivery' | 'collection'

export interface RouteEndereco {
  logradouro: string
  numero: string
  bairro_name: string | null
  cidade_name: string | null
  lat: number | null
  lng: number | null
  geocode_status: GeocodeStatus
}

export interface RouteOrderRef {
  uuid: string
  total_amount: number
  is_paid: boolean
  is_delivered: boolean
  is_installment: boolean
  expected_delivery_date: string | null
}

export interface RouteInstallmentRef {
  uuid: string
  order_uuid: string
  amount: number
  due_date: string
  is_overdue: boolean
  is_paid: boolean
}

export interface RouteStop {
  client_uuid: string
  client_name: string
  phone_primary: string | null
  dia_ideal_name: string | null
  periodo_ideal_name: string | null
  endereco: RouteEndereco
  orders: RouteOrderRef[]
  installments: RouteInstallmentRef[]
}

export interface RouteCandidatesResponse {
  type: RouteType
  date: string
  stops: RouteStop[]
}

/** `RouteStop` com localização confirmada (`endereco.lat`/`lng` não nulos) — só essas entram na otimização OSRM. */
export interface LocatedRouteStop extends RouteStop {
  endereco: RouteEndereco & { lat: number; lng: number }
}

/**
 * `lat`/`lng` não nulos já é o sinal correto e suficiente de "localizado"
 * — `pending`/`failed` nunca têm coordenada, `success` e `manual` sempre
 * têm (ver `EnderecoService`/`GeocodeEnderecoJob` no backend). Checar só
 * `geocode_status === 'success'` excluía endereço com lat/lng MANUAL da
 * otimização de rota por engano (mesmo bug de raiz do `GeocodeStatusChip`
 * quebrando em runtime — o enum de 4 valores só era tratado como 3 em
 * vários lugares do frontend).
 */
export function isLocatedStop(stop: RouteStop): stop is LocatedRouteStop {
  return stop.endereco.lat !== null && stop.endereco.lng !== null
}

/** Parada já na sequência otimizada de visita (ordenada por `waypoint_index` do OSRM). */
export interface OptimizedStop {
  position: number
  stop: LocatedRouteStop
  /** `[lon, lat]` ajustado à malha viária pelo OSRM. */
  location: [number, number]
}
