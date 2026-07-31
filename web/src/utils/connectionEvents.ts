export type ConnectionEventSource = 'api' | 'browser'

export interface ConnectionEventDetail {
  source: ConnectionEventSource
  at: string
}

/**
 * Ponte mínima entre `apiClient` (interceptor axios, sem acesso a contexto
 * React) e a UI global de status de conexão — dois `CustomEvent` na `window`,
 * sem pub-sub próprio nem dependência nova. `apiClient` dispara
 * `mk:api-offline` só em falha de rede real (timeout/sem resposta), nunca em
 * 4xx/5xx normais, e `mk:api-online` a cada resposta bem-sucedida.
 */
export const API_OFFLINE_EVENT = 'mk:api-offline'
export const API_ONLINE_EVENT = 'mk:api-online'

export function notifyApiOffline(): void {
  window.dispatchEvent(
    new CustomEvent<ConnectionEventDetail>(API_OFFLINE_EVENT, {
      detail: { source: 'api', at: new Date().toISOString() },
    }),
  )
}

export function notifyApiOnline(): void {
  window.dispatchEvent(
    new CustomEvent<ConnectionEventDetail>(API_ONLINE_EVENT, {
      detail: { source: 'api', at: new Date().toISOString() },
    }),
  )
}
