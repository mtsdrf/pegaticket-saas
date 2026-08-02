import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'

/** Avaliação de venda concluída para o cliente final — sempre via `portalApiClient` (`customer.jwt`). */

export interface RateSalePayload {
  rating: number
  comment?: string
}

export interface SaleRatingResult {
  rating: number
  comment: string | null
}

/**
 * 1 avaliação por venda. Erros de negócio (`INVALID_ORDER_STATE` — venda
 * ainda não concluída, `ORDER_ALREADY_RATED` — já avaliada) chegam como
 * `ApiRequestError` normal, código em `error.code` — quem chama trata os 2
 * casos com mensagem amigável, não como erro genérico.
 */
export function rateSale(saleUuid: string, payload: RateSalePayload): Promise<SaleRatingResult> {
  return unwrap(portalApiClient.post<ApiSuccess<SaleRatingResult>>(`/portal/sales/${saleUuid}/rating`, payload))
}
