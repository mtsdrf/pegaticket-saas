import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type {
  AccountingMessage,
  AccountingOfficeTenantLink,
  ApproveAccessPayload,
  CreateAccountingMessagePayload,
} from '../types/accounting'

/**
 * Lado do TENANT (staff autenticado) do módulo do contador. Usa o
 * `apiClient` de staff (JWT + tenant + `perm:accounting-access,*`), nunca o
 * `accountingApiClient`. Endpoints sob `/accounting-access-requests`.
 */

/** `GET /accounting-access-requests` — solicitações/vínculos de contadores da empresa. */
export function listAccessRequests(): Promise<AccountingOfficeTenantLink[]> {
  return unwrap(
    apiClient.get<ApiSuccess<AccountingOfficeTenantLink[]>>('/accounting-access-requests'),
  )
}

/** `POST /accounting-access-requests/{uuid}/approve` — aprova concedendo escopos. */
export function approveAccessRequest(
  uuid: string,
  payload: ApproveAccessPayload,
): Promise<AccountingOfficeTenantLink> {
  return unwrap(
    apiClient.post<ApiSuccess<AccountingOfficeTenantLink>>(
      `/accounting-access-requests/${uuid}/approve`,
      payload,
    ),
  )
}

/** `POST /accounting-access-requests/{uuid}/revoke` — revoga um vínculo aprovado. */
export function revokeAccessRequest(uuid: string): Promise<AccountingOfficeTenantLink> {
  return unwrap(
    apiClient.post<ApiSuccess<AccountingOfficeTenantLink>>(`/accounting-access-requests/${uuid}/revoke`, {}),
  )
}

/** `GET /accounting-access-requests/{uuid}/messages` — central de pendências (lado tenant). */
export function listMessages(uuid: string): Promise<AccountingMessage[]> {
  return unwrap(
    apiClient.get<ApiSuccess<AccountingMessage[]>>(`/accounting-access-requests/${uuid}/messages`),
  )
}

/** `POST /accounting-access-requests/{uuid}/messages` — responde ao contador (anexo opcional). */
export function sendMessage(
  uuid: string,
  payload: CreateAccountingMessagePayload,
): Promise<AccountingMessage> {
  const form = new FormData()
  form.append('body', payload.body)
  if (payload.due_date) form.append('due_date', payload.due_date)
  if (payload.attachment) form.append('attachment', payload.attachment)

  return unwrap(
    apiClient.post<ApiSuccess<AccountingMessage>>(`/accounting-access-requests/${uuid}/messages`, form),
  )
}
