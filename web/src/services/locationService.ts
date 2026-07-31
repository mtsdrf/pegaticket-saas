import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { Bairro, Cidade, Estado, ReverseGeocodeResult } from '../types/location'

export function getEstados(): Promise<Estado[]> {
  return unwrap(apiClient.get<ApiSuccess<Estado[]>>('/estados', { params: { per_page: 100 } }))
}

/** Sempre filtrado por estado — nunca carregar todas as cidades do Brasil de uma vez. */
export function getCidades(estadoUuid: string): Promise<Cidade[]> {
  return unwrap(
    apiClient.get<ApiSuccess<Cidade[]>>('/cidades', {
      params: { estado_uuid: estadoUuid, per_page: 100 },
    }),
  )
}

/** Sempre filtrado por cidade. */
export function getBairros(cidadeUuid: string): Promise<Bairro[]> {
  return unwrap(
    apiClient.get<ApiSuccess<Bairro[]>>('/bairros', {
      params: { cidade_uuid: cidadeUuid, per_page: 100 },
    }),
  )
}

/**
 * Sempre 200 (autenticado, sem tenant/perm) — qualquer campo pode vir `null`
 * se não achou correspondência; nunca erro. Chamador trata `null` como
 * "usuário preenche esse campo manualmente".
 */
export function reverseGeocode(lat: number, lng: number): Promise<ReverseGeocodeResult> {
  return unwrap(apiClient.get<ApiSuccess<ReverseGeocodeResult>>('/location/reverse-geocode', { params: { lat, lng } }))
}
