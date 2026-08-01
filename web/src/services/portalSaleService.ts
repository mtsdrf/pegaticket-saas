import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type { SalePayment } from '../types/sale'
import type { CreatePortalLinkPayload, PortalLink, PortalSaleSummary, PortalReorderItem, PortalTicket } from '../types/portal'

/** Lista agregada de pedidos entre todas as lojas vinculadas, mais recente primeiro (ordenação já vem do backend). */
export function listPortalSales(): Promise<PortalSaleSummary[]> {
  return unwrap(portalApiClient.get<ApiSuccess<PortalSaleSummary[]>>('/portal/sales'))
}

/** Idempotente no backend — chamar de novo pro mesmo `order_uuid` reaproveita o vínculo existente, não duplica. */
export function createPortalLink(payload: CreatePortalLinkPayload): Promise<PortalLink> {
  return unwrap(portalApiClient.post<ApiSuccess<PortalLink>>('/portal/links', payload))
}

/** "Pedir de novo" (Delivery Fase 4) — itens do pedido antigo com preço/disponibilidade atuais do produto. */
export function getSaleItemsForReorder(orderUuid: string): Promise<PortalReorderItem[]> {
  return unwrap(
    portalApiClient.get<ApiSuccess<{ items: PortalReorderItem[] }>>(`/portal/sales/${orderUuid}/items`),
  ).then((result) => result.items)
}

/**
 * Cliente final solicita cancelamento (roadmap A4). Só válido para pedido
 * `origin: 'storefront'` ainda não "saiu para entrega"/"entregue" — backend
 * responde 422 fora dessa janela. `reason` é opcional (max 1000). Resposta
 * é o mesmo shape de `listPortalSales` (um `PortalOrderResource`), já com
 * `status: 'cancellation_requested'`.
 */
export function requestSaleCancellation(orderUuid: string, reason?: string): Promise<PortalSaleSummary> {
  return unwrap(
    portalApiClient.post<ApiSuccess<PortalSaleSummary>>(
      `/portal/sales/${orderUuid}/request-cancellation`,
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
export function createSalePixCharge(orderUuid: string): Promise<SalePayment> {
  return unwrap(portalApiClient.post<ApiSuccess<SalePayment>>(`/portal/sales/${orderUuid}/payment-charge`))
}

/** "Meus ingressos" — ingressos emitidos para um pedido específico do comprador autenticado (`PortalTicketResource`). */
export function listSaleTickets(orderUuid: string): Promise<PortalTicket[]> {
  return unwrap(portalApiClient.get<ApiSuccess<PortalTicket[]>>(`/portal/sales/${orderUuid}/tickets`))
}
