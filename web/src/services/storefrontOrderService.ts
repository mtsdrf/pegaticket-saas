import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Order, OrderFilters } from '../types/order'

/**
 * Tela dedicada de gestão de pedidos da loja (/pedidos-loja) — mesmo
 * contrato de `orderService.ts`, mas contra `/storefront-orders/...`
 * (permissão `storefront-orders,*`, independente de `orders,*`). O
 * backend já força `origin=storefront` no servidor — nenhum filtro de
 * origin precisa (ou pode) ser enviado daqui.
 */
export function listStorefrontOrders(filters: OrderFilters): Promise<PaginatedResult<Order>> {
  return listPaginated<Order>('/storefront-orders', filters)
}

/** Detalhe completo de UM pedido da loja (itens/telefone/endereço/cupom) — usado no accordion "Ver detalhes" (roadmap Loja). */
export function getStorefrontOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.get<ApiSuccess<Order>>(`/storefront-orders/${uuid}`))
}

/** Resposta de `POST /storefront-orders/{uuid}/prep-link` — token temporário (expira) pro QR de preparo sem login. */
export interface PrepLinkResult {
  token: string
  expires_at: string
}

export function generatePrepLink(uuid: string): Promise<PrepLinkResult> {
  return unwrap(apiClient.post<ApiSuccess<PrepLinkResult>>(`/storefront-orders/${uuid}/prep-link`))
}

export function approveStorefrontOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>(`/storefront-orders/${uuid}/approve`))
}

/** `reason` opcional — vira `cancellation_reason` no pedido recusado. */
export function rejectStorefrontOrder(uuid: string, reason?: string): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>(`/storefront-orders/${uuid}/reject`, reason ? { reason } : undefined))
}

export function cancelStorefrontOrder(uuid: string, cancellation_reason: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/storefront-orders/${uuid}/cancel`, { cancellation_reason }))
}

export function dispatchStorefrontOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/storefront-orders/${uuid}/dispatch`))
}

export function deliverStorefrontOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/storefront-orders/${uuid}/deliver`))
}

/** Reverte "saiu para entrega" de volta pra "em preparação" (`is_out_for_delivery` → false). */
export function undispatchStorefrontOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/storefront-orders/${uuid}/undispatch`))
}

/** Reverte "entregue" de volta pra "saiu para entrega" (`is_delivered` → false). */
export function undeliverStorefrontOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/storefront-orders/${uuid}/undeliver`))
}

/** Conclui o pedido marcando-o como PAGO (`is_paid` → true) — ação financeira real. */
export function payStorefrontOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/storefront-orders/${uuid}/pay`))
}
