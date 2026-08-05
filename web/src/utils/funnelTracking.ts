import { publicApiClient } from '../services/publicApiClient'

/** Espelha `step` de `StoreFunnelEventRequest` (backend) / `StorefrontFunnelEvent::STEPS`. */
export type FunnelStep =
  | 'event_viewed'
  | 'ticket_selection_started'
  | 'hold_created'
  | 'checkout_started'
  | 'payment_confirmed'

/**
 * Telemetria de funil de conversão (roadmap A2, decisão 2026-08-05 §7.1
 * item 3) — rastreio ANÔNIMO por sessão, mesmo espírito de
 * `utils/cartTelemetry.ts`: fire-and-forget, nunca bloqueia navegação/
 * compra, sem dado pessoal (só `session_id` técnico já usado no carrinho).
 * `payment_confirmed` é uma aproximação prática: card/Pix confirmam via
 * checagem de status ou submissão bem-sucedida da cobrança, não
 * webhook assíncrono completo — decisão deliberada de escopo (mesmo
 * espírito de adiar o funil elaborado, ver roadmap 5.4).
 */
export function sendFunnelEvent(slug: string, eventSlug: string, sessionId: string, step: FunnelStep): void {
  const payload = { session_id: sessionId, step }
  const baseURL = (publicApiClient.defaults.baseURL ?? '').replace(/\/$/, '')
  const url = `${baseURL}/loja/${encodeURIComponent(slug)}/eventos/${encodeURIComponent(eventSlug)}/funnel-events`

  try {
    if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
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
    }
  } catch {
    // ambiente sem fetch (não esperado) — desiste silenciosamente
  }
}
