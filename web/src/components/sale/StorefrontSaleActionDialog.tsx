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
import * as storefrontSaleService from '../../services/storefrontSaleService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { Sale } from '../../types/sale'
import { formatCurrency, formatDateTimeBR, formatItemQuantity } from '../../utils/format'
import { deriveSaleStatus, getSaleActionButtons, STATUS_TONE_COLORS, type SaleActionButton } from '../../utils/saleStatus'

interface StorefrontSaleActionDialogProps {
  saleUuid: string | null
  open: boolean
  onClose: () => void
  /** Chamado depois de qualquer ação bem-sucedida — a página que monta o dialog recarrega seu grid. */
  onChanged: () => void
  /** `perm:sales,update` (roadmap A4) — libera aprovar/rejeitar cancelamento solicitado pelo cliente. Sem ela, a venda mostra só o badge de status. */
  canManageCancellation?: boolean
}

/** Ação pendente de confirmação (com ou sem motivo) — guarda o botão escolhido até o usuário confirmar. */
interface PendingAction {
  button: SaleActionButton
}

/**
 * Modal único de gestão de uma venda online (`/vendas-online`): mostra itens,
 * telefone, endereço e cupom + badge do status atual, e no rodapé
 * exatamente 2 (ou 0) botões de ação escolhidos dinamicamente pelo status
 * via `getSaleActionButtons`. Ação com motivo abre um sub-diálogo de motivo;
 * ação sem motivo abre uma confirmação simples. Após qualquer ação, a venda
 * é re-buscado dentro do próprio modal (atualiza os botões pro novo estado)
 * e `onChanged` recarrega o grid por trás.
 */
export function StorefrontSaleActionDialog({
  saleUuid,
  open,
  onClose,
  onChanged,
  canManageCancellation = false,
}: StorefrontSaleActionDialogProps) {
  const isWide = useMediaQuery('(min-width:900px)')

  const [sale, setSale] = useState<Sale | null>(null)
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

  const fetchSale = useCallback(async (uuid: string, showLoading: boolean) => {
    if (showLoading) setIsLoading(true)
    setLoadError(null)
    try {
      const result = await storefrontSaleService.getStorefrontSale(uuid)
      setSale(result)
    } catch (err) {
      setLoadError(getApiErrorMessage(err, 'Não foi possível carregar os detalhes da venda.'))
    } finally {
      if (showLoading) setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    if (!open || !saleUuid) return
    setSale(null)
    setPending(null)
    setReasonText('')
    setActionError(null)
    setQrUrl(null)
    setQrExpiresAt(null)
    setQrError(null)
    setQrOpen(false)
    void fetchSale(saleUuid, true)
  }, [open, saleUuid, fetchSale])

  function closePending() {
    if (isSubmitting) return
    setPending(null)
    setReasonText('')
    setActionError(null)
  }

  async function confirmPending() {
    if (!pending || !sale) return
    setActionError(null)
    setIsSubmitting(true)
    try {
      const updated = await pending.button.run(sale.uuid, reasonText.trim() || undefined)
      setSale(updated)
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
    if (!sale) return
    setIsGeneratingQr(true)
    setQrError(null)
    try {
      const result = await storefrontSaleService.generatePrepLink(sale.uuid)
      setQrUrl(`${window.location.origin}/preparo/${sale.uuid}?token=${result.token}`)
      setQrExpiresAt(result.expires_at)
      setQrOpen(true)
    } catch (err) {
      setQrError(getApiErrorMessage(err, 'Não foi possível gerar o QR de preparo agora.'))
    } finally {
      setIsGeneratingQr(false)
    }
  }

  const status = sale
    ? deriveSaleStatus({
        is_cancelled: sale.cancelled_at !== null,
        is_paid: sale.is_paid,
        is_delivered: sale.is_delivered,
        is_installment: sale.is_installment,
        delivered_at: sale.delivered_at,
        paid_at: sale.paid_at,
        status: sale.status,
      })
    : null
  const actions = sale ? getSaleActionButtons(sale, canManageCancellation) : []
  return (
    <>
      <Dialog open={open} onClose={onClose} fullWidth maxWidth="sm">
        <DialogTitle sx={{ pb: 1 }}>
          <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1.5 }}>
            <Box sx={{ minWidth: 0 }}>
              <Typography sx={{ fontWeight: 700, fontSize: 16, wordBreak: 'break-word' }}>
                {sale?.final_customer?.name ?? 'Venda'}
              </Typography>
              {sale && (
                <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                  {sale.codigo} • {formatDateTimeBR(sale.created_at)}
                </Typography>
              )}
            </Box>
            {sale && <Typography sx={{ fontWeight: 700, fontSize: 16, flexShrink: 0 }}>{formatCurrency(sale.total_amount)}</Typography>}
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

          {sale && status && (
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

              {sale.status === 'cancellation_requested' && (
                <Alert severity="warning" variant="outlined">
                  O cliente solicitou o cancelamento desta venda
                  {sale.cancellation_reason ? `: "${sale.cancellation_reason}"` : ', sem motivo informado.'}
                  {!canManageCancellation && ' Você não tem permissão para aprovar/rejeitar esta solicitação.'}
                </Alert>
              )}

              <Stack spacing={0.75}>
                {(sale.items ?? []).map((item) => (
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
                {sale.final_customer?.phone_primary && (
                  <Typography sx={{ fontSize: 13 }}>
                    <Box component="span" sx={{ color: 'var(--pt-muted)' }}>
                      Telefone:{' '}
                    </Box>
                    {sale.final_customer.phone_primary}
                  </Typography>
                )}
                {sale.coupon_code && (
                  <Typography sx={{ fontSize: 13 }}>
                    <Box component="span" sx={{ color: 'var(--pt-muted)' }}>
                      Cupom:{' '}
                    </Box>
                    {sale.coupon_code}
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
            <DialogContentText>Confirmar esta ação na venda?</DialogContentText>
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
              Aponte a câmera do celular para abrir a preparação da venda — sem precisar de login.
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
