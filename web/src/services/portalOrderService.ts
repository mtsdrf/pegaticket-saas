import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type { OrderPayment } from '../types/order'
import type { CreatePortalLinkPayload, PortalLink, PortalOrderSummary, PortalReorderItem } from '../types/portal'

/** Lista agregada de pedidos entre todas as lojas vinculadas, mais recente primeiro (ordenação já vem do backend). */
export function listPortalOrders(): Promise<PortalOrderSummary[]> {
  return unwrap(portalApiClient.get<ApiSuccess<PortalOrderSummary[]>>('/portal/orders'))
}

/** Idempotente no backend — chamar de novo pro mesmo `order_uuid` reaproveita o vínculo existente, não duplica. */
export function createPortalLink(payload: CreatePortalLinkPayload): Promise<PortalLink> {
  return unwrap(portalApiClient.post<ApiSuccess<PortalLink>>('/portal/links', payload))
}

/** "Pedir de novo" (Delivery Fase 4) — itens do pedido antigo com preço/disponibilidade atuais do produto. */
export function getOrderItemsForReorder(orderUuid: string): Promise<PortalReorderItem[]> {
  return unwrap(
    portalApiClient.get<ApiSuccess<{ items: PortalReorderItem[] }>>(`/portal/orders/${orderUuid}/items`),
  ).then((result) => result.items)
}

/**
 * Cliente final solicita cancelamento (roadmap A4). Só válido para pedido
 * `origin: 'storefront'` ainda não "saiu para entrega"/"entregue" — backend
 * responde 422 fora dessa janela. `reason` é opcional (max 1000). Resposta
 * é o mesmo shape de `listPortalOrders` (um `PortalOrderResource`), já com
 * `status: 'cancellation_requested'`.
 */
export function requestOrderCancellation(orderUuid: string, reason?: string): Promise<PortalOrderSummary> {
  return unwrap(
    portalApiClient.post<ApiSuccess<PortalOrderSummary>>(
      `/portal/orders/${orderUuid}/request-cancellation`,
      reason?.trim() ? { reason: reason.trim() } : undefined,
    ),
  )
}

/**
 * Cobrança Pix do próprio pedido (roadmap Fase B, item 1 — checkout Pix na
 * loja pública). Backend rejeita (422 `INVALID_ORDER_STATE`) se o pedido já
 * estiver pago/cancelado ou já tiver uma cobrança Pix ativa — quem chama
 * trata esse código explicitamente, nunca assume que a chamada sempre cria
 * uma cobrança nova.
 */
export function createOrderPixCharge(orderUuid: string): Promise<OrderPayment> {
  return unwrap(portalApiClient.post<ApiSuccess<OrderPayment>>(`/portal/orders/${orderUuid}/payment-charge`))
}
