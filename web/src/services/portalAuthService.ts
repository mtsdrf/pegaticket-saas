import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type { PortalAuthTokens, PortalCustomer, RequestOtpPayload, VerifyOtpPayload } from '../types/portal'

/**
 * Sempre sucesso genérico no backend (nunca revela se o e-mail tem conta).
 * Resolve com os minutos de validade do código (`data.expires_in_minutes`)
 * pra quem chama poder iniciar o countdown visual — fallback `10` quando
 * vier ausente/`null` (contrato antigo/mock de teste sem esse campo).
 */
export function requestOtp(payload: RequestOtpPayload): Promise<number> {
  return unwrap(
    portalApiClient.post<ApiSuccess<{ expires_in_minutes: number | null } | null>>('/portal/auth/request-otp', payload),
  ).then((data) => data?.expires_in_minutes ?? 10)
}

export function verifyOtp(payload: VerifyOtpPayload): Promise<PortalAuthTokens> {
  return unwrap(portalApiClient.post<ApiSuccess<PortalAuthTokens>>('/portal/auth/verify-otp', payload))
}

export function me(): Promise<PortalCustomer> {
  return unwrap(portalApiClient.get<ApiSuccess<PortalCustomer>>('/portal/me'))
}
