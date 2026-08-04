export const STORAGE_KEYS = {
  accessToken: 'pegaticket.access_token',
  refreshToken: 'pegaticket.refresh_token',
  activeTenantUuid: 'pegaticket.active_tenant_uuid',
  themeModePreference: 'pegaticket.theme_mode',
  /**
   * Token do portal do cliente final (`FinalCustomer`) — chave DIFERENTE da
   * sessão de staff (`accessToken`/`refreshToken` acima). As duas identidades
   * nunca podem se misturar: ver `services/portalApiClient.ts`.
   */
  portalAccessToken: 'pegaticket.portal_access_token',
  /** `sessionStorage` (não `localStorage`) — some ao fechar a aba/navegador, pra o modal de seleção de empresa reaparecer a cada novo acesso, não a cada F5. */
  tenantSelectionConfirmed: 'pegaticket.tenant_selection_confirmed',
  /** Roadmap A1.6 — `published_at` (ISO) da release note mais recente já vista pelo usuário neste navegador; usado só pra calcular o badge de "novidades" do sino no `AppLayout`. */
  releaseNotesLastSeenAt: 'pegaticket.release_notes_last_seen_at',
  /** Central de Treinamento — progresso, trilhas e respostas rápidas persistidos por usuário+empresa neste navegador. */
  /** Identificador local estável deste navegador/dispositivo para fluxos offline controlados. */
  offlineDeviceId: 'pegaticket.offline_device_id',
  /** Último contexto operacional escolhido na portaria neste navegador. */
  checkinContext: 'pegaticket.checkin_context',
} as const

/**
 * Carrinho da loja pública (Delivery Fase 1) — chave por SLUG, nunca
 * estática: o mesmo navegador pode visitar `/loja/loja-a` e `/loja/loja-b`
 * em sessões diferentes, e cada carrinho pertence a um tenant distinto.
 * Usar sempre este helper em vez de montar a chave inline.
 */
export function storefrontCartStorageKey(slug: string): string {
  return `pegaticket.storefront_cart.${slug}`
}

export function checkinContextStorageKey(tenantUuid: string | null | undefined): string {
  return `${STORAGE_KEYS.checkinContext}.${tenantUuid ?? 'global'}`
}

/**
 * Rastreio de marketing da loja pública (Fase 6) — código de afiliado
 * (`?ref=`) e UTM (`?utm_source=/utm_medium=/utm_campaign=`), chave por
 * SLUG (mesmo motivo do carrinho acima: cada loja é uma atribuição
 * distinta). Ver `utils/marketingTracking.ts`.
 */
export function storefrontTrackingStorageKey(slug: string): string {
  return `pegaticket.storefront_tracking.${slug}`
}
