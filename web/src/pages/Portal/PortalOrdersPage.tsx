import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import ChevronRightIcon from '@mui/icons-material/ChevronRight'
import ReplayOutlinedIcon from '@mui/icons-material/ReplayOutlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Divider,
  Paper,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { Link as RouterLink, useLocation, useNavigate } from 'react-router-dom'
import { getOrderItemsForReorder, listPortalOrders, requestOrderCancellation } from '../../services/portalOrderService'
import { storefrontCartStorageKey } from '../../constants/storage'
import { getApiErrorMessage } from '../../types/api'
import type { PortalOrderSummary, PortalReorderItem } from '../../types/portal'
import type { StorefrontCartItem } from '../../types/storefront'
import { canRequestOrderCancellation, deriveOrderStatus, STATUS_TONE_COLORS } from '../../utils/orderStatus'
import { formatCurrency, formatDateFromDateTimeBR } from '../../utils/format'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { PortalShell } from './PortalShell'

/**
 * Popula o carrinho da loja de destino escrevendo direto na mesma chave de
 * `localStorage` que `StorefrontCartContext` usa (`readCart`/`writeCart`) —
 * o Provider daquela loja só existe dentro de `/loja/{slug}/*`, então não dá
 * pra chamar `addItem` por contexto React a partir daqui. Substitui
 * (não mescla) o carrinho existente daquela loja, mesmo espírito de "pedir
 * de novo" recriar o pedido do zero.
 */
function replaceCartForStore(slug: string, items: PortalReorderItem[]): void {
  const cartItems: StorefrontCartItem[] = items
    .filter((item) => item.is_available && item.product_uuid && item.current_price !== null)
    .map((item) => ({
      id: globalThis.crypto?.randomUUID?.() ?? `cart-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
      product_uuid: item.product_uuid as string,
      name: item.product_name ?? '',
      unit_price: item.current_price as number,
      // Reorder só conhece o preço atual — sem promoção/atacado; o snapshot
      // vira o preço base e o carrinho recalcula ao mexer na quantidade.
      price: item.current_price as number,
      promo_price: null,
      wholesale_min_quantity: null,
      wholesale_price: null,
      image_url: null,
      quantity: item.quantity,
      configuration_label: null,
      options: [],
    }))
  localStorage.setItem(storefrontCartStorageKey(slug), JSON.stringify(cartItems))
}

function LoadingSkeleton() {
  return (
    <Stack spacing={1.5}>
      {[0, 1, 2].map((index) => (
        <Skeleton key={index} variant="rounded" height={84} sx={{ borderRadius: 'var(--mk-radius-xl)' }} />
      ))}
    </Stack>
  )
}

function EmptyState() {
  return (
    <Paper
      elevation={0}
      sx={{
        p: 4,
        textAlign: 'center',
        ...ELEVATED_SURFACE_SX,
      }}
    >
      <Inventory2OutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)', mb: 1.5 }} />
      <Typography sx={{ fontWeight: 600, fontSize: 16, mb: 0.75 }}>Você ainda não tem pedidos vinculados</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
        Abra o link de rastreio de um pedido e toque em "Ver todos os meus pedidos" para vinculá-lo à sua conta.
      </Typography>
    </Paper>
  )
}

function OrderCard({
  order,
  onReorder,
  onRequestCancellation,
}: {
  order: PortalOrderSummary
  onReorder: (order: PortalOrderSummary) => void
  onRequestCancellation: (order: PortalOrderSummary) => void
}) {
  const status = deriveOrderStatus(order)
  const colors = STATUS_TONE_COLORS[status.tone]
  const canCancel = canRequestOrderCancellation(order)

  return (
    <Paper
      elevation={0}
      sx={{
        ...ELEVATED_SURFACE_SX,
        overflow: 'hidden',
      }}
    >
      <Stack
        component={RouterLink}
        to={`/rastreio/${order.uuid}`}
        direction="row"
        sx={{
          p: 1.75,
          alignItems: 'center',
          gap: 1.25,
          textDecoration: 'none',
          color: 'inherit',
          transition: 'background-color 0.15s ease',
          '&:hover': { background: 'var(--mk-surface-soft)' },
        }}
      >
        <Box sx={{ minWidth: 0, flex: 1 }}>
          <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}>
            <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 14, fontWeight: 700, wordBreak: 'break-word' }}>{order.tenant_name}</Typography>
            <Typography sx={{ fontSize: 15, fontWeight: 700, flexShrink: 0 }}>
              {formatCurrency(order.total_amount)}
            </Typography>
          </Stack>

          <Stack direction="row" sx={{ alignItems: 'center', gap: 0.75, mt: 0.75, flexWrap: 'wrap' }}>
            <Box
              sx={{
                px: 1,
                py: 0.25,
                borderRadius: 999,
                fontSize: 11.5,
                fontWeight: 700,
                color: colors.fg,
                bgcolor: colors.bg,
              }}
            >
              {status.label}
            </Box>
            <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)' }}>{formatDateFromDateTimeBR(order.created_at)}</Typography>
          </Stack>
        </Box>

        <ChevronRightIcon sx={{ color: 'var(--mk-muted)', flexShrink: 0 }} />
      </Stack>

      <Divider />

      <Button
        component={RouterLink}
        to={`/loja/${order.tenant_slug}`}
        startIcon={<StorefrontOutlinedIcon fontSize="small" />}
        fullWidth
        sx={{ borderRadius: 0, py: 1, fontSize: 13, fontWeight: 600 }}
      >
        Ir para a loja
      </Button>

      <Divider />

      <Stack direction="row">
        <Button
          onClick={() => onReorder(order)}
          startIcon={<ReplayOutlinedIcon fontSize="small" />}
          fullWidth
          sx={{ borderRadius: 0, py: 1, fontSize: 13, fontWeight: 600 }}
        >
          Pedir de novo
        </Button>
        {canCancel && (
          <>
            <Divider orientation="vertical" flexItem />
            <Button
              onClick={() => onRequestCancellation(order)}
              startIcon={<CancelOutlinedIcon fontSize="small" />}
              color="error"
              fullWidth
              sx={{ borderRadius: 0, py: 1, fontSize: 13, fontWeight: 600 }}
            >
              Cancelar pedido
            </Button>
          </>
        )}
      </Stack>
    </Paper>
  )
}

/**
 * "Solicitar cancelamento" (roadmap A4) — motivo opcional. Ao confirmar,
 * o pedido na lista é atualizado in-place com o `status: 'cancellation_requested'`
 * devolvido pelo backend (mesmo shape de `listPortalOrders`).
 */
function RequestCancellationDialog({
  order,
  onClose,
  onRequested,
}: {
  order: PortalOrderSummary | null
  onClose: () => void
  onRequested: (updated: PortalOrderSummary) => void
}) {
  const [reason, setReason] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!order) return
    setReason('')
    setErrorMessage(null)
  }, [order])

  async function handleConfirm() {
    if (!order) return
    setIsSubmitting(true)
    setErrorMessage(null)
    try {
      const updated = await requestOrderCancellation(order.uuid, reason)
      onRequested(updated)
      onClose()
    } catch (error) {
      setErrorMessage(getApiErrorMessage(error, 'Não foi possível solicitar o cancelamento agora. Tente novamente.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open={Boolean(order)} onClose={() => !isSubmitting && onClose()} fullWidth maxWidth="xs">
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700 }}>Solicitar cancelamento</DialogTitle>
      <DialogContent>
        <Stack spacing={2}>
          <DialogContentText sx={{ fontSize: 13.5 }}>
            A loja vai analisar seu pedido de cancelamento antes de confirmá-lo. Você pode explicar o motivo, se quiser.
          </DialogContentText>
          <TextField
            label="Motivo (opcional)"
            value={reason}
            onChange={(event) => setReason(event.target.value.slice(0, 1000))}
            multiline
            minRows={2}
            fullWidth
            disabled={isSubmitting}
          />
          {errorMessage && (
            <Alert severity="error" variant="outlined" role="alert">
              {errorMessage}
            </Alert>
          )}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ flexDirection: 'column', alignItems: 'stretch', gap: 1 }}>
        <Button onClick={onClose} disabled={isSubmitting} fullWidth>
          Voltar
        </Button>
        <Button onClick={() => void handleConfirm()} variant="contained" color="error" disabled={isSubmitting} fullWidth>
          {isSubmitting ? 'Enviando…' : 'Solicitar cancelamento'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

/**
 * "Pedir de novo" (Delivery Fase 4) — mostra os itens do pedido antigo com
 * preço/disponibilidade atuais e, ao confirmar, substitui o carrinho da loja
 * de origem (`PortalOrderResource.tenant_slug`) e navega pro carrinho dela.
 */
function ReorderDialog({ order, onClose }: { order: PortalOrderSummary | null; onClose: () => void }) {
  const navigate = useNavigate()
  const [items, setItems] = useState<PortalReorderItem[] | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!order) {
      setItems(null)
      setErrorMessage(null)
      return
    }
    let cancelled = false
    setIsLoading(true)
    setErrorMessage(null)
    setItems(null)
    getOrderItemsForReorder(order.uuid)
      .then((result) => {
        if (!cancelled) setItems(result)
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setErrorMessage(getApiErrorMessage(error, 'Não foi possível carregar os itens deste pedido agora.'))
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [order])

  const availableItems = items?.filter((item) => item.is_available) ?? []
  const unavailableCount = items ? items.length - availableItems.length : 0

  const handleConfirm = () => {
    if (!order || availableItems.length === 0) return
    replaceCartForStore(order.tenant_slug, availableItems)
    navigate(`/loja/${order.tenant_slug}/carrinho`)
    onClose()
  }

  return (
    <Dialog open={Boolean(order)} onClose={onClose} fullWidth maxWidth="xs">
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700 }}>Pedir de novo</DialogTitle>
      <DialogContent>
        {isLoading && (
          <Stack spacing={1}>
            {[0, 1, 2].map((index) => (
              <Skeleton key={index} variant="rounded" height={48} sx={{ borderRadius: 'var(--mk-radius-md)' }} />
            ))}
          </Stack>
        )}

        {!isLoading && errorMessage && (
          <Alert severity="error" variant="outlined" role="alert">
            {errorMessage}
          </Alert>
        )}

        {!isLoading && !errorMessage && items && (
          <Stack spacing={1.5}>
            {unavailableCount > 0 && (
              <Alert severity="warning" variant="outlined">
                {unavailableCount === 1
                  ? '1 item não está mais disponível e foi removido desta lista.'
                  : `${unavailableCount} itens não estão mais disponíveis e foram removidos desta lista.`}
              </Alert>
            )}

            {availableItems.length === 0 ? (
              <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                Nenhum item deste pedido está disponível no momento.
              </Typography>
            ) : (
              <Stack spacing={1}>
                {availableItems.map((item, index) => (
                  <Box
                    key={item.product_uuid ?? index}
                    sx={{
                      p: 1.25,
                      ...SOFT_PANEL_SX,
                      display: 'flex',
                      justifyContent: 'space-between',
                      gap: 1,
                    }}
                  >
                    <Typography sx={{ fontSize: 13.5, wordBreak: 'break-word' }}>
                      {item.quantity} × {item.product_name}
                    </Typography>
                    <Typography sx={{ fontSize: 13.5, fontWeight: 700, flexShrink: 0 }}>
                      {item.current_price !== null ? formatCurrency(item.current_price) : '—'}
                    </Typography>
                  </Box>
                ))}
              </Stack>
            )}
          </Stack>
        )}
      </DialogContent>
      <DialogActions sx={{ flexDirection: 'column', alignItems: 'stretch', gap: 1 }}>
        <Button onClick={onClose} fullWidth>
          Fechar
        </Button>
        {!isLoading && !errorMessage && availableItems.length > 0 && (
          <Button variant="contained" onClick={handleConfirm} fullWidth>
            Ir para o carrinho
          </Button>
        )}
      </DialogActions>
    </Dialog>
  )
}

export function PortalOrdersPage() {
  const location = useLocation()
  const [orders, setOrders] = useState<PortalOrderSummary[] | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  // Repassado por `PortalLoginPage` quando o vínculo automático (`?vincular=`)
  // falha logo após o login — o login em si deu certo, então não bloqueia a
  // navegação, só avisa que esse pedido específico não entrou na lista.
  const [linkError, setLinkError] = useState<string | null>(
    (location.state as { linkError?: string } | null)?.linkError ?? null,
  )
  const [reorderOrder, setReorderOrder] = useState<PortalOrderSummary | null>(null)
  const [cancellationOrder, setCancellationOrder] = useState<PortalOrderSummary | null>(null)

  useEffect(() => {
    setIsLoading(true)
    setErrorMessage(null)

    listPortalOrders()
      .then((result) => setOrders(result))
      .catch((error) => {
        setErrorMessage(
          getApiErrorMessage(error, 'Não foi possível carregar seus pedidos agora. Verifique sua conexão e tente novamente.'),
        )
      })
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <PortalShell title="Meus pedidos" subtitle="Pedidos de todas as lojas vinculadas à sua conta, mais recentes primeiro.">
      {linkError && (
        <Alert severity="warning" variant="outlined" onClose={() => setLinkError(null)} sx={{ mb: 2 }}>
          {linkError}
        </Alert>
      )}

      {isLoading && <LoadingSkeleton />}

      {!isLoading && errorMessage && (
        <Alert severity="error" variant="outlined" role="alert">
          {errorMessage}
        </Alert>
      )}

      {!isLoading && !errorMessage && orders && orders.length === 0 && <EmptyState />}

      {!isLoading && !errorMessage && orders && orders.length > 0 && (
        <Stack spacing={1.25}>
          {orders.map((order) => (
            <OrderCard key={order.uuid} order={order} onReorder={setReorderOrder} onRequestCancellation={setCancellationOrder} />
          ))}
        </Stack>
      )}

      <ReorderDialog order={reorderOrder} onClose={() => setReorderOrder(null)} />
      <RequestCancellationDialog
        order={cancellationOrder}
        onClose={() => setCancellationOrder(null)}
        onRequested={(updated) => {
          setOrders((current) => current?.map((order) => (order.uuid === updated.uuid ? updated : order)) ?? current)
        }}
      />
    </PortalShell>
  )
}
