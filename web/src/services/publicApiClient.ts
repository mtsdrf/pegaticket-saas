import axios, { type AxiosError } from 'axios'
import { ApiRequestError, type ApiError } from '../types/api'

const baseURL = import.meta.env.VITE_API_BASE_URL ?? 'https://api.pegaticket.com/api/v1'

/**
 * Instância axios 100% anônima — sem interceptor de auth/refresh/redirect
 * nenhum. Usada só para os GETs públicos do catálogo da loja
 * (`storefrontService.ts`).
 *
 * NUNCA reaproveitar `portalApiClient` aqui: seu interceptor de 401 faz
 * `window.location.assign('/portal/entrar?...')`, correto pra uma sessão do
 * portal que expirou, mas seria uma regressão de UX grave numa navegação
 * anônima de catálogo que nunca teve sessão pra "expirar" — o visitante
 * seria redirecionado pro login do portal só por navegar no catálogo.
 */
export const publicApiClient = axios.create({ baseURL })

publicApiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    if (error.response?.data) {
      return Promise.reject(new ApiRequestError(error.response.data, error.response.status ?? 0))
    }
    return Promise.reject(error)
  },
)
