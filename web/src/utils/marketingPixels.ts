/**
 * Injeção condicional de pixels de marketing na loja pública (Fase 6) —
 * opt-in por tenant (`tenant_settings.meta_pixel_id`/`google_analytics_id`,
 * ver `StorefrontTenantResource`). Tenant sem nenhum dos dois configurados
 * não carrega nenhum script extra. Provedores suportados nesta rodada: Meta
 * Pixel e Google Analytics 4 — decisão técnica não validada com o usuário
 * (poderia crescer para TikTok Pixel/GTM depois).
 */

declare global {
  interface Window {
    fbq?: (...args: unknown[]) => void
    _fbq?: unknown
    dataLayer?: unknown[]
    gtag?: (...args: unknown[]) => void
  }
}

const META_PIXEL_SCRIPT_ID = 'pt-meta-pixel-script'
const GA_SCRIPT_ID = 'pt-ga-script'

/** Injeta o Meta Pixel (base code) uma única vez por `pixelId`; reinjeções com o mesmo id são no-op. */
export function injectMetaPixel(pixelId: string): void {
  if (typeof window === 'undefined' || typeof document === 'undefined') return

  const existing = document.getElementById(META_PIXEL_SCRIPT_ID)
  if (existing?.getAttribute('data-pixel-id') === pixelId) return
  existing?.remove()

  if (!window.fbq) {
    const fbq = function fbq(...args: unknown[]) {
      const queue = (fbq as unknown as { queue: unknown[][] }).queue
      queue.push(args)
    } as unknown as Window['fbq'] & { queue: unknown[][]; loaded?: boolean; version?: string }
    fbq!.queue = []
    fbq!.loaded = true
    fbq!.version = '2.0'
    window.fbq = fbq
    window._fbq = fbq
  }

  window.fbq?.('init', pixelId)
  window.fbq?.('track', 'PageView')

  const script = document.createElement('script')
  script.id = META_PIXEL_SCRIPT_ID
  script.async = true
  script.src = 'https://connect.facebook.net/en_US/fbevents.js'
  script.setAttribute('data-pixel-id', pixelId)
  document.head.appendChild(script)
}

/** Injeta o Google Analytics 4 (gtag.js) uma única vez por `measurementId`; reinjeções com o mesmo id são no-op. */
export function injectGoogleAnalytics(measurementId: string): void {
  if (typeof window === 'undefined' || typeof document === 'undefined') return

  const existing = document.getElementById(GA_SCRIPT_ID)
  if (existing?.getAttribute('data-ga-id') === measurementId) return
  existing?.remove()

  window.dataLayer = window.dataLayer || []
  window.gtag =
    window.gtag ||
    function gtag(...args: unknown[]) {
      window.dataLayer?.push(args)
    }
  window.gtag('js', new Date())
  window.gtag('config', measurementId)

  const script = document.createElement('script')
  script.id = GA_SCRIPT_ID
  script.async = true
  script.src = `https://www.googletagmanager.com/gtag/js?id=${measurementId}`
  script.setAttribute('data-ga-id', measurementId)
  document.head.appendChild(script)
}

/** Remove os scripts injetados por este módulo (usado ao sair da loja pública). */
export function removeInjectedMarketingPixels(): void {
  if (typeof document === 'undefined') return

  document.getElementById(META_PIXEL_SCRIPT_ID)?.remove()
  document.getElementById(GA_SCRIPT_ID)?.remove()
}
