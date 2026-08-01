import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type { SalePayment } from '../types/sale'
import type { CreatePortalLinkPayload, PortalLink, PortalSaleSummary, PortalReorderItem, PortalTicket } from '../types/portal'

/** Lista agregada de compras entre todas as empresas vinculadas, mais recente primeiro. */
export function listPortalSales(): Promise<PortalSaleSummary[]> {
  return unwrap(portalApiClient.get<ApiSuccess<PortalSaleSummary[]>>('/portal/sales'))
}

/** Idempotente no backend — chamar de novo pro mesmo `sale_uuid` reaproveita o vínculo existente, não duplica. */
export function createPortalLink(payload: CreatePortalLinkPayload): Promise<PortalLink> {
  return unwrap(portalApiClient.post<ApiSuccess<PortalLink>>('/portal/links', payload))
}

/** "Comprar novamente" — itens da compra anterior com preço/disponibilidade atuais. */
export function getSaleItemsForReorder(saleUuid: string): Promise<PortalReorderItem[]> {
  return unwrap(
    portalApiClient.get<ApiSuccess<{ items: PortalReorderItem[] }>>(`/portal/sales/${saleUuid}/items`),
  ).then((result) => result.items)
}

/**
 * Cliente final solicita cancelamento. Só válido para venda
 * `origin: 'storefront'` ainda não "saiu para entrega"/"entregue" — backend
 * responde 422 fora dessa janela. `reason` é opcional (max 1000). Resposta
 * é o mesmo shape de `listPortalSales`, já com
 * `status: 'cancellation_requested'`.
 */
export function requestSaleCancellation(saleUuid: string, reason?: string): Promise<PortalSaleSummary> {
  return unwrap(
    portalApiClient.post<ApiSuccess<PortalSaleSummary>>(
      `/portal/sales/${saleUuid}/request-cancellation`,
      reason?.trim() ? { reason: reason.trim() } : undefined,
    ),
  )
}

/**
 * Cobrança Pix da própria venda no checkout público. Backend rejeita (422
 * `INVALID_ORDER_STATE`) se a venda já
 * estiver pago/cancelado ou já tiver uma cobrança Pix ativa — quem chama
 * trata esse código explicitamente, nunca assume que a chamada sempre cria
 * uma cobrança nova.
 */
export function createSalePixCharge(saleUuid: string): Promise<SalePayment> {
  return unwrap(portalApiClient.post<ApiSuccess<SalePayment>>(`/portal/sales/${saleUuid}/payment-charge`))
}

/** "Meus ingressos" — ingressos emitidos para uma compra específica do comprador autenticado. */
export function listSaleTickets(saleUuid: string): Promise<PortalTicket[]> {
  return unwrap(portalApiClient.get<ApiSuccess<PortalTicket[]>>(`/portal/sales/${saleUuid}/tickets`))
}
