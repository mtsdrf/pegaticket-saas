import { unwrap } from './apiClient'
import { publicApiClient } from './publicApiClient'
import type { ApiSuccess } from '../types/api'
import type { StorefrontCheckoutPayload, StorefrontCheckoutResult } from '../types/storefront'

/**
 * Checkout público da loja: sem portal auth. Erros 422 de negócio
 * (cupom, meio de pagamento, hold inválido etc.) são tratados por quem
 * chama (`StorefrontCheckoutPage`), não aqui.
 */
export function checkout(slug: string, payload: StorefrontCheckoutPayload): Promise<StorefrontCheckoutResult> {
  return unwrap(publicApiClient.post<ApiSuccess<StorefrontCheckoutResult>>(`/loja/${slug}/checkout`, payload))
}
