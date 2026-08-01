import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { SaleTracking } from '../types/saleTracking'

/**
 * Endpoint 100% público (sem JWT) — o interceptor de `apiClient` só injeta
 * `Authorization` se houver token em `localStorage`, o que não quebra esta
 * chamada nem exige tratamento especial: se o cliente final abrir o link
 * num navegador sem sessão nenhuma, a requisição simplesmente vai sem header.
 */
export function getSaleTracking(uuid: string): Promise<SaleTracking> {
  return unwrap(apiClient.get<ApiSuccess<SaleTracking>>(`/rastreio/${uuid}`))
}
