import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { STORAGE_KEYS } from '../constants/storage'
import { ApiRequestError, type ApiError } from '../types/api'

const baseURL = import.meta.env.VITE_API_BASE_URL ?? 'https://api.maskats.com/api/v1'

/**
 * Instância axios DEDICADA ao módulo do contador (`AccountingOffice`, 3ª
 * identidade) — NUNCA reaproveitar `apiClient` (staff) nem `portalApiClient`
 * (cliente final). Cada instância lê/injeta sua própria chave de
 * `localStorage` (`STORAGE_KEYS.accountingAccessToken`).
 *
 * Comportamento de sessão expirada é igual ao do portal: o contrato do
 * contador só devolve `access_token`/`expires_in` (sem `refresh_token`, sem
 * `/accounting/refresh`), então em 401 a única ação é encerrar a sessão e
 * levar de volta pra `/contador/entrar` com um redirect "duro"
 * (`window.location.assign`) direto no interceptor.
 *
 * IMPORTANTE: só 401 (token inválido/expirado) desloga. 403
 * (`ACCOUNTING_ACCESS_DENIED` de `ResolveAccountingTenant`, quando o vínculo
 * não está mais aprovado) NÃO desloga — é erro de autorização de um recurso
 * específico e deve ser tratado pela tela (mostrar aviso, voltar à lista de
 * empresas), com a sessão do contador intacta.
 */
export const accountingApiClient = axios.create({ baseURL })

function getAccountingToken(): string | null {
  return localStorage.getItem(STORAGE_KEYS.accountingAccessToken)
}

export function storeAccountingToken(accessToken: string): void {
  localStorage.setItem(STORAGE_KEYS.accountingAccessToken, accessToken)
}

export function clearAccountingSession(): void {
  localStorage.removeItem(STORAGE_KEYS.accountingAccessToken)
}

export function hasAccountingToken(): boolean {
  return Boolean(getAccountingToken())
}

accountingApiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getAccountingToken()
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`)
  }
  return config
})

accountingApiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    const status = error.response?.status

    if (status === 401) {
      clearAccountingSession()
      if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/contador/entrar')) {
        window.location.assign('/contador/entrar?sessao=expirada')
      }
    }

    if (error.response?.data) {
      return Promise.reject(new ApiRequestError(error.response.data, status ?? 0))
    }

    return Promise.reject(error)
  },
)
