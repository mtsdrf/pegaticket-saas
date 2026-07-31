import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type { StorefrontCheckoutPayload, StorefrontCheckoutResult } from '../types/storefront'

/**
 * Único endpoint da loja que exige identidade — via `portalApiClient`
 * (Bearer do `FinalCustomer`, já obtido pelo mesmo fluxo de OTP do Portal),
 * diferente do resto do catálogo (`storefrontService.ts`, 100% anônimo via
 * `publicApiClient`). Erro 422 com `code: 'INSUFFICIENT_STOCK'` é tratado
 * explicitamente por quem chama (`StorefrontCheckoutPage`), não aqui.
 */
export function checkout(slug: string, payload: StorefrontCheckoutPayload): Promise<StorefrontCheckoutResult> {
  return unwrap(portalApiClient.post<ApiSuccess<StorefrontCheckoutResult>>(`/loja/${slug}/checkout`, payload))
}
