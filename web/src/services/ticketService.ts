import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type {
  CheckinResponse,
  CheckinSummary,
  CheckinSummaryFilters,
  CheckinTicketPayload,
  Ticket,
  TicketCheckin,
  TicketFilters,
} from '../types/ticket'

export function listTickets(filters: TicketFilters): Promise<PaginatedResult<Ticket>> {
  return listPaginated<Ticket>('/tickets', filters)
}

export function getTicket(uuid: string): Promise<Ticket> {
  return unwrap(apiClient.get<ApiSuccess<Ticket>>(`/tickets/${uuid}`))
}

/** Só registra o evento de reenvio (sem e-mail real ainda) — ver `TicketController::resend()`. */
export function resendTicket(uuid: string): Promise<Ticket> {
  return unwrap(apiClient.post<ApiSuccess<Ticket>>(`/tickets/${uuid}/resend`))
}

/**
 * Sempre responde 200 — erro de negócio (ingresso já usado, cancelado etc.)
 * é resultado (`CheckinResponse.result`), não HTTP error. Só rejeita a
 * promise em falha real (rede, 422 de payload sem nenhum identificador, 401/403).
 */
export function checkinTicket(payload: CheckinTicketPayload): Promise<CheckinResponse> {
  return unwrap(apiClient.post<ApiSuccess<CheckinResponse>>('/tickets/checkin', payload))
}

export function getTicketCheckinHistory(uuid: string): Promise<TicketCheckin[]> {
  return unwrap(apiClient.get<ApiSuccess<TicketCheckin[]>>(`/tickets/${uuid}/checkins`))
}

export function getCheckinSummary(filters: CheckinSummaryFilters): Promise<CheckinSummary> {
  return unwrap(apiClient.get<ApiSuccess<CheckinSummary>>('/tickets/checkin/summary', { params: filters }))
}
