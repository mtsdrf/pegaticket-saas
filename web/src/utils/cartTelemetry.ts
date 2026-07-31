import { publicApiClient } from '../services/publicApiClient'
import type { StorefrontCartItem } from '../types/storefront'

/** Espelha `event_type` de `StoreCartEventRequest` (backend). */
export type CartTelemetryEventType = 'cart_viewed' | 'cart_updated' | 'cart_abandoned' | 'cart_recovered'

const SESSION_TTL_MS = 24 * 60 * 60 * 1000

interface StoredCartSession {
  session_id: string
  saved_at: number
}

function sessionStorageKey(slug: string): string {
  return `pegaticket.storefront_cart_session.${slug}`
}

function generateSessionId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `cs_${Date.now()}_${Math.random().toString(36).slice(2)}`
}

/**
 * Sessão anônima de telemetria de carrinho (roadmap A3.14) — independente
 * da identidade do Portal (OTP), persistida em `localStorage` por slug com
 * TTL de 24h. Expirada, vira sessão nova: reabrir a loja depois de 1 dia
 * não conta como "carrinho recuperado".
 */
export function readOrCreateCartSession(slug: string): { sessionId: string; isRecovered: boolean } {
  const key = sessionStorageKey(slug)
  try {
    const raw = localStorage.getItem(key)
    if (raw) {
      const stored = JSON.parse(raw) as StoredCartSession
      const isExpired = Date.now() - stored.saved_at > SESSION_TTL_MS
      if (!isExpired && stored.session_id) {
        return { sessionId: stored.session_id, isRecovered: true }
      }
    }
  } catch {
    // JSON corrompido — trata como sessão nova abaixo.
  }
  const sessionId = generateSessionId()
  try {
    localStorage.setItem(key, JSON.stringify({ session_id: sessionId, saved_at: Date.now() } satisfies StoredCartSession))
  } catch {
    // localStorage indisponível (modo privado etc.) — segue só em memória.
  }
  return { sessionId, isRecovered: false }
}

function touchCartSession(slug: string, sessionId: string): void {
  try {
    localStorage.setItem(
      sessionStorageKey(slug),
      JSON.stringify({ session_id: sessionId, saved_at: Date.now() } satisfies StoredCartSession),
    )
  } catch {
    // best-effort
  }
}

function buildPayload(sessionId: string, eventType: CartTelemetryEventType, items: StorefrontCartItem[], totalAmount: number) {
  return {
    session_id: sessionId,
    event_type: eventType,
    items: items.map((item) => ({
      ticket_type_uuid: item.ticket_type_uuid,
      event_product_uuid: item.event_product_uuid,
      name: item.name,
      quantity: item.quantity,
      unit_price: item.unit_price,
    })),
    total_amount: totalAmount,
    occurred_at: new Date().toISOString(),
  }
}

/**
 * Telemetria de carrinho da loja (roadmap A3.14) — best-effort, sempre
 * fire-and-forget: nunca deve bloquear ou atrasar a navegação/compra. Sem
 * loading state, sem erro visível ao usuário; falha some em silêncio.
 *
 * `useBeacon: true` (chamado em `visibilitychange`/`pagehide`, quando a
 * página está saindo) usa `navigator.sendBeacon` — uma requisição XHR normal
 * pode ser cancelada pelo navegador antes de completar nesse momento.
 * Fora disso, ou se `sendBeacon` não estiver disponível, cai para
 * `fetch(..., { keepalive: true })`.
 */
export function sendCartEvent(
  slug: string,
  sessionId: string,
  eventType: CartTelemetryEventType,
  items: StorefrontCartItem[],
  totalAmount: number,
  options?: { useBeacon?: boolean },
): void {
  touchCartSession(slug, sessionId)

  const payload = buildPayload(sessionId, eventType, items, totalAmount)
  const baseURL = (publicApiClient.defaults.baseURL ?? '').replace(/\/$/, '')
  const url = `${baseURL}/loja/${encodeURIComponent(slug)}/eventos-carrinho`

  try {
    if (options?.useBeacon && typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
      const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' })
      if (navigator.sendBeacon(url, blob)) return
    }
  } catch {
    // cai para o fetch abaixo
  }

  try {
    if (typeof fetch === 'function') {
      void fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        keepalive: true,
      }).catch(() => undefined)
      return
    }
  } catch {
    // ambiente sem fetch (não esperado) — desiste silenciosamente
  }
}
