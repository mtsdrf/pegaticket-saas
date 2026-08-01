import AddIcon from '@mui/icons-material/Add'
import DownloadOutlinedIcon from '@mui/icons-material/DownloadOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import UploadFileOutlinedIcon from '@mui/icons-material/UploadFileOutlined'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControlLabel,
  FormLabel,
  InputAdornment,
  Radio,
  RadioGroup,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import * as saleRefundService from '../../services/saleRefundService'
import * as ticketService from '../../services/ticketService'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Sale } from '../../types/sale'
import type { SaleRefund, SaleRefundPayload, SaleRefundType } from '../../types/saleRefund'
import type { Ticket } from '../../types/ticket'
import { formatCurrency, formatDateBR, formatDateTimeBR } from '../../utils/format'

const MAX_RECEIPT_MB = 10

interface SaleRefundsSectionProps {
  sale: Sale
  /** Chamado depois de registrar um estorno com sucesso — quem monta a seção decide se precisa recarregar o pedido/lista. */
  onRefundRegistered?: () => void
}

/**
 * Estorno EXTERNO (spec 5.14/11.3): o clube já estornou o pagamento no
 * PagBank fora do sistema — esta seção só REGISTRA o que já aconteceu e
 * aplica os efeitos internos (ingressos invalidados, lugar liberado se
 * escolhido). Só faz sentido quando o pedido tem algum valor pago.
 */
export function SaleRefundsSection({ sale, onRefundRegistered }: SaleRefundsSectionProps) {
  const { can } = useAccessControl()
  const canRead = can(ACCESS.saleRefundsRead)
  const canCreate = can(ACCESS.saleRefundsCreate)

  const [refunds, setRefunds] = useState<SaleRefund[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [downloadingUuid, setDownloadingUuid] = useState<string | null>(null)
  const [dialogOpen, setDialogOpen] = useState(false)

  const hasPaidAmount = sale.is_paid || (sale.paid_amount ?? 0) > 0

  const load = useCallback(async () => {
    setIsLoading(true)
    setLoadError(null)
    try {
      const result = await saleRefundService.listSaleRefunds(sale.uuid)
      setRefunds(result)
    } catch (err) {
      setLoadError(getApiErrorMessage(err, 'Não foi possível carregar os estornos deste pedido agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [sale.uuid])

  useEffect(() => {
    if (!canRead || !hasPaidAmount) return
    void load()
  }, [canRead, hasPaidAmount, load])

  async function handleDownloadReceipt(refundUuid: string) {
    setDownloadingUuid(refundUuid)
    try {
      await saleRefundService.downloadSaleRefundReceipt(sale.uuid, refundUuid)
    } catch (err) {
      setLoadError(getApiErrorMessage(err, 'Não foi possível baixar o comprovante agora.'))
    } finally {
      setDownloadingUuid(null)
    }
  }

  if (!canRead || !hasPaidAmount) return null

  return (
    <Box>
      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', mb: 1 }}>
        <Typography sx={{ fontWeight: 700 }}>Estornos</Typography>
        {canCreate && !sale.cancelled_at && (
          <Button size="small" startIcon={<AddIcon />} onClick={() => setDialogOpen(true)} sx={{ minHeight: 44 }}>
            Registrar estorno
          </Button>
        )}
      </Stack>

      {loadError && (
        <Alert severity="error" variant="outlined" sx={{ mb: 1 }}>
          {loadError}
        </Alert>
      )}

      {isLoading && !loadError && <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Carregando estornos…</Typography>}

      {!isLoading && !loadError && refunds.length === 0 && (
        <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhum estorno registrado para este pedido.</Typography>
      )}

      {!isLoading && refunds.length > 0 && (
        <Stack spacing={1.25}>
          {refunds.map((refund) => (
            <Box key={refund.uuid} sx={{ p: 1.25, borderRadius: '12px', border: '1px solid var(--pt-border)' }}>
              <Stack spacing={0.6}>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 0.5 }}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                    <Chip
                      size="small"
                      label={refund.type === 'total' ? 'Total' : 'Parcial'}
                      color={refund.type === 'total' ? 'error' : 'warning'}
                      sx={{ fontWeight: 700 }}
                    />
                    <Typography sx={{ fontWeight: 700 }}>{formatCurrency(refund.amount)}</Typography>
                  </Stack>
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                    Estornado em {formatDateBR(refund.refunded_at)} • registrado em {formatDateTimeBR(refund.created_at)}
                  </Typography>
                </Stack>

                <Typography sx={{ fontSize: 13.5 }}>{refund.reason}</Typography>

                {refund.notes && (
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>Obs.: {refund.notes}</Typography>
                )}

                {refund.external_reference && (
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                    Referência externa: {refund.external_reference}
                  </Typography>
                )}

                {refund.release_seats && (
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>Lugares liberados.</Typography>
                )}

                {refund.type === 'parcial' && refund.tickets && refund.tickets.length > 0 && (
                  <Stack direction="row" spacing={0.5} sx={{ flexWrap: 'wrap', rowGap: 0.5, mt: 0.25 }}>
                    {refund.tickets.map((ticket) => (
                      <Chip key={ticket.uuid} size="small" variant="outlined" label={ticket.code} />
                    ))}
                  </Stack>
                )}

                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mt: 0.5 }}>
                  <Chip size="small" variant="outlined" label={refund.status === 'registrado' ? 'Registrado' : refund.status} />
                  {refund.has_receipt && (
                    <Button
                      size="small"
                      startIcon={<DownloadOutlinedIcon />}
                      disabled={downloadingUuid === refund.uuid}
                      onClick={() => void handleDownloadReceipt(refund.uuid)}
                      sx={{ minHeight: 36 }}
                    >
                      {downloadingUuid === refund.uuid ? 'Baixando…' : 'Comprovante'}
                    </Button>
                  )}
                </Stack>
              </Stack>
            </Box>
          ))}
        </Stack>
      )}

      {dialogOpen && (
        <SaleRefundFormDialog
          sale={sale}
          onClose={() => setDialogOpen(false)}
          onRegistered={() => {
            setDialogOpen(false)
            void load()
            onRefundRegistered?.()
          }}
        />
      )}
    </Box>
  )
}

interface SaleRefundFormDialogProps {
  sale: Sale
  onClose: () => void
  onRegistered: () => void
}

function SaleRefundFormDialog({ sale, onClose, onRegistered }: SaleRefundFormDialogProps) {
  const fileInputRef = useRef<HTMLInputElement>(null)
  const hasSeatItems = Boolean(sale.items?.some((item) => item.seat))

  const [type, setType] = useState<SaleRefundType>('total')
  const [amount, setAmount] = useState('')
  const [reason, setReason] = useState('')
  const [refundedAt, setRefundedAt] = useState(new Date().toISOString().slice(0, 10))
  const [externalReference, setExternalReference] = useState('')
  const [notes, setNotes] = useState('')
  const [releaseSeats, setReleaseSeats] = useState(false)
  const [receipt, setReceipt] = useState<File | null>(null)

  const [tickets, setTickets] = useState<Ticket[]>([])
  const [isLoadingTickets, setIsLoadingTickets] = useState(false)
  const [ticketsError, setTicketsError] = useState<string | null>(null)
  const [selectedTicketUuids, setSelectedTicketUuids] = useState<string[]>([])

  const [formError, setFormError] = useState<string | null>(null)
  const [amountError, setAmountError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    if (type !== 'parcial' || tickets.length > 0 || isLoadingTickets) return
    let cancelled = false
    setIsLoadingTickets(true)
    setTicketsError(null)
    ticketService
      .listTickets({ sale_uuid: sale.uuid, per_page: 100 })
      .then((result) => {
        if (!cancelled) setTickets(result.items)
      })
      .catch((err) => {
        if (!cancelled) setTicketsError(getApiErrorMessage(err, 'Não foi possível carregar os ingressos deste pedido agora.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoadingTickets(false)
      })
    return () => {
      cancelled = true
    }
  }, [type, sale.uuid, tickets.length, isLoadingTickets])

  function toggleTicket(uuid: string) {
    setSelectedTicketUuids((current) => (current.includes(uuid) ? current.filter((entry) => entry !== uuid) : [...current, uuid]))
  }

  function handleReceiptChange(file: File | null) {
    setFormError(null)
    if (file && file.size > MAX_RECEIPT_MB * 1024 * 1024) {
      setFormError(`O comprovante deve ter no máximo ${MAX_RECEIPT_MB}MB.`)
      if (fileInputRef.current) fileInputRef.current.value = ''
      return
    }
    setReceipt(file)
  }

  const isPartialMissingTickets = type === 'parcial' && selectedTicketUuids.length === 0

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setFormError(null)
    setAmountError(null)
    setFieldErrors({})

    if (!reason.trim()) {
      setFormError('Informe o motivo do estorno.')
      return
    }
    if (!amount.trim() || Number(amount) <= 0) {
      setAmountError('Informe um valor de estorno válido.')
      return
    }
    if (isPartialMissingTickets) {
      setFormError('Selecione ao menos um ingresso afetado pelo estorno parcial.')
      return
    }

    setIsSubmitting(true)
    const payload: SaleRefundPayload = {
      type,
      amount: Number(amount),
      reason: reason.trim(),
      refunded_at: refundedAt,
      external_reference: externalReference.trim() || undefined,
      notes: notes.trim() || undefined,
      release_seats: releaseSeats,
      receipt,
      ...(type === 'parcial' ? { ticket_uuids: selectedTicketUuids } : {}),
    }

    try {
      await saleRefundService.createSaleRefund(sale.uuid, payload)
      onRegistered()
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setFieldErrors(err.errors)
        if (err.errors.amount?.[0]) {
          setAmountError(err.errors.amount[0])
        } else if (err.code === 'INVALID_ORDER_STATE' && /valor|estorno/i.test(err.message)) {
          setAmountError(getApiErrorMessage(err, 'Valor de estorno inválido.'))
        } else {
          setFormError(getApiErrorMessage(err, 'Não foi possível registrar o estorno agora.'))
        }
      } else {
        setFormError(getApiErrorMessage(err, 'Não foi possível registrar o estorno agora.'))
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} fullWidth maxWidth="sm">
      <DialogTitle sx={{ fontWeight: 700 }}>Registrar estorno</DialogTitle>
      <Box component="form" onSubmit={(event) => void handleSubmit(event)} noValidate>
        <DialogContent dividers>
          <Stack spacing={2.25}>
            <Alert severity="info" variant="outlined">
              O estorno já precisa ter sido feito no PagBank fora do sistema. Aqui você só registra o que aconteceu e aplica os
              efeitos internos (ingressos invalidados e, se escolher, lugar liberado).
            </Alert>

            {formError && <Alert severity="error">{formError}</Alert>}

            <Box>
              <FormLabel sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-text)' }}>Tipo de estorno</FormLabel>
              <RadioGroup
                row
                value={type}
                onChange={(event) => {
                  setType(event.target.value as SaleRefundType)
                  setSelectedTicketUuids([])
                }}
              >
                <FormControlLabel value="total" control={<Radio />} label="Total" />
                <FormControlLabel value="parcial" control={<Radio />} label="Parcial" />
              </RadioGroup>
            </Box>

            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
              <TextField
                label="Valor estornado"
                type="number"
                required
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                error={Boolean(amountError)}
                helperText={amountError}
                slotProps={{
                  htmlInput: { min: 0.01, step: '0.01' },
                  input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                }}
                sx={{ width: { xs: '100%', sm: '50%' } }}
              />
              <TextField
                label="Data do estorno"
                type="date"
                required
                value={refundedAt}
                onChange={(event) => setRefundedAt(event.target.value)}
                error={Boolean(fieldErrors.refunded_at?.[0])}
                helperText={fieldErrors.refunded_at?.[0]}
                slotProps={{ inputLabel: { shrink: true } }}
                sx={{ width: { xs: '100%', sm: '50%' } }}
              />
            </Stack>

            <TextField
              label="Motivo"
              required
              value={reason}
              onChange={(event) => setReason(event.target.value.slice(0, 2000))}
              error={Boolean(fieldErrors.reason?.[0])}
              helperText={fieldErrors.reason?.[0]}
              multiline
              minRows={2}
            />

            <TextField
              label="Identificador externo (opcional)"
              value={externalReference}
              onChange={(event) => setExternalReference(event.target.value.slice(0, 255))}
              error={Boolean(fieldErrors.external_reference?.[0])}
              helperText={fieldErrors.external_reference?.[0]}
            />

            <TextField
              label="Observações (opcional)"
              value={notes}
              onChange={(event) => setNotes(event.target.value.slice(0, 2000))}
              error={Boolean(fieldErrors.notes?.[0])}
              helperText={fieldErrors.notes?.[0]}
              multiline
              minRows={2}
            />

            {hasSeatItems && (
              <FormControlLabel
                control={<Switch checked={releaseSeats} onChange={(event) => setReleaseSeats(event.target.checked)} />}
                label="Liberar lugares dos ingressos afetados"
              />
            )}

            <Box>
              <input
                ref={fileInputRef}
                type="file"
                hidden
                accept=".pdf,.jpg,.jpeg,.png"
                onChange={(event) => handleReceiptChange(event.target.files?.[0] ?? null)}
              />
              <Button
                variant="outlined"
                startIcon={<UploadFileOutlinedIcon />}
                onClick={() => fileInputRef.current?.click()}
                sx={{ minHeight: 44 }}
              >
                {receipt ? receipt.name : 'Anexar comprovante (opcional)'}
              </Button>
              {receipt && (
                <Button size="small" color="inherit" onClick={() => handleReceiptChange(null)} sx={{ ml: 1 }}>
                  Remover
                </Button>
              )}
              {fieldErrors.receipt?.[0] && (
                <Typography sx={{ fontSize: 12.5, color: 'var(--pt-danger)', mt: 0.5 }}>{fieldErrors.receipt[0]}</Typography>
              )}
            </Box>

            {type === 'parcial' && (
              <Box>
                <Divider sx={{ mb: 1.5 }} />
                <Typography sx={{ fontWeight: 700, mb: 0.5 }}>Ingressos afetados</Typography>
                <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mb: 1 }}>
                  Selecione os ingressos deste pedido que foram estornados.
                </Typography>

                {ticketsError && (
                  <Alert severity="error" variant="outlined" sx={{ mb: 1 }}>
                    {ticketsError}
                  </Alert>
                )}
                {isLoadingTickets && <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Carregando ingressos…</Typography>}
                {!isLoadingTickets && !ticketsError && tickets.length === 0 && (
                  <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhum ingresso encontrado para este pedido.</Typography>
                )}

                {!isLoadingTickets && tickets.length > 0 && (
                  <Stack spacing={0.25}>
                    {tickets.map((ticket) => {
                      const alreadyRefunded = ticket.status === 'estornado'
                      return (
                        <FormControlLabel
                          key={ticket.uuid}
                          control={
                            <Checkbox
                              checked={selectedTicketUuids.includes(ticket.uuid)}
                              disabled={alreadyRefunded}
                              onChange={() => toggleTicket(ticket.uuid)}
                            />
                          }
                          label={
                            <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                              <Typography sx={{ fontSize: 13.5 }}>{ticket.code}</Typography>
                              {alreadyRefunded && <Chip size="small" label="Já estornado" variant="outlined" />}
                            </Stack>
                          }
                        />
                      )
                    })}
                  </Stack>
                )}
                {fieldErrors.ticket_uuids?.[0] && (
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-danger)', mt: 0.5 }}>{fieldErrors.ticket_uuids[0]}</Typography>
                )}
              </Box>
            )}
          </Stack>
        </DialogContent>
        <DialogActions sx={{ px: 3, py: 2 }}>
          <Button onClick={onClose} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button
            type="submit"
            variant="contained"
            startIcon={<ReceiptLongOutlinedIcon />}
            disabled={isSubmitting || isPartialMissingTickets}
          >
            {isSubmitting ? 'Registrando…' : 'Registrar estorno'}
          </Button>
        </DialogActions>
      </Box>
    </Dialog>
  )
}
