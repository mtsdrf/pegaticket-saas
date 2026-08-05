/**
 * `site/` é um projeto/origem separado de `web/` (domínio raiz pegaticket.com,
 * sem autenticação) — chave própria para não colidir com
 * `pegaticket.theme_mode` do app principal (ver CLAUDE.md, decisão de criar
 * `site/` como landing institucional).
 */
export const STORAGE_KEYS = {
  themeModePreference: 'pegaticket.site.theme_mode',
  marketingTracking: 'pegaticket.site.marketing_tracking',
} as const
