import LocationOnOutlinedIcon from '@mui/icons-material/LocationOnOutlined'
import PhoneOutlinedIcon from '@mui/icons-material/PhoneOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import SearchOffOutlinedIcon from '@mui/icons-material/SearchOffOutlined'
import { Box, Divider, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { useParams, useSearchParams } from 'react-router-dom'
import { Logo } from '../../components/ui/Logo'
import { ThemeFab } from '../../components/ThemeFab'
import * as storefrontService from '../../services/storefrontService'
import { PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { Order } from '../../types/order'
import { formatCurrency, formatDateTimeBR, formatItemQuantity } from '../../utils/format'

function PageShell({ children }: { children: React.ReactNode }) {
  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        background:
          'var(--mk-page-background)',
        px: { xs: 2, sm: 3 },
        py: { xs: 3, sm: 5 },
      }}
    >
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 480 }}>
        <Stack spacing={0.5} sx={{ alignItems: 'center', mb: 3 }}>
          <Logo variant="mark" size={34} />
          <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>Preparo do pedido</Typography>
        </Stack>
        {children}
      </Box>
      <ThemeFab />
    </Box>
  )
}

function formatAddress(order: Order): string | null {
  const e = order.client?.endereco
  if (!e) return null
  const parts = [
    [e.logradouro, e.numero].filter(Boolean).join(', '),
    e.complemento,
    e.bairro_name,
    e.cidade_name,
    e.cep,
  ].filter((part) => part && part.trim())
  return parts.length > 0 ? parts.join(' • ') : null
}

export function PrepOrderPage() {
  const { orderUuid } = useParams<{ orderUuid: string }>()
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token') ?? ''

  const [order, setOrder] = useState<Order | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [hasError, setHasError] = useState(false)

  useEffect(() => {
    if (!orderUuid) return
    let cancelled = false
    setIsLoading(true)
    setHasError(false)
    storefrontService
      .getStorefrontOrderPrep(orderUuid, token)
      .then((result) => {
        if (!cancelled) setOrder(result)
      })
      .catch(() => {
        if (!cancelled) setHasError(true)
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [orderUuid, token])

  if (isLoading) {
    return (
      <PageShell>
        <Stack spacing={2}>
          <Skeleton variant="rounded" height={64} sx={{ borderRadius: 'var(--mk-radius-lg)' }} />
          <Skeleton variant="rounded" height={180} sx={{ borderRadius: 'var(--mk-radius-lg)' }} />
        </Stack>
      </PageShell>
    )
  }

  if (hasError || !order) {
    return (
      <PageShell>
        <Paper
          elevation={0}
          sx={{ ...ELEVATED_SURFACE_SX, p: 4, textAlign: 'center' }}
        >
          <SearchOffOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)', mb: 1.5 }} />
          <Typography sx={{ fontWeight: 600, fontSize: 17, mb: 0.75 }}>Link expirado ou inválido</Typography>
          <Typography sx={{ fontSize: 14, color: 'var(--mk-muted)' }}>
            Peça à loja para gerar um novo QR code de preparo deste pedido.
          </Typography>
        </Paper>
      </PageShell>
    )
  }

  const address = formatAddress(order)

  return (
    <PageShell>
      <Paper
        elevation={0}
        sx={{ ...ELEVATED_SURFACE_SX, p: 2, mb: 2 }}
      >
        <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1.5 }}>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontSize: 16, fontWeight: 700, wordBreak: 'break-word' }}>
              {order.client?.name ?? 'Cliente'}
            </Typography>
            <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
              Pedido {order.codigo} • {formatDateTimeBR(order.created_at)}
            </Typography>
          </Box>
          <Typography sx={{ fontSize: 17, fontWeight: 700, flexShrink: 0 }}>
            {formatCurrency(order.total_amount)}
          </Typography>
        </Stack>

        {order.client?.phone_primary && (
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mt: 1.5 }}>
            <PhoneOutlinedIcon fontSize="small" sx={{ color: 'var(--mk-muted)' }} />
            <Typography sx={{ fontSize: 13.5 }}>{order.client.phone_primary}</Typography>
          </Stack>
        )}

        {address && (
          <Stack direction="row" spacing={1} sx={{ alignItems: 'flex-start', mt: 1 }}>
            <LocationOnOutlinedIcon fontSize="small" sx={{ color: 'var(--mk-muted)', mt: 0.25 }} />
            <Typography sx={{ fontSize: 13.5, wordBreak: 'break-word' }}>{address}</Typography>
          </Stack>
        )}

        {order.coupon_code && (
          <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mt: 1.5 }}>
            Cupom aplicado: <strong>{order.coupon_code}</strong>
          </Typography>
        )}
      </Paper>

      <Paper
        elevation={0}
        sx={{ ...ELEVATED_SURFACE_SX, p: 2 }}
      >
        <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center', mb: 1.5 }}>
          <ReceiptLongOutlinedIcon fontSize="small" sx={{ color: 'var(--mk-primary)' }} />
          <Typography sx={{ fontSize: 15, fontWeight: 700 }}>Itens do pedido</Typography>
        </Stack>

        <Stack spacing={1}>
          {(order.items ?? []).map((item) => (
            <Stack key={item.uuid} direction="row" sx={{ justifyContent: 'space-between', gap: 1 }}>
              <Box sx={{ minWidth: 0 }}>
                <Typography sx={{ fontSize: 13.5, fontWeight: 600, wordBreak: 'break-word' }}>
                  {formatItemQuantity(item.quantity, item.product.unit)} × {item.product.name}
                </Typography>
                <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)' }}>
                  {formatCurrency(item.unit_price)} cada
                </Typography>
              </Box>
              <Typography sx={{ fontSize: 13.5, fontWeight: 600, flexShrink: 0 }}>
                {formatCurrency(item.line_total)}
              </Typography>
            </Stack>
          ))}
        </Stack>

        <Divider sx={{ my: 1.5 }} />
        <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
          <Typography sx={{ fontWeight: 700 }}>Total</Typography>
          <Typography sx={{ fontWeight: 700, fontSize: 16 }}>{formatCurrency(order.total_amount)}</Typography>
        </Stack>
      </Paper>
    </PageShell>
  )
}
