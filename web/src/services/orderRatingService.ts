import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'

/** Avaliação de pedido entregue (Delivery Fase 4) — identidade `FinalCustomer`, sempre via `portalApiClient` (`customer.jwt`). */

export interface RateOrderPayload {
  rating: number
  comment?: string
}

export interface OrderRatingResult {
  rating: number
  comment: string | null
}

/**
 * 1 avaliação por pedido. Erros de negócio (`INVALID_ORDER_STATE` — pedido
 * ainda não entregue, `ORDER_ALREADY_RATED` — já avaliado) chegam como
 * `ApiRequestError` normal, código em `error.code` — quem chama trata os 2
 * casos com mensagem amigável, não como erro genérico.
 */
export function rateOrder(orderUuid: string, payload: RateOrderPayload): Promise<OrderRatingResult> {
  return unwrap(portalApiClient.post<ApiSuccess<OrderRatingResult>>(`/portal/orders/${orderUuid}/rating`, payload))
}
