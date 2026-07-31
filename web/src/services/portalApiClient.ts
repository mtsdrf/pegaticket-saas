import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { STORAGE_KEYS } from '../constants/storage'
import { ApiRequestError, type ApiError } from '../types/api'

const baseURL = import.meta.env.VITE_API_BASE_URL ?? 'https://api.pegaticket.com/api/v1'

/**
 * Instância axios DEDICADA ao portal do cliente final (`FinalCustomer`) —
 * NUNCA reaproveitar `apiClient` (staff) aqui. Duas razões:
 *
 * 1. Identidades diferentes não podem compartilhar token/header: cada
 *    instância lê/injeta sua própria chave de `localStorage`
 *    (`STORAGE_KEYS.portalAccessToken`, nunca `accessToken`/`refreshToken`
 *    do staff).
 * 2. Comportamento de sessão expirada é diferente. `apiClient` faz refresh
 *    automático em 401 e, ao falhar, só limpa a sessão — quem decide
 *    redirecionar pra `/login` é o componente React `ProtectedRoute`. O
 *    portal não tem endpoint de refresh (contrato só devolve
 *    `access_token`/`expires_in`, sem `refresh_token`), então em 401 aqui a
 *    única ação possível é encerrar a sessão do portal e levar o cliente de
 *    volta pra `/portal/entrar` — feito com um redirect "duro"
 *    (`window.location.assign`) direto no interceptor, pra não depender de
 *    qual componente React (se algum) está montado no momento em que o
 *    token expira.
 */
export const portalApiClient = axios.create({ baseURL })

function getPortalToken(): string | null {
  return localStorage.getItem(STORAGE_KEYS.portalAccessToken)
}

/**
 * Exportado para uso fora deste client (ex.: `storefrontService.listStorefrontProducts`,
 * que roda em `publicApiClient` — nunca em `portalApiClient` — mas precisa
 * anexar o Bearer do portal quando presente, ver Delivery Fase 4).
 */
export function getPortalAccessToken(): string | null {
  return getPortalToken()
}

export function storePortalToken(accessToken: string): void {
  localStorage.setItem(STORAGE_KEYS.portalAccessToken, accessToken)
}

export function clearPortalSession(): void {
  localStorage.removeItem(STORAGE_KEYS.portalAccessToken)
}

export function hasPortalToken(): boolean {
  return Boolean(getPortalToken())
}

portalApiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getPortalToken()
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`)
  }
  return config
})

portalApiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    const status = error.response?.status

    if (status === 401) {
      clearPortalSession()
      if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/portal/entrar')) {
        window.location.assign('/portal/entrar?sessao=expirada')
      }
    }

    if (error.response?.data) {
      return Promise.reject(new ApiRequestError(error.response.data, status ?? 0))
    }

    return Promise.reject(error)
  },
)
