export const STORAGE_KEYS = {
  accessToken: 'maskats.access_token',
  refreshToken: 'maskats.refresh_token',
  activeTenantUuid: 'maskats.active_tenant_uuid',
  themeModePreference: 'maskats.theme_mode',
  /**
   * Token do portal do cliente final (`FinalCustomer`) — chave DIFERENTE da
   * sessão de staff (`accessToken`/`refreshToken` acima). As duas identidades
   * nunca podem se misturar: ver `services/portalApiClient.ts`.
   */
  portalAccessToken: 'maskats.portal_access_token',
  /** `sessionStorage` (não `localStorage`) — some ao fechar a aba/navegador, pra o modal de seleção de empresa reaparecer a cada novo acesso, não a cada F5. */
  tenantSelectionConfirmed: 'maskats.tenant_selection_confirmed',
  /** Roadmap A1.6 — `published_at` (ISO) da release note mais recente já vista pelo usuário neste navegador; usado só pra calcular o badge de "novidades" do sino no `AppLayout`. */
  releaseNotesLastSeenAt: 'maskats.release_notes_last_seen_at',
  /** Central de Treinamento — progresso, trilhas e respostas rápidas persistidos por usuário+empresa neste navegador. */
  trainingCenterProgress: 'maskats.training_center_progress',
  /** Identificador local estável deste navegador/dispositivo para fluxos offline controlados. */
  offlineDeviceId: 'maskats.offline_device_id',
} as const

/**
 * Carrinho da loja pública (Delivery Fase 1) — chave por SLUG, nunca
 * estática: o mesmo navegador pode visitar `/loja/loja-a` e `/loja/loja-b`
 * em sessões diferentes, e cada carrinho pertence a um tenant distinto.
 * Usar sempre este helper em vez de montar a chave inline.
 */
export function storefrontCartStorageKey(slug: string): string {
  return `maskats.storefront_cart.${slug}`
}
