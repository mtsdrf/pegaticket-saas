import ContentCopyRoundedIcon from '@mui/icons-material/ContentCopyRounded'
import OpenInNewRoundedIcon from '@mui/icons-material/OpenInNewRounded'
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useMemo, useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import type { SubscriptionInvoice, SubscriptionInvoicePayment } from '../../types/subscription'
import { formatCurrency } from '../../utils/format'

interface SubscriptionInvoicePixDialogProps {
  invoice: SubscriptionInvoice | null
  open: boolean
  payment: SubscriptionInvoicePayment | null
  error: string | null
  loading: boolean
  onClose: () => void
}

export function SubscriptionInvoicePixDialog({
  invoice,
  open,
  payment,
  error,
  loading,
  onClose,
}: SubscriptionInvoicePixDialogProps) {
  const [copied, setCopied] = useState(false)

  const qrCode = payment?.metadata?.qr_code ?? null
  const qrCodeBase64 = payment?.metadata?.qr_code_base64 ?? null
  const ticketUrl = payment?.metadata?.ticket_url ?? null

  const helperText = useMemo(() => {
    if (!invoice) return null
    return `Fatura ${invoice.competence_period} · ${formatCurrency(invoice.amount_net)}`
  }, [invoice])

  async function handleCopy() {
    if (!qrCode) return
    try {
      await navigator.clipboard.writeText(qrCode)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2500)
    } catch {
      setCopied(false)
    }
  }

  return (
    <Dialog open={open} onClose={loading ? undefined : onClose} fullWidth maxWidth="sm">
      <DialogTitle>Pagar fatura com Pix</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          {helperText && (
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
              {helperText}
            </Typography>
          )}

          {loading && (
            <Alert severity="info" variant="outlined">
              Gerando cobrança Pix…
            </Alert>
          )}

          {!loading && error && (
            <Alert severity="error" variant="outlined">
              {error}
            </Alert>
          )}

          {!loading && !error && payment && (
            <>
              {qrCode ? (
                <Box sx={{ display: 'flex', justifyContent: 'center', p: 2, ...SOFT_PANEL_SX, background: '#fff' }}>
                  <QRCodeSVG value={qrCode} size={220} />
                </Box>
              ) : qrCodeBase64 ? (
                <Box sx={{ display: 'flex', justifyContent: 'center', p: 1 }}>
                  <Box
                    component="img"
                    src={`data:image/png;base64,${qrCodeBase64}`}
                    alt="QR Code Pix"
                    sx={{ width: 220, height: 220, ...SOFT_PANEL_SX, background: '#fff' }}
                  />
                </Box>
              ) : ticketUrl ? (
                <Button
                  component="a"
                  href={ticketUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  variant="outlined"
                  startIcon={<OpenInNewRoundedIcon />}
                >
                  Abrir cobrança Pix
                </Button>
              ) : (
                <Alert severity="warning" variant="outlined">
                  A cobrança foi criada, mas ainda não recebemos os dados do QR Code. Você pode tentar abrir a cobrança ou gerar novamente mais tarde.
                </Alert>
              )}

              {qrCode && (
                <Stack spacing={1}>
                  <TextField
                    label="Código Pix copia e cola"
                    value={qrCode}
                    fullWidth
                    multiline
                    minRows={3}
                    slotProps={{ htmlInput: { readOnly: true } }}
                  />
                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                    <Button variant="outlined" startIcon={<ContentCopyRoundedIcon />} onClick={() => void handleCopy()}>
                      {copied ? 'Copiado' : 'Copiar código Pix'}
                    </Button>
                    {ticketUrl && (
                      <Button
                        component="a"
                        href={ticketUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        variant="text"
                        startIcon={<OpenInNewRoundedIcon />}
                      >
                        Abrir no Mercado Pago
                      </Button>
                    )}
                  </Stack>
                </Stack>
              )}
            </>
          )}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={loading}>
          Fechar
        </Button>
      </DialogActions>
    </Dialog>
  )
}
