import type { SxProps, Theme } from '@mui/material'

export const UI_RADIUS = {
  sm: '8px',
  md: '12px',
  lg: '15px',
  xl: '15px',
  pill: '999px',
} as const

export const UI_SIZE = {
  control: 44,
  controlLarge: 48,
  compactControl: 40,
  iconButton: 44,
  pageAction: 48,
  navItem: 44,
  navGroup: 46,
  quickActionCard: 120,
  metricCard: 148,
  emptyStateIcon: 72,
} as const

/**
 * Altura visual da barra fixa de total+checkout no fim de `StorefrontCartPage`
 * (sem contar a safe-area) — mesmo papel de `FLOATING_CHECKOUT_BAR_HEIGHT`
 * (`components/storefront/FloatingCheckoutBar.tsx`), mas maior por ter uma
 * linha extra (Total) acima do botão: padding (`p: 1.5` = 12px topo/base) +
 * linha "Total" (~20px + 10px de margem) + botão (`UI_SIZE.controlLarge`,
 * 48px). Vive aqui (não dentro de `StorefrontCartPage.tsx`) para que
 * `StorefrontLayout` possa importar só a constante sem puxar a página inteira
 * (que é lazy-loaded via rota) para o bundle principal.
 */
export const CART_CHECKOUT_BAR_HEIGHT = 110

export const PAGE_CONTAINER_SX: SxProps<Theme> = {
  width: '100%',
  maxWidth: 1600,
  mx: 'auto',
}

export const CONTENT_CONTAINER_SX: SxProps<Theme> = {
  width: '100%',
  minWidth: 0,
  display: 'flex',
  flexDirection: 'column',
  gap: 2.5,
}

export const SECTION_CARD_PADDING_SX: SxProps<Theme> = {
  p: { xs: 2, sm: 2.5, lg: 3 },
}

export const FORM_GRID_2_SX: SxProps<Theme> = {
  display: 'grid',
  gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(2, minmax(0, 1fr))' },
  gap: 2,
}

export const FORM_GRID_3_SX: SxProps<Theme> = {
  display: 'grid',
  gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(3, minmax(0, 1fr))' },
  gap: 2,
}

export const CARD_EQUAL_HEIGHT_SX: SxProps<Theme> = {
  height: '100%',
  display: 'flex',
  flexDirection: 'column',
}

export const CLAMP_TEXT_2_SX: SxProps<Theme> = {
  display: '-webkit-box',
  WebkitLineClamp: 2,
  WebkitBoxOrient: 'vertical',
  overflow: 'hidden',
}

export const CLAMP_TEXT_3_SX: SxProps<Theme> = {
  display: '-webkit-box',
  WebkitLineClamp: 3,
  WebkitBoxOrient: 'vertical',
  overflow: 'hidden',
}
