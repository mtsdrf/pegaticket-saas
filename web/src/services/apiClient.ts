import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { STORAGE_KEYS } from '../constants/storage'
import { ApiRequestError, type ApiError, type ApiSuccess } from '../types/api'
import type { AuthTokens } from '../types/auth'
import { parseBlobErrorPayload } from '../utils/blobError'
import { notifyApiOffline, notifyApiOnline } from '../utils/connectionEvents'

const baseURL = import.meta.env.VITE_API_BASE_URL ?? 'https://api.maskats.com/api/v1'

export const apiClient = axios.create({ baseURL })

function getAccessToken(): string | null {
  return localStorage.getItem(STORAGE_KEYS.accessToken)
}

export function storeTokens(tokens: AuthTokens): void {
  localStorage.setItem(STORAGE_KEYS.accessToken, tokens.access_token)
  if (tokens.refresh_token) {
    localStorage.setItem(STORAGE_KEYS.refreshToken, tokens.refresh_token)
  }
}

export function clearSession(): void {
  localStorage.removeItem(STORAGE_KEYS.accessToken)
  localStorage.removeItem(STORAGE_KEYS.refreshToken)
  localStorage.removeItem(STORAGE_KEYS.activeTenantUuid)
  // Sem isso, um logout seguido de login na MESMA aba herdava o "já
  // confirmei empresa" da sessão anterior — o TenantSelectModal nunca
  // reabria, o novo login ficava sem tenant ativo, e sidebar/dashboard
  // liam permissões vazias até algo (ex.: abrir o menu do usuário) montar
  // o auto-select de tenant único por acaso.
  sessionStorage.removeItem(STORAGE_KEYS.tenantSelectionConfirmed)
}

/**
 * Laravel's `boolean` validation rule only accepts `true, false, 1, 0, "1", "0"`
 * (ver docs oficiais) — NÃO aceita as strings "true"/"false". axios serializa
 * um valor `boolean` de `params` literalmente como a palavra "true"/"false"
 * na query string, o que os endpoints rejeitam com 422
 * (`"The is active field must be true or false."`, achado em 2026-07-10 via
 * Playwright ao testar o filtro boolean do ag-Grid). Normaliza todo boolean
 * de `params` pra `1`/`0` aqui — um único lugar central, não em cada
 * service/filtro — antes de qualquer requisição GET.
 */
function normalizeBooleanParams(params: unknown): unknown {
  if (params === null || typeof params !== 'object') return params

  const normalized: Record<string, unknown> = {}
  for (const [key, value] of Object.entries(params as Record<string, unknown>)) {
    normalized[key] = typeof value === 'boolean' ? (value ? 1 : 0) : value
  }
  return normalized
}

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getAccessToken()
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`)
  }
  if (config.params) {
    config.params = normalizeBooleanParams(config.params)
  }
  return config
})

let refreshInFlight: Promise<string> | null = null

async function refreshAccessToken(): Promise<string> {
  const refreshToken = localStorage.getItem(STORAGE_KEYS.refreshToken)
  const accessToken = getAccessToken()
  if (!refreshToken) {
    throw new Error('No refresh token available')
  }

  const response = await axios.post<ApiSuccess<AuthTokens>>(
    `${baseURL}/auth/refresh`,
    { refresh_token: refreshToken },
    {
      headers: accessToken ? { Authorization: `Bearer ${accessToken}` } : undefined,
    },
  )

  storeTokens(response.data.data)
  return response.data.data.access_token
}

apiClient.interceptors.response.use(
  (response) => {
    notifyApiOnline()
    return response
  },
  async (error: AxiosError<ApiError>) => {
    const original = error.config as (InternalAxiosRequestConfig & { _retried?: boolean }) | undefined
    const status = error.response?.status

    // Sem `error.response` é falha de rede real (timeout, DNS, servidor fora
    // do ar) — 4xx/5xx normais sempre trazem `response`, então não disparam
    // o indicador de "sem conexão" (`ConnectionStatusBanner`).
    if (!error.response) {
      notifyApiOffline()
    }
    // Um 401 sem Authorization na requisição original é login/signup/convite
    // com credencial errada, não sessão expirada — tentar refresh nesse caso
    // não faz sentido (não existe token pra renovar) e escondia o erro real
    // (credenciais inválidas) atrás de uma falha de refresh genérica.
    const hadAuthHeader = Boolean(original?.headers?.get?.('Authorization'))
    const hasRefreshToken = Boolean(localStorage.getItem(STORAGE_KEYS.refreshToken))

    if (status === 401 && original && !original._retried && hadAuthHeader && hasRefreshToken) {
      original._retried = true

      try {
        refreshInFlight ??= refreshAccessToken().finally(() => {
          refreshInFlight = null
        })
        const newToken = await refreshInFlight

        original.headers.set('Authorization', `Bearer ${newToken}`)
        return apiClient(original)
      } catch {
        // Refresh falhou de verdade (sessão expirada) — cai pro bloco abaixo
        // pra embrulhar em `ApiRequestError` com o `status`/`message` originais,
        // em vez de rejeitar o `AxiosError` cru (perderia `code`/`message` da API
        // e vazaria o texto genérico do axios, tipo "Request failed with status code 401").
        clearSession()
      }
    }

    if (error.response?.data) {
      // Requisições com `responseType: 'blob'` (download de PDF) recebem o
      // corpo de erro também como Blob — precisa converter de volta pra JSON
      // antes de desembrulhar, senão message/code/errors ficam vazios (ver
      // utils/blobError.ts).
      const blobPayload = await parseBlobErrorPayload(error.response.data)
      return Promise.reject(new ApiRequestError(blobPayload ?? (error.response.data as ApiError), status ?? 0))
    }

    return Promise.reject(error)
  },
)

export async function unwrap<T>(promise: Promise<{ data: ApiSuccess<T> }>): Promise<T> {
  const response = await promise
  return response.data.data
}
