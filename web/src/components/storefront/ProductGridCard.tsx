import AddIcon from '@mui/icons-material/Add'
import FavoriteIcon from '@mui/icons-material/Favorite'
import FavoriteBorderOutlinedIcon from '@mui/icons-material/FavoriteBorderOutlined'
import RemoveIcon from '@mui/icons-material/Remove'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import { Box, Button, Chip, IconButton, Paper, Stack, Typography } from '@mui/material'
import { ProductCardBadges } from './ProductCardBadges'
import { useProductCardState } from '../../hooks/useProductCardState'
import { CLAMP_TEXT_2_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import type { StorefrontProduct, StorefrontProductBadge } from '../../types/storefront'
import { formatCurrency } from '../../utils/format'

interface ProductGridCardProps {
  product: StorefrontProduct
  onToggleFavorite: (product: StorefrontProduct) => void
  onConfigureOptions: (product: StorefrontProduct) => void
  /** Selos a omitir (ex.: 'best_selling' dentro do próprio rail "Mais vendidos", onde o selo é redundante). */
  excludeBadges?: StorefrontProductBadge[]
}

/**
 * Item de produto da loja pública — card com foto grande (1:1), nome e preço
 * abaixo. Layout alternativo (`tenant_settings.catalog_layout === 'grid'`);
 * o padrão é `ProductListItem` (lista com imagem à direita). Os dois
 * compartilham estado/lógica via `useProductCardState`, só a estrutura
 * visual difere.
 */
export function ProductGridCard({ product, onToggleFavorite, onConfigureOptions, excludeBadges }: ProductGridCardProps) {
  const { quantity, hasOptions, discountLabel, showWholesaleNote, addItem, updateQuantity } =
    useProductCardState(product)

  const visibleBadges = excludeBadges?.length
    ? (product.badges ?? []).filter((badge) => !excludeBadges.includes(badge))
    : product.badges

  return (
    <Paper
      elevation={0}
      sx={{
        ...ELEVATED_SURFACE_SX,
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
        width: '100%',
        height: '100%',
        opacity: product.is_available ? 1 : 0.6,
        transition: 'box-shadow 0.15s ease',
        '&:hover': { boxShadow: 'var(--pt-shadow-md)' },
      }}
    >
      <Box
        sx={{
          position: 'relative',
          width: '100%',
          aspectRatio: '1 / 1',
          ...SOFT_PANEL_SX,
          borderRadius: 0,
          borderLeft: 'none',
          borderRight: 'none',
          borderTop: 'none',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          overflow: 'hidden',
          flexShrink: 0,
        }}
      >
        {product.image_url ? (
          <Box
            component="img"
            src={product.image_url}
            alt={product.name}
            sx={{ width: '100%', height: '100%', objectFit: 'cover' }}
          />
        ) : (
          <StorefrontOutlinedIcon sx={{ fontSize: 44, color: 'var(--pt-muted)' }} />
        )}

        <ProductCardBadges badges={visibleBadges} variant="overlay" />

        <IconButton
          onClick={(event) => {
            event.stopPropagation()
            onToggleFavorite(product)
          }}
          aria-label={product.is_favorited ? `Remover ${product.name} dos favoritos` : `Favoritar ${product.name}`}
          size="small"
          sx={{
            position: 'absolute',
            top: 6,
            right: 6,
            width: 40,
            height: 40,
            bgcolor: 'color-mix(in srgb, var(--pt-surface) 85%, transparent)',
            '&:hover': { background: 'var(--pt-surface)' },
          }}
        >
          {product.is_favorited ? (
            <FavoriteIcon sx={{ fontSize: 17, color: 'var(--pt-primary)' }} />
          ) : (
            <FavoriteBorderOutlinedIcon sx={{ fontSize: 17, color: 'var(--pt-muted)' }} />
          )}
        </IconButton>
      </Box>

      <Box sx={{ p: 1.5, display: 'flex', flexDirection: 'column', flex: 1 }}>
        <Typography
          sx={{
            fontSize: 14,
            fontWeight: 700,
            lineHeight: 1.3,
            wordBreak: 'break-word',
            minHeight: '2.6em',
            ...CLAMP_TEXT_2_SX,
          }}
        >
          {product.name}
        </Typography>

        <Box sx={{ flex: 1 }} />

        <Stack spacing={0.5} sx={{ mt: 1, alignItems: 'center', textAlign: 'center' }}>
          {product.promo_price !== null ? (
            <Stack direction="row" spacing={0.75} sx={{ alignItems: 'baseline', justifyContent: 'center', flexWrap: 'wrap' }}>
              <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)', textDecoration: 'line-through' }}>
                {formatCurrency(product.price)}
              </Typography>
              <Typography sx={{ fontSize: 16, fontWeight: 700, color: 'var(--pt-primary)' }}>
                {formatCurrency(product.promo_price)}
              </Typography>
              {discountLabel && (
                <Chip
                  label={discountLabel}
                  size="small"
                  sx={{ height: 18, fontSize: 10, fontWeight: 700, color: '#FFFFFF', bgcolor: 'var(--pt-danger)' }}
                />
              )}
            </Stack>
          ) : (
            <Typography sx={{ fontSize: 16, fontWeight: 700, color: 'var(--pt-primary)' }}>
              {formatCurrency(product.price)}
            </Typography>
          )}

          {showWholesaleNote && (
            <Typography sx={{ fontSize: 11.5, fontWeight: 600, color: 'var(--pt-success, #1b7a3d)', lineHeight: 1.25 }}>
              A partir de {String(product.wholesale_min_quantity).replace('.', ',')} un.:{' '}
              {formatCurrency(product.wholesale_price!)} cada
            </Typography>
          )}

          {!product.is_available ? (
            <Typography sx={{ fontSize: 12.5, fontWeight: 600, color: 'var(--pt-muted)' }}>
              Indisponível no momento
            </Typography>
          ) : hasOptions ? (
            <Stack spacing={0.5} sx={{ alignItems: 'center' }}>
              <Button
                variant="outlined"
                size="small"
                startIcon={<AddIcon />}
                onClick={() => onConfigureOptions(product)}
                sx={{ minHeight: 44 }}
              >
                Escolher opcionais
              </Button>
              {quantity > 0 && (
                <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)' }}>
                  {quantity} item(ns) deste produto no carrinho
                </Typography>
              )}
            </Stack>
          ) : quantity === 0 ? (
            <Button
              variant="outlined"
              size="small"
              startIcon={<AddIcon />}
              onClick={() => addItem(product)}
              sx={{ minHeight: 44 }}
            >
              Adicionar
            </Button>
          ) : (
            <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center', justifyContent: 'center' }}>
              <IconButton
                size="small"
                aria-label={`Diminuir quantidade de ${product.name}`}
                onClick={() => updateQuantity(product.uuid, quantity - 1)}
                sx={{ minWidth: 44, minHeight: 44, ...SOFT_PANEL_SX }}
              >
                <RemoveIcon fontSize="small" />
              </IconButton>
              <Typography sx={{ fontWeight: 700, minWidth: 24, textAlign: 'center' }}>{quantity}</Typography>
              <IconButton
                size="small"
                aria-label={`Aumentar quantidade de ${product.name}`}
                onClick={() => updateQuantity(product.uuid, quantity + 1)}
                sx={{ minWidth: 44, minHeight: 44, ...SOFT_PANEL_SX }}
              >
                <AddIcon fontSize="small" />
              </IconButton>
            </Stack>
          )}
        </Stack>
      </Box>
    </Paper>
  )
}
