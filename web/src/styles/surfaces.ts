export const ELEVATED_SURFACE_SX = {
  borderRadius: 'var(--mk-radius-xl)',
  borderColor: 'color-mix(in srgb, var(--mk-border) 88%, white)',
  background: 'var(--mk-surface-raised-bg)',
  boxShadow: 'var(--mk-shadow-sm)',
} as const

export const SOFT_PANEL_SX = {
  borderRadius: 'var(--mk-radius-lg)',
  border: '1px solid color-mix(in srgb, var(--mk-border) 88%, white)',
  background: 'var(--mk-surface-soft-bg)',
} as const
