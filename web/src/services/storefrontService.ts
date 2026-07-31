import { getPortalAccessToken } from './portalApiClient'
import { publicApiClient } from './publicApiClient'
import type { ApiSuccess } from '../types/api'
import type { Order } from '../types/order'
import type { PaginationMeta } from '../types/pagination'
import type {
  PublicReservationTenant,
  StorefrontCategory,
  StorefrontCheckoutItemPayload,
  StorefrontCouponValidationResult,
  StorefrontListResult,
  StorefrontProduct,
  StorefrontProductFilters,
  StorefrontTableReservationPayload,
  StorefrontTenant,
} from '../types/storefront'
import type { TableReservation } from '../types/balcao'

/**
 * Leitura pública do pedido pra tela de preparo no celular (roadmap Loja) —
 * 100% anônima (`publicApiClient`), protegida só pelo `token` temporário.
 * Token inválido/expirado/inexistente → 404 genérico (`ApiRequestError`),
 * tratado por quem chama (`PrepOrderPage`) como "link expirado ou inválido".
 */
export function getStorefrontOrderPrep(uuid: string, token: string): Promise<Order> {
  return publicApiClient
    .get<ApiSuccess<Order>>(`/storefront-orders/${uuid}/prep`, { params: { token } })
    .then((response) => response.data.data)
}

export function getStorefront(slug: string): Promise<StorefrontTenant> {
  return publicApiClient
    .get<ApiSuccess<StorefrontTenant>>(`/loja/${slug}`)
    .then((response) => response.data.data)
}

export function getPublicReservationTenant(slug: string): Promise<PublicReservationTenant> {
  return publicApiClient
    .get<ApiSuccess<PublicReservationTenant>>(`/reservas/${slug}`)
    .then((response) => response.data.data)
}

/** Vitrine estilo iFood — categorias com produto disponível, ordenadas por priority (backend). */
export function listStorefrontCategories(slug: string): Promise<StorefrontCategory[]> {
  return publicApiClient
    .get<ApiSuccess<StorefrontCategory[]>>(`/loja/${slug}/categorias`)
    .then((response) => response.data.data)
}

/**
 * Consulta prévia de taxa de entrega por bairro (Delivery Fase 2) — pública,
 * sem auth. 404 com `code: DELIVERY_AREA_NOT_SERVED` quando o bairro não tem
 * taxa cadastrada pro tenant; quem chama trata esse `ApiRequestError`
 * explicitamente (não é um erro genérico de rede).
 */
export function getStorefrontDeliveryFee(slug: string, bairroUuid: string): Promise<number> {
  return publicApiClient
    .get<ApiSuccess<{ fee: number }>>(`/loja/${slug}/taxa-entrega/${bairroUuid}`)
    .then((response) => response.data.data.fee)
}

/**
 * Paginado, mas via `publicApiClient` (não passa por `listPaginated` de
 * `crudService.ts`, que está acoplado ao `apiClient` de staff) — mesmo
 * formato de `meta.pagination` do resto da API.
 */
export function listStorefrontProducts(slug: string, filters: StorefrontProductFilters): Promise<StorefrontListResult> {
  // `publicApiClient` não tem o interceptor de normalização boolean->1/0 de
  // `apiClient` (ver comentário em `apiClient.ts`) — resolve aqui, no único
  // filtro boolean deste endpoint, em vez de duplicar o interceptor.
  const { on_promotion, ...rest } = filters
  const params = { ...rest, ...(on_promotion ? { on_promotion: 1 } : {}) }

  // Delivery Fase 4 — rota pública, NUNCA exige login (não usar
  // `portalApiClient` aqui, que redireciona em 401). Quando o cliente final
  // já tem um token do portal salvo (mesmo usado no checkout), anexa o
  // Bearer manualmente só para o backend marcar `is_favorited` por produto;
  // sem token, a chamada segue idêntica a antes.
  const token = getPortalAccessToken()

  return publicApiClient
    .get<ApiSuccess<StorefrontProduct[]>>(`/loja/${slug}/produtos`, {
      params,
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

export function createStorefrontTableReservation(
  slug: string,
  payload: StorefrontTableReservationPayload,
): Promise<TableReservation> {
  return publicApiClient
    .post<ApiSuccess<TableReservation>>(`/loja/${slug}/reservas`, payload)
    .then((response) => response.data.data)
}

export function createPublicTableReservation(
  slug: string,
  payload: StorefrontTableReservationPayload,
): Promise<TableReservation> {
  return publicApiClient
    .post<ApiSuccess<TableReservation>>(`/reservas/${slug}`, payload)
    .then((response) => response.data.data)
}
