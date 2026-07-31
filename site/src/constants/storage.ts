/**
 * `site/` é um projeto/origem separado de `web/` (domínio raiz maskats.com,
 * sem autenticação) — chave própria para não colidir com
 * `maskats.theme_mode` do app principal (ver CLAUDE.md, decisão de criar
 * `site/` como landing institucional).
 */
export const STORAGE_KEYS = {
  themeModePreference: 'maskats.site.theme_mode',
} as const
