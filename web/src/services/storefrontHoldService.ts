import { unwrap } from './apiClient'
import { getPortalAccessToken, portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type {
  StorefrontAvailabilityResult,
  StorefrontCreateHoldPayload,
  StorefrontInventoryHold,
  StorefrontQueueStatus,
} from '../types/storefront'

const baseURL = import.meta.env.VITE_API_BASE_URL ?? 'https://api.pegaticket.com/api/v1'

export function getStorefrontAvailability(
  slug: string,
  eventSlug: string,
  sessionUuid?: string,
): Promise<StorefrontAvailabilityResult> {
  return unwrap(
    portalApiClient.get<ApiSuccess<StorefrontAvailabilityResult>>(`/loja/${slug}/eventos/${eventSlug}/disponibilidade`, {
      params: sessionUuid ? { session_uuid: sessionUuid } : undefined,
    }),
  )
}

/** Fila virtual para alta demanda (roadmap Fase 7) — polling da tela de detalhe do evento enquanto `high_demand_mode=true` e `status !== 'admitted'`. */
export function getQueueStatus(slug: string, eventSlug: string, sessionToken: string): Promise<StorefrontQueueStatus> {
  return unwrap(
    portalApiClient.get<ApiSuccess<StorefrontQueueStatus>>(`/loja/${slug}/eventos/${eventSlug}/fila`, {
      params: { session_token: sessionToken },
    }),
  )
}

export function createHold(
  slug: string,
  eventSlug: string,
  payload: StorefrontCreateHoldPayload,
): Promise<StorefrontInventoryHold> {
  return unwrap(portalApiClient.post<ApiSuccess<StorefrontInventoryHold>>(`/loja/${slug}/eventos/${eventSlug}/holds`, payload))
}

export function getHold(slug: string, holdUuid: string, sessionToken?: string): Promise<StorefrontInventoryHold> {
  return unwrap(
    portalApiClient.get<ApiSuccess<StorefrontInventoryHold>>(`/loja/${slug}/holds/${holdUuid}`, {
      params: sessionToken ? { session_token: sessionToken } : undefined,
    }),
  )
}

export function renewHold(slug: string, holdUuid: string, sessionToken?: string): Promise<StorefrontInventoryHold> {
  return unwrap(
    portalApiClient.post<ApiSuccess<StorefrontInventoryHold>>(`/loja/${slug}/holds/${holdUuid}/renovar`, {
      session_token: sessionToken,
    }),
  )
}

export function releaseHold(slug: string, holdUuid: string, sessionToken?: string): Promise<StorefrontInventoryHold> {
  return unwrap(
    portalApiClient.delete<ApiSuccess<StorefrontInventoryHold>>(`/loja/${slug}/holds/${holdUuid}`, {
      data: { session_token: sessionToken },
    }),
  )
}

/**
 * `pagehide`/unmount: tenta liberar a reserva sem depender do ciclo React.
 * Erro é intencionalmente ignorado — é limpeza best-effort.
 */
export function releaseHoldBestEffort(slug: string, holdUuid: string, sessionToken?: string): void {
  const token = getPortalAccessToken()

  void fetch(`${baseURL}/loja/${slug}/holds/${holdUuid}`, {
    method: 'DELETE',
    keepalive: true,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ session_token: sessionToken }),
  }).catch(() => undefined)
}
