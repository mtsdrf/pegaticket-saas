import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { CreatePagBankSellerAccountPayload, TenantPagBankConnection } from '../types/pagBankConnect'

/** Tenant-scoped, perm `financial_receiving,read`. `null` quando o tenant nunca iniciou onboarding. */
export function getStatus(): Promise<TenantPagBankConnection | null> {
  return unwrap(apiClient.get<ApiSuccess<TenantPagBankConnection | null>>('/tenant-tools/pagbank-connect/status'))
}

/** Tenant-scoped, perm `financial_receiving,update`. `authorize_url` é redirect OAuth real — navegar com `window.location.href`, nunca SPA. */
export function getAuthorizeUrl(): Promise<{ authorize_url: string }> {
  return unwrap(apiClient.get<ApiSuccess<{ authorize_url: string }>>('/tenant-tools/pagbank-connect/authorize-url'))
}

/** Tenant-scoped, perm `financial_receiving,update`. */
export function disconnect(): Promise<null> {
  return unwrap(apiClient.post<ApiSuccess<null>>('/tenant-tools/pagbank-connect/disconnect'))
}

/** Tenant-scoped, perm `financial_receiving,update`. Caminho Account/Cadastro — cria conta SELLER nova (PF/PJ). */
export function createSellerAccount(payload: CreatePagBankSellerAccountPayload): Promise<TenantPagBankConnection> {
  return unwrap(
    apiClient.post<ApiSuccess<TenantPagBankConnection>>('/tenant-tools/pagbank-connect/create-account', payload),
  )
}
