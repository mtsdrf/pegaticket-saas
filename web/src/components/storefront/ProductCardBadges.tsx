import { Chip, Stack } from '@mui/material'
import type { StorefrontProductBadge } from '../../types/storefront'

const BADGE_META: Record<StorefrontProductBadge, { label: string; color: string; bg: string }> = {
  new: { label: 'Novo', color: 'var(--mk-info)', bg: 'color-mix(in srgb, var(--mk-info) 14%, transparent)' },
  best_selling: {
    label: 'Mais vendido',
    color: 'var(--mk-accent)',
    bg: 'color-mix(in srgb, var(--mk-accent) 16%, transparent)',
  },
  low_stock: {
    label: 'Últimas unidades',
    color: 'var(--mk-warning)',
    bg: 'color-mix(in srgb, var(--mk-warning) 16%, transparent)',
  },
}

/**
 * Selos de produto ('new'|'best_selling'|'low_stock') — compartilhado entre
 * `ProductListItem` e `ProductGridCard`. `variant="overlay"` (usada pelo
 * `ProductGridCard`, sobre a foto) usa fundo semitransparente (`color-mix`
 * 78% opaco) + blur leve + sombra — visível sobre qualquer foto sem tapar
 * totalmente a imagem; `variant="inline"` (usada pelo `ProductListItem`,
 * acima do nome) mantém o fundo translúcido original.
 */
export function ProductCardBadges({
  badges,
  variant = 'inline',
}: {
  badges: StorefrontProductBadge[]
  variant?: 'inline' | 'overlay'
}) {
  if (!badges || badges.length === 0) return null
  return (
    <Stack
      direction="row"
      spacing={0.5}
      sx={
        variant === 'overlay'
          ? { position: 'absolute', top: 8, left: 8, flexWrap: 'wrap', rowGap: 0.5, maxWidth: 'calc(100% - 16px)' }
          : { flexWrap: 'wrap', rowGap: 0.5, mb: 0.25 }
      }
    >
      {badges.map((badge) => (
        <Chip
          key={badge}
          label={BADGE_META[badge].label}
          size="small"
          sx={
            variant === 'overlay'
              ? {
                  height: 22,
                  fontSize: 10.5,
                  fontWeight: 700,
                  color: '#FFFFFF',
                  bgcolor: `color-mix(in srgb, ${BADGE_META[badge].color} 78%, transparent)`,
                  backdropFilter: 'blur(2px)',
                  boxShadow: 'var(--mk-shadow-sm)',
                }
              : {
                  height: 20,
                  fontSize: 10.5,
                  fontWeight: 700,
                  color: BADGE_META[badge].color,
                  bgcolor: BADGE_META[badge].bg,
                }
          }
        />
      ))}
    </Stack>
  )
}
