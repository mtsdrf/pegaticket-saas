import { storefrontTrackingStorageKey } from '../constants/storage'

/**
 * Rastreio de marketing da loja pública (Fase 6) — código de afiliado
 * (`?ref=`) e UTM (`?utm_source=/utm_medium=/utm_campaign=`) capturados da
 * URL ao carregar qualquer página da loja (`StorefrontLayout`), persistidos
 * em `localStorage` por slug com janela de atribuição (default técnico 30
 * dias — não validado com o usuário; é atribuição de marketing, não sessão
 * de compra, por isso mais longa que o carrinho). Enviados automaticamente
 * no checkout (`affiliate_code`/`utm_source`/`utm_medium`/`utm_campaign`,
 * já aceitos pelo backend).
 */
export interface StorefrontTrackingData {
  affiliate_code: string | null
  utm_source: string | null
  utm_medium: string | null
  utm_campaign: string | null
  expires_at: string
}

const ATTRIBUTION_WINDOW_DAYS = 30

/**
 * Lê `?ref=/&utm_source=/&utm_medium=/&utm_campaign=` da URL atual e, se
 * houver ao menos um parâmetro reconhecido, grava (substitui) o registro de
 * atribuição salvo para este slug. Idempotente/sem efeito quando a URL não
 * traz nenhum desses parâmetros — não apaga uma atribuição já capturada
 * numa visita anterior.
 */
export function captureStorefrontTrackingFromUrl(slug: string, search: string): void {
  if (typeof window === 'undefined') return

  const params = new URLSearchParams(search)
  const affiliateCode = params.get('ref')?.trim() || null
  const utmSource = params.get('utm_source')?.trim() || null
  const utmMedium = params.get('utm_medium')?.trim() || null
  const utmCampaign = params.get('utm_campaign')?.trim() || null

  if (!affiliateCode && !utmSource && !utmMedium && !utmCampaign) {
    return
  }

  const data: StorefrontTrackingData = {
    affiliate_code: affiliateCode,
    utm_source: utmSource,
    utm_medium: utmMedium,
    utm_campaign: utmCampaign,
    expires_at: new Date(Date.now() + ATTRIBUTION_WINDOW_DAYS * 24 * 60 * 60 * 1000).toISOString(),
  }

  try {
    window.localStorage.setItem(storefrontTrackingStorageKey(slug), JSON.stringify(data))
  } catch {
    // localStorage indisponível (modo privado/quota) — captura é best-effort, nunca bloqueia a navegação.
  }
}

/** Lê a atribuição salva para este slug, descartando (e limpando) quando já passou da janela de expiração. */
export function getStorefrontTracking(slug: string): StorefrontTrackingData | null {
  if (typeof window === 'undefined') return null

  try {
    const raw = window.localStorage.getItem(storefrontTrackingStorageKey(slug))
    if (!raw) return null

    const data = JSON.parse(raw) as StorefrontTrackingData
    if (!data.expires_at || Date.parse(data.expires_at) < Date.now()) {
      window.localStorage.removeItem(storefrontTrackingStorageKey(slug))
      return null
    }

    return data
  } catch {
    return null
  }
}
