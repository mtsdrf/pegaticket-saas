import ShoppingCartOutlinedIcon from '@mui/icons-material/ShoppingCartOutlined'
import { Box, Button, Paper, Stack, Typography } from '@mui/material'
import { useNavigate } from 'react-router-dom'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import { PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import { formatCurrency } from '../../utils/format'
import { STOREFRONT_BOTTOM_NAV_HEIGHT } from './StorefrontBottomNav'

/** Altura visual desta barra (sem contar a safe-area) — usada pelas páginas que a renderizam pra reservar `padding-bottom` suficiente. */
export const FLOATING_CHECKOUT_BAR_HEIGHT = 73

/**
 * Barra fixa "Finalizar" do catálogo — mesmo estilo visual da barra fixa de
 * `StorefrontCartPage`, mas empilhada ACIMA da `StorefrontBottomNav` (nunca
 * `bottom: 0`, que sobreporia o nav). Sempre navega pro carrinho, nunca
 * direto pro checkout — o app sempre revisa o carrinho antes de pagar.
 */
export function FloatingCheckoutBar({ slug }: { slug: string }) {
  const navigate = useNavigate()
  const { totalQuantity, totalAmount } = useStorefrontCart()

  if (totalQuantity === 0) return null

  return (
    <Paper
      elevation={0}
      sx={{
        position: 'fixed',
        left: 0,
        right: 0,
        bottom: `calc(${STOREFRONT_BOTTOM_NAV_HEIGHT}px + env(safe-area-inset-bottom, 0px))`,
        zIndex: (theme) => theme.zIndex.appBar + 1,
        borderRadius: 0,
        borderTop: '1px solid var(--pt-border)',
        background: 'var(--pt-surface-raised-bg)',
        boxShadow: 'var(--pt-shadow-lg)',
      }}
    >
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 960, p: 1.5 }}>
        <Button
          variant="contained"
          fullWidth
          onClick={() => navigate(`/eventos/${slug}/carrinho`)}
          startIcon={<ShoppingCartOutlinedIcon fontSize="small" />}
          sx={{ minHeight: UI_SIZE.controlLarge, display: 'flex', justifyContent: 'space-between', px: 2 }}
        >
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
            <Typography sx={{ fontSize: 14, fontWeight: 700, color: 'inherit' }}>Finalizar</Typography>
          </Stack>
          <Typography sx={{ fontSize: 15, fontWeight: 700, color: 'inherit' }}>{formatCurrency(totalAmount)}</Typography>
        </Button>
      </Box>
    </Paper>
  )
}
