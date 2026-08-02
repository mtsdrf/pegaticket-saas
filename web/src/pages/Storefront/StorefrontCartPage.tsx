import AddIcon from '@mui/icons-material/Add'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import RemoveIcon from '@mui/icons-material/Remove'
import ShoppingCartOutlinedIcon from '@mui/icons-material/ShoppingCartOutlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import { Alert, Avatar, Box, Button, IconButton, Paper, Stack, TextField, Typography } from '@mui/material'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { EmptyState } from '../../components/layout/EmptyState'
import { Logo } from '../../components/ui/Logo'
import { useCartAbandonmentTelemetry } from '../../hooks/useCartAbandonmentTelemetry'
import { useStorefrontCart } from '../../hooks/useStorefrontCart'
import { PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { formatCurrency } from '../../utils/format'

const ITEM_NOTES_MAX_LENGTH = 200

function isExclusiveSeatKind(kind: string | null | undefined): boolean {
  return kind === 'mesa' || kind === 'camarote'
}

export function StorefrontCartPage() {
  const { slug } = useParams<{ slug: string }>()
  const navigate = useNavigate()
  const location = useLocation()
  const { items, totalAmount, updateQuantity, removeItem, setItemNotes } = useStorefrontCart()
  useCartAbandonmentTelemetry(slug)

  // Redirecionado de volta pelo checkout quando a reserva temporária expirou
  // antes da finalização (ver StorefrontCheckoutPage) — os itens do carrinho
  // continuam intactos, só o hold do servidor morreu.
  const holdExpiredMessage = (location.state as { holdExpiredMessage?: string } | null)?.holdExpiredMessage ?? null

  function canIncreaseQuantity(item: (typeof items)[number]) {
    if (!item.seat_uuid) {
      return true
    }

    if (isExclusiveSeatKind(item.seat_kind)) {
      return false
    }

    return item.quantity < Math.max(1, item.seat_capacity ?? 1)
  }

  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        background:
          'var(--pt-page-background)',
        pb: items.length > 0 ? 12 : 4,
      }}
    >
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 640, px: { xs: 2, sm: 3 }, py: { xs: 3, sm: 4 } }}>
        <Button
          startIcon={<ArrowBackIcon />}
          color="inherit"
          onClick={() => navigate(`/eventos/${slug}`)}
          sx={{ ml: -1, mb: 2 }}
        >
          Continuar comprando
        </Button>

        <Typography sx={{ fontSize: { xs: 22, sm: 26 }, fontWeight: 700, mb: 3 }}>Seu carrinho</Typography>

        {holdExpiredMessage && (
          <Alert severity="warning" variant="outlined" sx={{ mb: 3 }}>
            {holdExpiredMessage}
          </Alert>
        )}

        {items.length === 0 ? (
          <Paper elevation={0} sx={{ ...ELEVATED_SURFACE_SX, p: 4 }}>
            <EmptyState
              icon={<ShoppingCartOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
              title="Seu carrinho está vazio"
              description="Volte ao catálogo e adicione produtos para continuar."
              action={
                <Button variant="contained" onClick={() => navigate(`/eventos/${slug}`)}>
                  Ver catálogo
                </Button>
              }
            />
          </Paper>
        ) : (
          <>
            <Stack spacing={1.5}>
              {items.map((item) => (
                <Paper
                  key={item.id}
                  elevation={0}
                  sx={{
                    ...ELEVATED_SURFACE_SX,
                    p: 1.5,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 1.25,
                  }}
                >
                  <Stack direction="row" sx={{ alignItems: 'center', gap: 1.5 }}>
                    <Avatar
                      src={item.image_url ?? undefined}
                      variant="rounded"
                      sx={{ ...SOFT_PANEL_SX, width: 56, height: 56, flexShrink: 0 }}
                    >
                      <StorefrontOutlinedIcon sx={{ color: 'var(--pt-muted)' }} />
                    </Avatar>

                    <Box sx={{ flex: 1, minWidth: 0 }}>
                      <Typography sx={{ fontSize: 14.5, fontWeight: 600, wordBreak: 'break-word' }}>{item.name}</Typography>
                      <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>{item.event_name}</Typography>
                      {item.session_name && (
                        <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>Sessão: {item.session_name}</Typography>
                      )}
                      {item.seat_label && (
                        <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>
                          Lugar: {item.seat_label}
                          {item.seat_sector_name ? ` • ${item.seat_sector_name}` : ''}
                        </Typography>
                      )}
                      {item.seat_uuid && isExclusiveSeatKind(item.seat_kind) && (
                        <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>
                          Reserva exclusiva para {Math.max(1, item.seat_capacity ?? item.quantity)} pessoa(s)
                        </Typography>
                      )}
                      <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>{formatCurrency(item.unit_price)} cada</Typography>

                      <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center', mt: 1 }}>
                        <IconButton
                          size="small"
                          aria-label={`Diminuir quantidade de ${item.name}`}
                          onClick={() => updateQuantity(item.id, item.quantity - 1)}
                          sx={{ minWidth: UI_SIZE.compactControl, minHeight: UI_SIZE.compactControl, ...SOFT_PANEL_SX }}
                        >
                          <RemoveIcon fontSize="small" />
                        </IconButton>
                        <Typography sx={{ fontWeight: 700, minWidth: 24, textAlign: 'center' }}>{item.quantity}</Typography>
                        <IconButton
                          size="small"
                          aria-label={`Aumentar quantidade de ${item.name}`}
                          onClick={() => updateQuantity(item.id, item.quantity + 1)}
                          sx={{ minWidth: UI_SIZE.compactControl, minHeight: UI_SIZE.compactControl, ...SOFT_PANEL_SX }}
                          disabled={!canIncreaseQuantity(item)}
                        >
                          <AddIcon fontSize="small" />
                        </IconButton>
                      </Stack>
                    </Box>

                    <Stack spacing={1} sx={{ alignItems: 'flex-end', flexShrink: 0 }}>
                      <Typography sx={{ fontSize: 14.5, fontWeight: 700 }}>
                        {formatCurrency(item.unit_price * item.quantity)}
                      </Typography>
                      <IconButton
                        size="small"
                        aria-label={`Remover ${item.name} do carrinho`}
                        onClick={() => removeItem(item.id)}
                        sx={{ minWidth: UI_SIZE.compactControl, minHeight: UI_SIZE.compactControl, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-danger)' } }}
                      >
                        <DeleteOutlineIcon fontSize="small" />
                      </IconButton>
                    </Stack>
                  </Stack>

                  <TextField
                    placeholder="Alguma observação sobre este item?"
                    value={item.notes ?? ''}
                    onChange={(event) => setItemNotes(item.id, event.target.value.slice(0, ITEM_NOTES_MAX_LENGTH))}
                    size="small"
                    fullWidth
                    multiline
                    maxRows={3}
                    helperText={`${(item.notes ?? '').length}/${ITEM_NOTES_MAX_LENGTH}`}
                    sx={{ ...SOFT_PANEL_SX }}
                    slotProps={{ htmlInput: { maxLength: ITEM_NOTES_MAX_LENGTH } }}
                  />
                </Paper>
              ))}
            </Stack>

            <Stack spacing={0.5} sx={{ alignItems: 'center', mt: 5 }}>
              <Logo variant="mark" size={20} />
              <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)' }}>Loja via PegaTicket</Typography>
            </Stack>
          </>
        )}
      </Box>

      {items.length > 0 && (
        <Paper
          elevation={0}
          sx={{
            position: 'fixed',
            left: 0,
            right: 0,
            bottom: 0,
            zIndex: (theme) => theme.zIndex.snackbar,
            borderTop: '1px solid var(--pt-border)',
            background:
              'var(--pt-surface-raised-bg)',
            boxShadow: 'var(--pt-shadow-lg)',
          }}
        >
          <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 640, p: 1.5 }}>
            <Stack direction="row" spacing={2} sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 1.25, px: 0.5 }}>
              <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>Total</Typography>
              <Typography sx={{ fontSize: 18, fontWeight: 700 }}>{formatCurrency(totalAmount)}</Typography>
            </Stack>
            <Button
              variant="contained"
              fullWidth
              onClick={() => navigate(`/eventos/${slug}/checkout`)}
              sx={{ minHeight: UI_SIZE.controlLarge }}
            >
              Continuar para o checkout
            </Button>
          </Box>
        </Paper>
      )}
    </Box>
  )
}
