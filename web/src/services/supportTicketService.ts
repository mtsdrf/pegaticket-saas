import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { CreateSupportTicketPayload, SupportTicket } from '../types/support'

/** Lista de chamados do tenant (mais recentes primeiro, limitado a 50 no backend — sem paginação client-side ainda). */
export function listSupportTickets(): Promise<SupportTicket[]> {
  return unwrap(apiClient.get<ApiSuccess<SupportTicket[]>>('/support/tickets'))
}

/** `include_diagnostics=true` faz o backend anexar plano/auditoria/fila/versão automaticamente — nada a coletar no frontend. */
export function createSupportTicket(
  payload: CreateSupportTicketPayload,
  attachment?: File | null,
): Promise<SupportTicket> {
  const formData = new FormData()
  formData.append('subject', payload.subject)
  formData.append('description', payload.description)
  formData.append('include_diagnostics', payload.include_diagnostics ? '1' : '0')
  if (attachment) formData.append('attachment', attachment)

  return unwrap(apiClient.post<ApiSuccess<SupportTicket>>('/support/tickets', formData))
}
