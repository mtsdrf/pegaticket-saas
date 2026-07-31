/**
 * Camada de analytics da landing — nenhum provedor (GA4/GTM/Meta Pixel etc.)
 * está confirmado/instalado no projeto ainda, então este módulo só define o
 * contrato de eventos e empurra para `window.dataLayer` quando existir (ex.:
 * GTM instalado depois via tag manager, sem alterar o código da página).
 * Sem provedor configurado, os eventos só ficam disponíveis via console em
 * dev. Não enviar dados pessoais (nome, e-mail, telefone) em nenhum evento.
 */
export type LandingAnalyticsEvent =
  | { name: 'landing_view' }
  | { name: 'hero_cta_click'; target: 'primary' | 'secondary' }
  | { name: 'segment_select'; segment: string }
  | { name: 'pricing_view' }
  | { name: 'billing_period_change'; period: 'monthly' | 'quarterly' | 'yearly' }
  | { name: 'plan_select'; plan: string; period: 'monthly' | 'quarterly' | 'yearly' }
  | { name: 'demo_click' }
  | { name: 'faq_open'; question: string }
  | { name: 'lead_start' }

declare global {
  interface Window {
    dataLayer?: unknown[]
  }
}

export function trackEvent(event: LandingAnalyticsEvent): void {
  if (typeof window !== 'undefined' && Array.isArray(window.dataLayer)) {
    window.dataLayer.push({ event: event.name, ...event })
    return
  }

  if (import.meta.env.DEV) {
    // eslint-disable-next-line no-console
    console.info('[analytics]', event)
  }
}
