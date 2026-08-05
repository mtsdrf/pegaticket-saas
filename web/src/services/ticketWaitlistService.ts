import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { JoinTicketTypeWaitlistPayload, TicketTypeWaitlistEntry } from '../types/ticketWaitlist'

/** Cadastro público (sem login) na lista de espera de um tipo de ingresso esgotado. */
export function joinTicketTypeWaitlist(slug: string, payload: JoinTicketTypeWaitlistPayload): Promise<null> {
  return unwrap(apiClient.post<ApiSuccess<null>>(`/loja/${slug}/lista-espera`, payload))
}

/** Listagem staff (tenant-scoped, perm `ticket_waitlist,read`) — demanda represada de um tipo de ingresso. */
export function getTicketTypeWaitlistEntries(ticketTypeUuid: string): Promise<TicketTypeWaitlistEntry[]> {
  return unwrap(apiClient.get<ApiSuccess<TicketTypeWaitlistEntry[]>>(`/ticket-types/${ticketTypeUuid}/lista-espera`))
}
