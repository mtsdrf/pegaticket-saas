export const ELEVATED_SURFACE_SX = {
  borderRadius: 'var(--pt-radius-xl)',
  borderColor: 'color-mix(in srgb, var(--pt-border) 88%, white)',
  background: 'var(--pt-surface-raised-bg)',
  boxShadow: 'var(--pt-shadow-sm)',
} as const

export const SOFT_PANEL_SX = {
  borderRadius: 'var(--pt-radius-lg)',
  border: '1px solid color-mix(in srgb, var(--pt-border) 88%, white)',
  background: 'var(--pt-surface-soft-bg)',
} as const
