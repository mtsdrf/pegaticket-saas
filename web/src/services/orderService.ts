import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type {
  Order,
  OrderFilters,
  OrderInstallmentPayload,
  OrderInstallmentsReallocatePayload,
  OrderPayload,
  OrderUpdateItemsPayload,
} from '../types/order'

export function listOrders(filters: OrderFilters): Promise<PaginatedResult<Order>> {
  return listPaginated<Order>('/orders', filters)
}

export function getOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.get<ApiSuccess<Order>>(`/orders/${uuid}`))
}

export function createOrder(payload: OrderPayload): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>('/orders', payload))
}

/**
 * Edita itens/cabeçalho de um pedido já criado. `items` é o array COMPLETO
 * (item com `uuid` atualiza, sem `uuid` cria, item existente ausente do
 * array é removido). Só permitido enquanto o pedido não estiver
 * entregue/pago/cancelado — 422 `INVALID_ORDER_STATE` fora dessa janela.
 */
export function updateOrderItems(uuid: string, payload: OrderUpdateItemsPayload): Promise<Order> {
  return unwrap(apiClient.put<ApiSuccess<Order>>(`/orders/${uuid}/items`, payload))
}

export function deliverOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/orders/${uuid}/deliver`))
}

export function dispatchOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/orders/${uuid}/dispatch`))
}

export function undispatchOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/orders/${uuid}/undispatch`))
}

export function undeliverOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/orders/${uuid}/undeliver`))
}

/**
 * `paidAt` (YYYY-MM-DD) é opcional e pode ser uma data futura (pagamento agendado) —
 * se omitido, o backend usa a data atual do servidor. `amount` é opcional; se
 * enviado MENOR que o total do pedido, o backend registra pagamento PARCIAL
 * (`paid_amount`) sem marcar `is_paid: true`.
 */
export function payOrder(uuid: string, paidAt?: string, amount?: number): Promise<Order> {
  const body: { paid_at?: string; amount?: number } = {}
  if (paidAt) body.paid_at = paidAt
  if (amount !== undefined) body.amount = amount
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/orders/${uuid}/pay`, Object.keys(body).length > 0 ? body : undefined))
}

export function unpayOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/orders/${uuid}/unpay`))
}

export function payInstallment(orderUuid: string, installmentUuid: string, paidAt?: string): Promise<Order> {
  return unwrap(
    apiClient.patch<ApiSuccess<Order>>(
      `/orders/${orderUuid}/installments/${installmentUuid}/pay`,
      paidAt ? { paid_at: paidAt } : undefined,
    ),
  )
}

export function unpayInstallment(orderUuid: string, installmentUuid: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/orders/${orderUuid}/installments/${installmentUuid}/unpay`))
}

export function cancelOrder(uuid: string, cancellation_reason: string): Promise<Order> {
  return unwrap(apiClient.patch<ApiSuccess<Order>>(`/orders/${uuid}/cancel`, { cancellation_reason }))
}

/** Fila de aprovação (Delivery Fase 1) — só válido para pedido `status: 'pending_approval'`. */
export function approveOrder(uuid: string): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>(`/orders/${uuid}/approve`))
}

/** `reason` opcional — vira `cancellation_reason` no pedido rejeitado. */
export function rejectOrder(uuid: string, reason?: string): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>(`/orders/${uuid}/reject`, reason ? { reason } : undefined))
}

/**
 * Aprova a solicitação de cancelamento feita pelo cliente final no Portal
 * (roadmap A4) — só válido enquanto `status === 'cancellation_requested'`.
 * Executa o cancelamento de fato (libera reserva/gera Refund Pix se pago),
 * ação IRREVERSÍVEL — confirmar com destaque na UI antes de chamar.
 */
export function approveOrderCancellationRequest(uuid: string): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>(`/orders/${uuid}/approve-cancellation`))
}

/** Rejeita a solicitação de cancelamento — o pedido volta ao status anterior, nada é executado. */
export function rejectOrderCancellationRequest(uuid: string): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>(`/orders/${uuid}/reject-cancellation`))
}

export function createInstallment(orderUuid: string, payload: OrderInstallmentPayload): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>(`/orders/${orderUuid}/installments`, payload))
}

export function updateInstallment(orderUuid: string, installmentUuid: string, payload: OrderInstallmentPayload): Promise<Order> {
  return unwrap(apiClient.put<ApiSuccess<Order>>(`/orders/${orderUuid}/installments/${installmentUuid}`, payload))
}

export function deleteInstallment(orderUuid: string, installmentUuid: string): Promise<void> {
  return apiClient.delete(`/orders/${orderUuid}/installments/${installmentUuid}`).then(() => undefined)
}

/**
 * Substitui de uma vez todas as parcelas não pagas do pedido (criação + edição +
 * exclusão implícita das que saíram do array). Única forma de redistribuir valor
 * entre 2+ parcelas — a validação de soma do backend roda uma vez no final do
 * lote, não a cada parcela isolada (`createInstallment`/`updateInstallment`
 * batem a soma imediatamente, então não dá pra usá-las pra mover valor de uma
 * parcela pra outra sem passar por um 422 intermediário).
 */
export function reallocateInstallments(orderUuid: string, payload: OrderInstallmentsReallocatePayload): Promise<Order> {
  return unwrap(apiClient.put<ApiSuccess<Order>>(`/orders/${orderUuid}/installments`, payload))
}
