import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { Endereco, EnderecoPayload } from '../types/location'

/**
 * Endereço próprio da empresa (loja pública) — `GET/PUT /store-settings/address`,
 * gated por `storefront,update` no backend. Reaproveita o model genérico
 * `Endereco` (mesmo shape de `EnderecoResource`). `GET` devolve `null` enquanto
 * a empresa não cadastrou endereço; `PUT` cria na primeira vez e atualiza nas
 * seguintes (substituição completa, cadeia geográfica obrigatória).
 */
export function getStoreAddress(): Promise<Endereco | null> {
  return unwrap(apiClient.get<ApiSuccess<Endereco | null>>('/store-settings/address'))
}

export function updateStoreAddress(payload: EnderecoPayload): Promise<Endereco> {
  return unwrap(apiClient.put<ApiSuccess<Endereco>>('/store-settings/address', payload))
}
