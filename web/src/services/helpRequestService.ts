import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { CreateHelpRequestPayload, HelpRequest } from '../types/helpRequest'

/** Lista de chamados do tenant (mais recentes primeiro, limitado a 50 no backend — sem paginação client-side ainda). */
export function listHelpRequests(): Promise<HelpRequest[]> {
  return unwrap(apiClient.get<ApiSuccess<HelpRequest[]>>('/support/help-requests'))
}

/** `include_diagnostics=true` faz o backend anexar plano/auditoria/fila/versão automaticamente — nada a coletar no frontend. */
export function createHelpRequest(
  payload: CreateHelpRequestPayload,
  attachment?: File | null,
): Promise<HelpRequest> {
  const formData = new FormData()
  formData.append('subject', payload.subject)
  formData.append('description', payload.description)
  formData.append('include_diagnostics', payload.include_diagnostics ? '1' : '0')
  if (attachment) formData.append('attachment', attachment)

  return unwrap(apiClient.post<ApiSuccess<HelpRequest>>('/support/help-requests', formData))
}
