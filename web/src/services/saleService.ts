import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type {
  Sale,
  SaleFilters,
  SaleInstallmentPayload,
  SaleInstallmentsReallocatePayload,
  SalePayload,
  SaleUpdateItemsPayload,
} from '../types/sale'

export function listSales(filters: SaleFilters): Promise<PaginatedResult<Sale>> {
  return listPaginated<Sale>('/orders', filters)
}

export function getSale(uuid: string): Promise<Sale> {
  return unwrap(apiClient.get<ApiSuccess<Sale>>(`/sales/${uuid}`))
}

export function createSale(payload: SalePayload): Promise<Sale> {
  return unwrap(apiClient.post<ApiSuccess<Sale>>('/orders', payload))
}

/**
 * Edita itens/cabeçalho de um pedido já criado. `items` é o array COMPLETO
 * (item com `uuid` atualiza, sem `uuid` cria, item existente ausente do
 * array é removido). Só permitido enquanto o pedido não estiver
 * entregue/pago/cancelado — 422 `INVALID_ORDER_STATE` fora dessa janela.
 */
export function updateSaleItems(uuid: string, payload: SaleUpdateItemsPayload): Promise<Sale> {
  return unwrap(apiClient.put<ApiSuccess<Sale>>(`/sales/${uuid}/items`, payload))
}

export function deliverSale(uuid: string): Promise<Sale> {
  return unwrap(apiClient.patch<ApiSuccess<Sale>>(`/sales/${uuid}/deliver`))
}

export function dispatchSale(uuid: string): Promise<Sale> {
  return unwrap(apiClient.patch<ApiSuccess<Sale>>(`/sales/${uuid}/dispatch`))
}

export function undispatchSale(uuid: string): Promise<Sale> {
  return unwrap(apiClient.patch<ApiSuccess<Sale>>(`/sales/${uuid}/undispatch`))
}

export function undeliverSale(uuid: string): Promise<Sale> {
  return unwrap(apiClient.patch<ApiSuccess<Sale>>(`/sales/${uuid}/undeliver`))
}

/**
 * `paidAt` (YYYY-MM-DD) é opcional e pode ser uma data futura (pagamento agendado) —
 * se omitido, o backend usa a data atual do servidor. `amount` é opcional; se
 * enviado MENOR que o total do pedido, o backend registra pagamento PARCIAL
 * (`paid_amount`) sem marcar `is_paid: true`.
 */
export function paySale(uuid: string, paidAt?: string, amount?: number): Promise<Sale> {
  const body: { paid_at?: string; amount?: number } = {}
  if (paidAt) body.paid_at = paidAt
  if (amount !== undefined) body.amount = amount
  return unwrap(apiClient.patch<ApiSuccess<Sale>>(`/sales/${uuid}/pay`, Object.keys(body).length > 0 ? body : undefined))
}

export function unpaySale(uuid: string): Promise<Sale> {
  return unwrap(apiClient.patch<ApiSuccess<Sale>>(`/sales/${uuid}/unpay`))
}

export function payInstallment(orderUuid: string, installmentUuid: string, paidAt?: string): Promise<Sale> {
  return unwrap(
    apiClient.patch<ApiSuccess<Sale>>(
      `/sales/${orderUuid}/installments/${installmentUuid}/pay`,
      paidAt ? { paid_at: paidAt } : undefined,
    ),
  )
}

export function unpayInstallment(orderUuid: string, installmentUuid: string): Promise<Sale> {
  return unwrap(apiClient.patch<ApiSuccess<Sale>>(`/sales/${orderUuid}/installments/${installmentUuid}/unpay`))
}

export function cancelSale(uuid: string, cancellation_reason: string): Promise<Sale> {
  return unwrap(apiClient.patch<ApiSuccess<Sale>>(`/sales/${uuid}/cancel`, { cancellation_reason }))
}

/** Fila de aprovação (Delivery Fase 1) — só válido para pedido `status: 'pending_approval'`. */
export function approveSale(uuid: string): Promise<Sale> {
  return unwrap(apiClient.post<ApiSuccess<Sale>>(`/sales/${uuid}/approve`))
}

/** `reason` opcional — vira `cancellation_reason` no pedido rejeitado. */
export function rejectSale(uuid: string, reason?: string): Promise<Sale> {
  return unwrap(apiClient.post<ApiSuccess<Sale>>(`/sales/${uuid}/reject`, reason ? { reason } : undefined))
}

/**
 * Aprova a solicitação de cancelamento feita pelo cliente final no Portal
 * (roadmap A4) — só válido enquanto `status === 'cancellation_requested'`.
 * Executa o cancelamento de fato (libera reserva/gera Refund Pix se pago),
 * ação IRREVERSÍVEL — confirmar com destaque na UI antes de chamar.
 */
export function approveSaleCancellationRequest(uuid: string): Promise<Sale> {
  return unwrap(apiClient.post<ApiSuccess<Sale>>(`/sales/${uuid}/approve-cancellation`))
}

/** Rejeita a solicitação de cancelamento — o pedido volta ao status anterior, nada é executado. */
export function rejectSaleCancellationRequest(uuid: string): Promise<Sale> {
  return unwrap(apiClient.post<ApiSuccess<Sale>>(`/sales/${uuid}/reject-cancellation`))
}

export function createInstallment(orderUuid: string, payload: SaleInstallmentPayload): Promise<Sale> {
  return unwrap(apiClient.post<ApiSuccess<Sale>>(`/sales/${orderUuid}/installments`, payload))
}

export function updateInstallment(orderUuid: string, installmentUuid: string, payload: SaleInstallmentPayload): Promise<Sale> {
  return unwrap(apiClient.put<ApiSuccess<Sale>>(`/sales/${orderUuid}/installments/${installmentUuid}`, payload))
}

export function deleteInstallment(orderUuid: string, installmentUuid: string): Promise<void> {
  return apiClient.delete(`/sales/${orderUuid}/installments/${installmentUuid}`).then(() => undefined)
}

/**
 * Substitui de uma vez todas as parcelas não pagas do pedido (criação + edição +
 * exclusão implícita das que saíram do array). Única forma de redistribuir valor
 * entre 2+ parcelas — a validação de soma do backend roda uma vez no final do
 * lote, não a cada parcela isolada (`createInstallment`/`updateInstallment`
 * batem a soma imediatamente, então não dá pra usá-las pra mover valor de uma
 * parcela pra outra sem passar por um 422 intermediário).
 */
export function reallocateInstallments(orderUuid: string, payload: SaleInstallmentsReallocatePayload): Promise<Sale> {
  return unwrap(apiClient.put<ApiSuccess<Sale>>(`/sales/${orderUuid}/installments`, payload))
}
