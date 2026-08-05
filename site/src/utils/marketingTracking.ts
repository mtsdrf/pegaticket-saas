import { STORAGE_KEYS } from '../constants/storage'

/**
 * Rastreio de marketing da landing pública (Fase 6, mesma lógica do
 * `web/src/utils/marketingTracking.ts`, adaptada pro `site/`: aqui não há
 * slug de tenant — é um único domínio institucional — por isso a chave de
 * storage é fixa, e não há `ref` de afiliado, que só existe na loja do
 * `web/`). UTM capturado da URL de entrada (tráfego pago/campanhas) e
 * persistido em `localStorage` com janela de atribuição (default técnico 30
 * dias) para sobreviver à navegação até o app autenticado, anexado como
 * query string nos CTAs de conversão.
 */
export interface SiteMarketingTrackingData {
  utm_source: string | null
  utm_medium: string | null
  utm_campaign: string | null
  utm_term: string | null
  utm_content: string | null
  expires_at: string
}

const ATTRIBUTION_WINDOW_DAYS = 30

/**
 * Lê `utm_source`/`utm_medium`/`utm_campaign`/`utm_term`/`utm_content` da
 * URL atual e, se houver ao menos um parâmetro reconhecido, grava
 * (substitui) o registro de atribuição salvo. Idempotente/sem efeito quando
 * a URL não traz nenhum desses parâmetros — não apaga uma atribuição já
 * capturada numa visita anterior.
 */
export function captureSiteMarketingTrackingFromUrl(search: string): void {
  if (typeof window === 'undefined') return

  const params = new URLSearchParams(search)
  const utmSource = params.get('utm_source')?.trim() || null
  const utmMedium = params.get('utm_medium')?.trim() || null
  const utmCampaign = params.get('utm_campaign')?.trim() || null
  const utmTerm = params.get('utm_term')?.trim() || null
  const utmContent = params.get('utm_content')?.trim() || null

  if (!utmSource && !utmMedium && !utmCampaign && !utmTerm && !utmContent) {
    return
  }

  const data: SiteMarketingTrackingData = {
    utm_source: utmSource,
    utm_medium: utmMedium,
    utm_campaign: utmCampaign,
    utm_term: utmTerm,
    utm_content: utmContent,
    expires_at: new Date(Date.now() + ATTRIBUTION_WINDOW_DAYS * 24 * 60 * 60 * 1000).toISOString(),
  }

  try {
    window.localStorage.setItem(STORAGE_KEYS.marketingTracking, JSON.stringify(data))
  } catch {
    // localStorage indisponível (modo privado/quota) — captura é best-effort, nunca bloqueia a navegação.
  }
}

/** Lê a atribuição salva, descartando (e limpando) quando já passou da janela de expiração. */
export function getSiteMarketingTracking(): SiteMarketingTrackingData | null {
  if (typeof window === 'undefined') return null

  try {
    const raw = window.localStorage.getItem(STORAGE_KEYS.marketingTracking)
    if (!raw) return null

    const data = JSON.parse(raw) as SiteMarketingTrackingData
    if (!data.expires_at || Date.parse(data.expires_at) < Date.now()) {
      window.localStorage.removeItem(STORAGE_KEYS.marketingTracking)
      return null
    }

    return data
  } catch {
    return null
  }
}

/**
 * Retorna `baseUrl` com os UTMs salvos (se houver e não tiverem expirado)
 * anexados como query string — usado pelos CTAs de conversão (`Hero`,
 * `Header`, `FinalCta`) para que a atribuição capturada na landing sobreviva
 * até o app autenticado (`web/`).
 */
export function withMarketingTracking(baseUrl: string): string {
  const tracking = getSiteMarketingTracking()
  if (!tracking) return baseUrl

  const params = new URLSearchParams()
  if (tracking.utm_source) params.set('utm_source', tracking.utm_source)
  if (tracking.utm_medium) params.set('utm_medium', tracking.utm_medium)
  if (tracking.utm_campaign) params.set('utm_campaign', tracking.utm_campaign)
  if (tracking.utm_term) params.set('utm_term', tracking.utm_term)
  if (tracking.utm_content) params.set('utm_content', tracking.utm_content)

  const query = params.toString()
  if (!query) return baseUrl

  return `${baseUrl}${baseUrl.includes('?') ? '&' : '?'}${query}`
}
