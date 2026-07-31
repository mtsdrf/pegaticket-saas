import { publicApiClient } from './publicApiClient'
import type { ApiSuccess } from '../types/api'
import type { Bairro, Cidade, Estado, ReverseGeocodeResult } from '../types/location'

/**
 * Estado/Cidade/Bairro via rota pública dedicada da loja
 * (`/loja/localizacoes/*`) — NUNCA `locationService.ts` (`/estados`,
 * `/cidades`, `/bairros`), que exige o guard de staff (`jwt`) e rejeita
 * explicitamente um token de `FinalCustomer`. Ver
 * `App\Http\Controllers\Storefront\StorefrontLocationController` no
 * backend (endpoint aditivo, criado junto com este frontend — descoberta
 * de 2026-07-16 documentada lá).
 */
export function getStorefrontEstados(): Promise<Estado[]> {
  return publicApiClient
    .get<ApiSuccess<Estado[]>>('/loja/localizacoes/estados')
    .then((response) => response.data.data)
}

export function getStorefrontCidades(estadoUuid: string): Promise<Cidade[]> {
  return publicApiClient
    .get<ApiSuccess<Cidade[]>>('/loja/localizacoes/cidades', { params: { estado_uuid: estadoUuid } })
    .then((response) => response.data.data)
}

export function getStorefrontBairros(cidadeUuid: string): Promise<Bairro[]> {
  return publicApiClient
    .get<ApiSuccess<Bairro[]>>('/loja/localizacoes/bairros', { params: { cidade_uuid: cidadeUuid } })
    .then((response) => response.data.data)
}

/**
 * Consulta de CEP (ViaCEP) — mesmo shape de resposta de `reverseGeocode()`
 * abaixo (`estado_uuid`/`cidade_uuid`/`bairro_uuid`/`logradouro`/`cep`),
 * reaproveita `ReverseGeocodeResult`. 404 `CEP_NOT_FOUND` é tratado por
 * quem chama (não é um erro genérico de rede).
 */
export function lookupStorefrontCep(cep: string): Promise<ReverseGeocodeResult> {
  return publicApiClient
    .get<ApiSuccess<ReverseGeocodeResult>>(`/loja/localizacoes/cep/${cep}`)
    .then((response) => response.data.data)
}

/**
 * Reverse geocode público — mesmo `ReverseGeocodeResult` de
 * `locationService.ts` (staff), mas via rota pública dedicada da loja
 * (customer.jwt não é aceito pelo guard de staff, ver
 * StorefrontLocationController::reverseGeocode()).
 */
export function reverseGeocodeStorefront(lat: number, lng: number): Promise<ReverseGeocodeResult> {
  return publicApiClient
    .get<ApiSuccess<ReverseGeocodeResult>>('/loja/localizacoes/reverse-geocode', { params: { lat, lng } })
    .then((response) => response.data.data)
}
