import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { OrderTracking } from '../types/orderTracking'

/**
 * Endpoint 100% público (sem JWT) — o interceptor de `apiClient` só injeta
 * `Authorization` se houver token em `localStorage`, o que não quebra esta
 * chamada nem exige tratamento especial: se o cliente final abrir o link
 * num navegador sem sessão nenhuma, a requisição simplesmente vai sem header.
 */
export function getOrderTracking(uuid: string): Promise<OrderTracking> {
  return unwrap(apiClient.get<ApiSuccess<OrderTracking>>(`/rastreio/${uuid}`))
}
