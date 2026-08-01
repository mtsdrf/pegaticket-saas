import { getPortalAccessToken } from './portalApiClient'
import { publicApiClient } from './publicApiClient'
import type { ApiSuccess } from '../types/api'
import type { PaginationMeta } from '../types/pagination'
import type {
  StorefrontCategory,
  StorefrontCheckoutItemPayload,
  StorefrontCouponValidationResult,
  StorefrontEvent,
  StorefrontEventFilters,
  StorefrontEventListResult,
  StorefrontTenant,
} from '../types/storefront'

export function getStorefront(slug: string): Promise<StorefrontTenant> {
  return publicApiClient
    .get<ApiSuccess<StorefrontTenant>>(`/loja/${slug}`)
    .then((response) => response.data.data)
}

/** Vitrine estilo iFood — categorias com produto disponível, ordenadas por priority (backend). */
export function listStorefrontCategories(slug: string): Promise<StorefrontCategory[]> {
  return publicApiClient
    .get<ApiSuccess<StorefrontCategory[]>>(`/loja/${slug}/categorias`)
    .then((response) => response.data.data)
}

/**
 * Paginado, mas via `publicApiClient` (não passa por `listPaginated` de
 * `crudService.ts`, que está acoplado ao `apiClient` de staff) — mesmo
 * formato de `meta.pagination` do resto da API.
 */
export function listStorefrontEvents(slug: string, filters: StorefrontEventFilters): Promise<StorefrontEventListResult> {
  // Delivery Fase 4 — rota pública, NUNCA exige login (não usar
  // `portalApiClient` aqui, que redireciona em 401). Quando o cliente final
  // já tem um token do portal salvo (mesmo usado no checkout), anexa o
  // Bearer manualmente só para o backend marcar `is_favorited` por evento;
  // sem token, a chamada segue idêntica a antes.
  const token = getPortalAccessToken()

  return publicApiClient
    .get<ApiSuccess<StorefrontEvent[]>>(`/loja/${slug}/eventos`, {
      params: filters,
      headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    })
    .then((response) => {
      const meta = response.data.meta as { pagination?: PaginationMeta }
      return {
        items: response.data.data,
        pagination: meta.pagination ?? {
          current_page: 1,
          per_page: filters.per_page ?? response.data.data.length,
          total: response.data.data.length,
          last_page: 1,
        },
      }
    })
}

/** `GET /loja/{slug}/eventos/{eventSlug}` — detalhe público, com `ticket_types`/`event_products` aninhados. */
export function getStorefrontEvent(slug: string, eventSlug: string): Promise<StorefrontEvent> {
  const token = getPortalAccessToken()
  return publicApiClient
    .get<ApiSuccess<StorefrontEvent>>(`/loja/${slug}/eventos/${eventSlug}`, {
      headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    })
    .then((response) => response.data.data)
}

/**
 * Prévia pública de cupom (Delivery Fase 3) — sem auth, chamada antes do
 * OTP/identificação do cliente final. Não consome o limite de uso; 422 com
 * `code: INVALID_COUPON`/`COUPON_USAGE_LIMIT_REACHED` é tratado explicitamente
 * por quem chama (`StorefrontCheckoutPage`), não aqui.
 */
export function validateStorefrontCoupon(
  slug: string,
  code: string,
  items: StorefrontCheckoutItemPayload[],
): Promise<StorefrontCouponValidationResult> {
  return publicApiClient
    .post<ApiSuccess<StorefrontCouponValidationResult>>(`/loja/${slug}/cupons/validar`, { code, items })
    .then((response) => response.data.data)
}
