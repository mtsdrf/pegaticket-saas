import { STORAGE_KEYS } from '../constants/storage'

/**
 * Atribuição de marketing do app autenticado — captura o UTM que sobrevive
 * na URL de saída do `site/` (landing pública) até o login/futura tela de
 * self-signup do `web/`. Mesmo padrão/janela de `utils/marketingTracking.ts`
 * (loja pública por tenant), mas sem slug: aqui ainda não existe tenant.
 */
export interface AppMarketingTrackingData {
  utm_source: string | null
  utm_medium: string | null
  utm_campaign: string | null
  utm_term: string | null
  utm_content: string | null
  expires_at: string
}

const ATTRIBUTION_WINDOW_DAYS = 30

/**
 * Lê UTM da URL atual e, se houver ao menos um parâmetro reconhecido, grava
 * (substitui) a atribuição salva. Sem efeito quando a URL não traz nenhum —
 * não apaga uma atribuição capturada numa visita anterior.
 */
export function captureAppMarketingTrackingFromUrl(search: string): void {
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

  const data: AppMarketingTrackingData = {
    utm_source: utmSource,
    utm_medium: utmMedium,
    utm_campaign: utmCampaign,
    utm_term: utmTerm,
    utm_content: utmContent,
    expires_at: new Date(Date.now() + ATTRIBUTION_WINDOW_DAYS * 24 * 60 * 60 * 1000).toISOString(),
  }

  try {
    window.localStorage.setItem(STORAGE_KEYS.appMarketingTracking, JSON.stringify(data))
  } catch {
    // localStorage indisponível (modo privado/quota) — captura é best-effort, nunca bloqueia a navegação.
  }
}

/** Lê a atribuição salva, descartando (e limpando) quando já passou da janela de expiração. */
export function getAppMarketingTracking(): AppMarketingTrackingData | null {
  if (typeof window === 'undefined') return null

  try {
    const raw = window.localStorage.getItem(STORAGE_KEYS.appMarketingTracking)
    if (!raw) return null

    const data = JSON.parse(raw) as AppMarketingTrackingData
    if (!data.expires_at || Date.parse(data.expires_at) < Date.now()) {
      window.localStorage.removeItem(STORAGE_KEYS.appMarketingTracking)
      return null
    }

    return data
  } catch {
    return null
  }
}
