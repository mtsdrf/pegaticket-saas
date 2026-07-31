import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { AcceptInvitePayload, AuthTokens, LoginPayload, MyTenant, PublicPlan, SelfSignupPayload } from '../types/auth'
import type { AccessProfile } from '../types/access'

export function login(payload: LoginPayload): Promise<AuthTokens> {
  return unwrap(apiClient.post<ApiSuccess<AuthTokens>>('/auth/login', payload))
}

export function register(payload: SelfSignupPayload): Promise<AuthTokens> {
  return unwrap(apiClient.post<ApiSuccess<AuthTokens>>('/auth/signup', payload))
}

export function acceptInvite(payload: AcceptInvitePayload): Promise<AuthTokens> {
  return unwrap(apiClient.post<ApiSuccess<AuthTokens>>('/auth/accept-invite', payload))
}

export function listSignupPlans(): Promise<PublicPlan[]> {
  return unwrap(apiClient.get<ApiSuccess<PublicPlan[]>>('/auth/signup/plans'))
}

export function logout(): Promise<void> {
  return apiClient.post('/auth/logout').then(() => undefined)
}

/** Sempre 200, mensagem genérica — não revela se o e-mail existe (ver backend `AuthController::forgotPassword`). */
export function forgotPassword(email: string): Promise<void> {
  return apiClient.post('/auth/forgot-password', { email }).then(() => undefined)
}

/** 422 com `code: INVALID_PASSWORD_RESET_TOKEN` se o token for inválido/expirado. */
export function resetPassword(token: string, password: string, passwordConfirmation: string): Promise<void> {
  return apiClient
    .post('/auth/reset-password', { token, password, password_confirmation: passwordConfirmation })
    .then(() => undefined)
}

export function myTenants(): Promise<MyTenant[]> {
  return unwrap(apiClient.get<ApiSuccess<MyTenant[]>>('/auth/my-tenants'))
}

export function getAccessProfile(): Promise<AccessProfile> {
  return unwrap(apiClient.get<ApiSuccess<AccessProfile>>('/auth/access-profile'))
}

export function switchTenant(tenantUuid: string): Promise<AuthTokens> {
  return unwrap(
    apiClient.post<ApiSuccess<AuthTokens>>('/auth/switch-tenant', { tenant_uuid: tenantUuid }),
  )
}
