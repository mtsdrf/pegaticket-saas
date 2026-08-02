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
import { getSaleItemsForReorder, listPortalSales, requestSaleCancellation } from '../../services/portalSaleService'
import { storefrontCartStorageKey } from '../../constants/storage'
import { getApiErrorMessage } from '../../types/api'
import type { PortalSaleSummary, PortalResaleItem } from '../../types/portal'
import type { StorefrontCartItem } from '../../types/storefront'
import { canRequestSaleCancellation, deriveSaleStatus, STATUS_TONE_COLORS } from '../../utils/saleStatus'
import { formatCurrency, formatDateFromDateTimeBR } from '../../utils/format'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { PortalShell } from './PortalShell'

/**
 * Popula o carrinho da loja de destino escrevendo direto na mesma chave de
 * `localStorage` que `StorefrontCartContext` usa (`readCart`/`writeCart`) —
 * o Provider daquela loja só existe dentro de `/loja/{slug}/*`, então não dá
 * pra chamar `addItem` por contexto React a partir daqui. Substitui
 * (não mescla) o carrinho existente daquela loja, no mesmo espírito de
 * recompra a partir do zero.
 */
function replaceCartForStore(slug: string, items: PortalResaleItem[]): void {
  const cartItems: StorefrontCartItem[] = items
    .filter((item) => item.is_available && item.ticket_type_uuid && item.current_price !== null)
    .map((item) => ({
      id: globalThis.crypto?.randomUUID?.() ?? `cart-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
      // Backend (`PortalCustomerService::getSaleItemsForReorder()`) não
      // distingue ticket_type/event_product no reorder — sempre trata como
      // ticket_type_uuid. Simplificação aceita: reorder de item que era um
      // event_product some do carrinho reconstruído (filtrado por
      // `is_available` acima, que já é `false` nesse caso hoje).
      ticket_type_uuid: item.ticket_type_uuid as string,
      name: item.ticket_type_name ?? '',
      event_name: '',
      event_slug: null,
      session_uuid: null,
      session_name: null,
      seat_uuid: null,
      seat_label: null,
      seat_sector_name: null,
      seat_kind: null,
      seat_capacity: null,
      unit_price: item.current_price as number,
      image_url: null,
      quantity: item.quantity,
    }))
  localStorage.setItem(storefrontCartStorageKey(slug), JSON.stringify(cartItems))
}

function LoadingSkeleton() {
  return (
    <Stack spacing={1.5}>
      {[0, 1, 2].map((index) => (
        <Skeleton key={index} variant="rounded" height={84} sx={{ borderRadius: 'var(--pt-radius-xl)' }} />
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
      <Inventory2OutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)', mb: 1.5 }} />
      <Typography sx={{ fontWeight: 600, fontSize: 16, mb: 0.75 }}>Você ainda não tem compras vinculadas</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
        Abra o link de rastreio de uma compra e toque em "Ver todas as minhas compras" para vinculá-la à sua conta.
      </Typography>
    </Paper>
  )
}

function SaleCard({
  sale,
  onReorder,
  onRequestCancellation,
}: {
  sale: PortalSaleSummary
  onReorder: (sale: PortalSaleSummary) => void
  onRequestCancellation: (sale: PortalSaleSummary) => void
}) {
  const status = deriveSaleStatus(sale)
  const colors = STATUS_TONE_COLORS[status.tone]
  const canCancel = canRequestSaleCancellation(sale)

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
        to={`/rastreio/${sale.uuid}`}
        direction="row"
        sx={{
          p: 1.75,
          alignItems: 'center',
          gap: 1.25,
          textDecoration: 'none',
          color: 'inherit',
          transition: 'background-color 0.15s ease',
          '&:hover': { background: 'var(--pt-surface-soft)' },
        }}
      >
        <Box sx={{ minWidth: 0, flex: 1 }}>
          <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}>
            <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 14, fontWeight: 700, wordBreak: 'break-word' }}>{sale.tenant_name}</Typography>
            <Typography sx={{ fontSize: 15, fontWeight: 700, flexShrink: 0 }}>
              {formatCurrency(sale.total_amount)}
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
            <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>{formatDateFromDateTimeBR(sale.created_at)}</Typography>
          </Stack>
        </Box>

        <ChevronRightIcon sx={{ color: 'var(--pt-muted)', flexShrink: 0 }} />
      </Stack>

      <Divider />

      <Button
        component={RouterLink}
        to={`/eventos/${sale.tenant_slug}`}
        startIcon={<StorefrontOutlinedIcon fontSize="small" />}
        fullWidth
        sx={{ borderRadius: 0, py: 1, fontSize: 13, fontWeight: 600 }}
      >
        Ir para a loja
      </Button>

      <Divider />

      <Stack direction="row">
        <Button
          onClick={() => onReorder(sale)}
          startIcon={<ReplayOutlinedIcon fontSize="small" />}
          fullWidth
          sx={{ borderRadius: 0, py: 1, fontSize: 13, fontWeight: 600 }}
        >
          Comprar novamente
        </Button>
        {canCancel && (
          <>
            <Divider orientation="vertical" flexItem />
            <Button
              onClick={() => onRequestCancellation(sale)}
              startIcon={<CancelOutlinedIcon fontSize="small" />}
              color="error"
              fullWidth
              sx={{ borderRadius: 0, py: 1, fontSize: 13, fontWeight: 600 }}
            >
              Cancelar compra
            </Button>
          </>
        )}
      </Stack>
    </Paper>
  )
}

/**
 * "Solicitar cancelamento" (roadmap A4) — motivo opcional. Ao confirmar,
 * a compra na lista é atualizada in-place com o `status: 'cancellation_requested'`
 * devolvido pelo backend (mesmo shape de `listPortalSales`).
 */
function RequestCancellationDialog({
  sale,
  onClose,
  onRequested,
}: {
  sale: PortalSaleSummary | null
  onClose: () => void
  onRequested: (updated: PortalSaleSummary) => void
}) {
  const [reason, setReason] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!sale) return
    setReason('')
    setErrorMessage(null)
  }, [sale])

  async function handleConfirm() {
    if (!sale) return
    setIsSubmitting(true)
    setErrorMessage(null)
    try {
      const updated = await requestSaleCancellation(sale.uuid, reason)
      onRequested(updated)
      onClose()
    } catch (error) {
      setErrorMessage(getApiErrorMessage(error, 'Não foi possível solicitar o cancelamento agora. Tente novamente.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open={Boolean(sale)} onClose={() => !isSubmitting && onClose()} fullWidth maxWidth="xs">
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700 }}>Solicitar cancelamento</DialogTitle>
      <DialogContent>
        <Stack spacing={2}>
          <DialogContentText sx={{ fontSize: 13.5 }}>
            A loja vai analisar sua solicitação de cancelamento antes de confirmá-la. Você pode explicar o motivo, se quiser.
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
 * "Comprar novamente" (Delivery Fase 4) — mostra os itens da compra anterior com
 * preço/disponibilidade atuais e, ao confirmar, substitui o carrinho da loja
 * de origem (`PortalSaleResource.tenant_slug`) e navega pro carrinho dela.
 */
function ReorderDialog({ sale, onClose }: { sale: PortalSaleSummary | null; onClose: () => void }) {
  const navigate = useNavigate()
  const [items, setItems] = useState<PortalResaleItem[] | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  useEffect(() => {
    if (!sale) {
      setItems(null)
      setErrorMessage(null)
      return
    }
    let cancelled = false
    setIsLoading(true)
    setErrorMessage(null)
    setItems(null)
    getSaleItemsForReorder(sale.uuid)
      .then((result) => {
        if (!cancelled) setItems(result)
      })
      .catch((error: unknown) => {
        if (!cancelled) {
          setErrorMessage(getApiErrorMessage(error, 'Não foi possível carregar os itens desta compra agora.'))
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [sale])

  const availableItems = items?.filter((item) => item.is_available) ?? []
  const unavailableCount = items ? items.length - availableItems.length : 0

  const handleConfirm = () => {
    if (!sale || availableItems.length === 0) return
    replaceCartForStore(sale.tenant_slug, availableItems)
    navigate(`/eventos/${sale.tenant_slug}/carrinho`)
    onClose()
  }

  return (
    <Dialog open={Boolean(sale)} onClose={onClose} fullWidth maxWidth="xs">
      <DialogTitle sx={{ fontSize: 17, fontWeight: 700 }}>Comprar novamente</DialogTitle>
      <DialogContent>
        {isLoading && (
          <Stack spacing={1}>
            {[0, 1, 2].map((index) => (
              <Skeleton key={index} variant="rounded" height={48} sx={{ borderRadius: 'var(--pt-radius-md)' }} />
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
              <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                Nenhum item desta compra está disponível no momento.
              </Typography>
            ) : (
              <Stack spacing={1}>
                {availableItems.map((item, index) => (
                  <Box
                    key={item.ticket_type_uuid ?? index}
                    sx={{
                      p: 1.25,
                      ...SOFT_PANEL_SX,
                      display: 'flex',
                      justifyContent: 'space-between',
                      gap: 1,
                    }}
                  >
                    <Typography sx={{ fontSize: 13.5, wordBreak: 'break-word' }}>
                      {item.quantity} × {item.ticket_type_name}
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

export function PortalSalesPage() {
  const location = useLocation()
  const [sales, setSales] = useState<PortalSaleSummary[] | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  // Repassado por `PortalLoginPage` quando o vínculo automático (`?vincular=`)
  // falha logo após o login — o login em si deu certo, então não bloqueia a
  // navegação, só avisa que essa compra específica não entrou na lista.
  const [linkError, setLinkError] = useState<string | null>(
    (location.state as { linkError?: string } | null)?.linkError ?? null,
  )
  const [reorderSale, setReorderSale] = useState<PortalSaleSummary | null>(null)
  const [cancellationSale, setCancellationSale] = useState<PortalSaleSummary | null>(null)

  useEffect(() => {
    setIsLoading(true)
    setErrorMessage(null)

    listPortalSales()
      .then((result) => setSales(result))
      .catch((error) => {
        setErrorMessage(
          getApiErrorMessage(error, 'Não foi possível carregar suas compras agora. Verifique sua conexão e tente novamente.'),
        )
      })
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <PortalShell title="Minhas compras" subtitle="Compras de todas as lojas vinculadas à sua conta, mais recentes primeiro.">
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

      {!isLoading && !errorMessage && sales && sales.length === 0 && <EmptyState />}

      {!isLoading && !errorMessage && sales && sales.length > 0 && (
        <Stack spacing={1.25}>
          {sales.map((sale) => (
            <SaleCard key={sale.uuid} sale={sale} onReorder={setReorderSale} onRequestCancellation={setCancellationSale} />
          ))}
        </Stack>
      )}

      <ReorderDialog sale={reorderSale} onClose={() => setReorderSale(null)} />
      <RequestCancellationDialog
        sale={cancellationSale}
        onClose={() => setCancellationSale(null)}
        onRequested={(updated) => {
          setSales((current) => current?.map((sale) => (sale.uuid === updated.uuid ? updated : sale)) ?? current)
        }}
      />
    </PortalShell>
  )
}
