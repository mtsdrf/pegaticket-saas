import QrCode2OutlinedIcon from '@mui/icons-material/QrCode2Outlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Divider,
  Stack,
  TextField,
  Typography,
  useMediaQuery,
} from '@mui/material'
import { QRCodeSVG } from 'qrcode.react'
import { useCallback, useEffect, useState, type ReactElement } from 'react'
import * as storefrontOrderService from '../../services/storefrontOrderService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { Order } from '../../types/order'
import { formatCurrency, formatDateTimeBR, formatItemQuantity } from '../../utils/format'
import { deriveOrderStatus, getOrderActionButtons, STATUS_TONE_COLORS, type OrderActionButton } from '../../utils/orderStatus'

interface StorefrontOrderActionDialogProps {
  orderUuid: string | null
  open: boolean
  onClose: () => void
  /** Chamado depois de qualquer ação bem-sucedida — a página que monta o dialog recarrega seu grid. */
  onChanged: () => void
  /** `perm:orders,update` (roadmap A4) — libera aprovar/rejeitar cancelamento solicitado pelo cliente. Sem ela, o pedido mostra só o badge de status. */
  canManageCancellation?: boolean
}

function formatOrderAddress(order: Order): string | null {
  const e = order.final_customer?.endereco
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

/** Ação pendente de confirmação (com ou sem motivo) — guarda o botão escolhido até o usuário confirmar. */
interface PendingAction {
  button: OrderActionButton
}

/**
 * Modal único de gestão de um pedido da loja (/pedidos-loja): mostra itens,
 * telefone, endereço e cupom + badge do status atual, e no rodapé
 * exatamente 2 (ou 0) botões de ação escolhidos dinamicamente pelo status
 * via `getOrderActionButtons`. Ação com motivo abre um sub-diálogo de motivo;
 * ação sem motivo abre uma confirmação simples. Após qualquer ação, o pedido
 * é re-buscado dentro do próprio modal (atualiza os botões pro novo estado)
 * e `onChanged` recarrega o grid por trás.
 */
export function StorefrontOrderActionDialog({
  orderUuid,
  open,
  onClose,
  onChanged,
  canManageCancellation = false,
}: StorefrontOrderActionDialogProps) {
  const isWide = useMediaQuery('(min-width:900px)')

  const [order, setOrder] = useState<Order | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)

  const [pending, setPending] = useState<PendingAction | null>(null)
  const [reasonText, setReasonText] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  const [qrUrl, setQrUrl] = useState<string | null>(null)
  const [qrExpiresAt, setQrExpiresAt] = useState<string | null>(null)
  const [isGeneratingQr, setIsGeneratingQr] = useState(false)
  const [qrError, setQrError] = useState<string | null>(null)
  const [qrOpen, setQrOpen] = useState(false)

  const fetchOrder = useCallback(async (uuid: string, showLoading: boolean) => {
    if (showLoading) setIsLoading(true)
    setLoadError(null)
    try {
      const result = await storefrontOrderService.getStorefrontOrder(uuid)
      setOrder(result)
    } catch (err) {
      setLoadError(getApiErrorMessage(err, 'Não foi possível carregar os detalhes do pedido.'))
    } finally {
      if (showLoading) setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    if (!open || !orderUuid) return
    setOrder(null)
    setPending(null)
    setReasonText('')
    setActionError(null)
    setQrUrl(null)
    setQrExpiresAt(null)
    setQrError(null)
    setQrOpen(false)
    void fetchOrder(orderUuid, true)
  }, [open, orderUuid, fetchOrder])

  function closePending() {
    if (isSubmitting) return
    setPending(null)
    setReasonText('')
    setActionError(null)
  }

  async function confirmPending() {
    if (!pending || !order) return
    setActionError(null)
    setIsSubmitting(true)
    try {
      const updated = await pending.button.run(order.uuid, reasonText.trim() || undefined)
      setOrder(updated)
      setPending(null)
      setReasonText('')
      onChanged()
    } catch (err) {
      setActionError(getApiErrorMessage(err, 'Não foi possível concluir esta ação agora.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleGenerateQr() {
    if (!order) return
    setIsGeneratingQr(true)
    setQrError(null)
    try {
      const result = await storefrontOrderService.generatePrepLink(order.uuid)
      setQrUrl(`${window.location.origin}/preparo/${order.uuid}?token=${result.token}`)
      setQrExpiresAt(result.expires_at)
      setQrOpen(true)
    } catch (err) {
      setQrError(getApiErrorMessage(err, 'Não foi possível gerar o QR de preparo agora.'))
    } finally {
      setIsGeneratingQr(false)
    }
  }

  const status = order
    ? deriveOrderStatus({
        is_cancelled: order.cancelled_at !== null,
        is_paid: order.is_paid,
        is_delivered: order.is_delivered,
        is_installment: order.is_installment,
        delivered_at: order.delivered_at,
        paid_at: order.paid_at,
        status: order.status,
        is_out_for_delivery: order.is_out_for_delivery,
      })
    : null
  const actions = order ? getOrderActionButtons(order, canManageCancellation) : []
  const address = order ? formatOrderAddress(order) : null

  return (
    <>
      <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
        <DialogTitle sx={{ pb: 1 }}>
          <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1.5 }}>
            <Box sx={{ minWidth: 0 }}>
              <Typography sx={{ fontWeight: 700, fontSize: 16, wordBreak: 'break-word' }}>
                {order?.final_customer?.name ?? 'Pedido'}
              </Typography>
              {order && (
                <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                  {order.codigo} • {formatDateTimeBR(order.created_at)}
                </Typography>
              )}
            </Box>
            {order && <Typography sx={{ fontWeight: 700, fontSize: 16, flexShrink: 0 }}>{formatCurrency(order.total_amount)}</Typography>}
          </Stack>
        </DialogTitle>

        <DialogContent dividers>
          {isLoading && (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
              <CircularProgress size={26} />
            </Box>
          )}

          {loadError && !isLoading && (
            <Alert severity="error" variant="outlined">
              {loadError}
            </Alert>
          )}

          {order && status && (
            <Stack spacing={1.75}>
              <Box>
                <Chip
                  icon={status.icon as ReactElement}
                  label={status.label}
                  sx={{
                    fontWeight: 600,
                    color: STATUS_TONE_COLORS[status.tone].fg,
                    bgcolor: STATUS_TONE_COLORS[status.tone].bg,
                    '& .MuiChip-icon': { color: STATUS_TONE_COLORS[status.tone].fg },
                  }}
                />
              </Box>

              {order.status === 'cancellation_requested' && (
                <Alert severity="warning" variant="outlined">
                  O cliente solicitou o cancelamento deste pedido
                  {order.cancellation_reason ? `: "${order.cancellation_reason}"` : ', sem motivo informado.'}
                  {!canManageCancellation && ' Você não tem permissão para aprovar/rejeitar esta solicitação.'}
                </Alert>
              )}

              <Stack spacing={0.75}>
                {(order.items ?? []).map((item) => (
                  <Stack key={item.uuid} direction="row" sx={{ justifyContent: 'space-between', gap: 1 }}>
                    <Box sx={{ minWidth: 0 }}>
                      <Typography sx={{ fontSize: 13.5, fontWeight: 600, wordBreak: 'break-word' }}>
                        {formatItemQuantity(item.quantity, item.ticket_type?.unit ?? null)} ×{' '}
                        {item.ticket_type?.name ?? item.event_product?.name ?? '—'}
                      </Typography>
                      <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>{formatCurrency(item.unit_price)} cada</Typography>
                    </Box>
                    <Typography sx={{ fontSize: 13.5, fontWeight: 600, flexShrink: 0 }}>{formatCurrency(item.line_total)}</Typography>
                  </Stack>
                ))}
              </Stack>

              <Divider />

              <Stack spacing={0.5}>
                {order.final_customer?.phone_primary && (
                  <Typography sx={{ fontSize: 13 }}>
                    <Box component="span" sx={{ color: 'var(--pt-muted)' }}>
                      Telefone:{' '}
                    </Box>
                    {order.final_customer.phone_primary}
                  </Typography>
                )}
                {address && (
                  <Typography sx={{ fontSize: 13, wordBreak: 'break-word' }}>
                    <Box component="span" sx={{ color: 'var(--pt-muted)' }}>
                      Endereço:{' '}
                    </Box>
                    {address}
                  </Typography>
                )}
                {order.coupon_code && (
                  <Typography sx={{ fontSize: 13 }}>
                    <Box component="span" sx={{ color: 'var(--pt-muted)' }}>
                      Cupom:{' '}
                    </Box>
                    {order.coupon_code}
                  </Typography>
                )}
              </Stack>

              {isWide && (
                <Box>
                  <Button
                    size="small"
                    variant="outlined"
                    startIcon={isGeneratingQr ? <CircularProgress size={14} /> : <QrCode2OutlinedIcon />}
                    onClick={() => void handleGenerateQr()}
                    disabled={isGeneratingQr}
                    sx={{ minHeight: 40 }}
                  >
                    {isGeneratingQr ? 'Gerando…' : 'Gerar QR de preparo'}
                  </Button>
                  {qrError && (
                    <Alert severity="error" variant="outlined" sx={{ mt: 1 }}>
                      {qrError}
                    </Alert>
                  )}
                </Box>
              )}
            </Stack>
          )}
        </DialogContent>

        <DialogActions sx={{ px: 3, py: 2, gap: 1 }}>
          {actions.length === 0 ? (
            <Button onClick={onClose} sx={{ minHeight: 44 }}>
              Fechar
            </Button>
          ) : (
            <Stack direction="row" spacing={1} sx={{ width: '100%' }}>
              {actions.map((button) => (
                <Button
                  key={button.label}
                  fullWidth
                  variant={button.tone === 'forward' ? 'contained' : 'outlined'}
                  color={button.tone === 'forward' ? 'primary' : button.requiresReason ? 'error' : 'inherit'}
                  onClick={() => {
                    setActionError(null)
                    setReasonText('')
                    setPending({ button })
                  }}
                  sx={{ minHeight: 44 }}
                >
                  {button.label}
                </Button>
              ))}
            </Stack>
          )}
        </DialogActions>
      </Dialog>

      {/* Confirmação da ação — com motivo obrigatório ou simples "tem certeza?". */}
      <Dialog open={pending !== null} onClose={closePending} maxWidth="xs" fullWidth>
        <DialogTitle>{pending?.button.label}</DialogTitle>
        <DialogContent>
          {pending?.button.requiresReason ? (
            <TextField
              label="Motivo"
              value={reasonText}
              onChange={(event) => setReasonText(event.target.value)}
              multiline
              minRows={2}
              fullWidth
              autoFocus
              sx={{ mt: 0.5 }}
            />
          ) : (
            <DialogContentText>Confirmar esta ação no pedido?</DialogContentText>
          )}
          {pending?.button.confirmWarning && (
            <Alert severity="warning" sx={{ mt: 1.5 }}>
              {pending.button.confirmWarning}
            </Alert>
          )}
          {actionError && (
            <Alert severity="error" variant="outlined" sx={{ mt: 1.5 }}>
              {actionError}
            </Alert>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={closePending} disabled={isSubmitting}>
            Voltar
          </Button>
          <Button
            onClick={() => void confirmPending()}
            variant="contained"
            color={pending?.button.requiresReason ? 'error' : 'primary'}
            disabled={isSubmitting || (pending?.button.requiresReason === true && !reasonText.trim())}
          >
            {isSubmitting ? 'Confirmando…' : 'Confirmar'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* QR de preparo (desktop). */}
      <Dialog open={qrOpen} onClose={() => setQrOpen(false)} maxWidth="xs">
        <DialogTitle>QR de preparo</DialogTitle>
        <DialogContent>
          <Stack spacing={1.5} sx={{ alignItems: 'center', py: 1 }}>
            {qrUrl && (
              <Box sx={{ p: 2, ...SOFT_PANEL_SX, background: '#FFFFFF' }}>
                <QRCodeSVG value={qrUrl} size={200} />
              </Box>
            )}
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', textAlign: 'center' }}>
              Aponte a câmera do celular para abrir a preparação do pedido — sem precisar de login.
            </Typography>
            {qrExpiresAt && (
              <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>Válido até {formatDateTimeBR(qrExpiresAt)}</Typography>
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setQrOpen(false)}>Fechar</Button>
        </DialogActions>
      </Dialog>
    </>
  )
}
