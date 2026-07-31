import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControlLabel,
  IconButton,
  InputAdornment,
  MenuItem,
  Stack,
  Switch,
  TextField,
  Typography,
} from '@mui/material'
import { useMemo, useState } from 'react'
import * as balcaoService from '../../services/balcaoService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Order } from '../../types/order'
import type { BalcaoPaymentMethod, CloseComandaPaymentPayload, Comanda } from '../../types/balcao'
import { BALCAO_PAYMENT_METHODS, BALCAO_PAYMENT_METHOD_LABELS } from '../../constants/balcao'
import { formatCurrency } from '../../utils/format'

interface PaymentLine {
  id: string
  method: BalcaoPaymentMethod
  amount: string
}

function toCents(value: number): number {
  return Math.round(value * 100)
}

interface CloseComandaModalProps {
  comanda: Comanda
  subtotal: number
  onClose: () => void
  onCompleted: (order: Order, payments: CloseComandaPaymentPayload[]) => void
}

/**
 * Fechamento da comanda: taxa de serviço (aceitar/recusar) + split de pagamento
 * (mesmo padrão do PDV) + divisão em N partes. O TOTAL é sempre autoridade do
 * backend; o cálculo aqui é só sugestão. Se o operador RECUSAR a taxa mas o
 * estabelecimento a tiver como obrigatória, o backend força a taxa e a soma não
 * bate (422) — tratado com mensagem clara.
 */
export function CloseComandaModal({ comanda, subtotal, onClose, onCompleted }: CloseComandaModalProps) {
  const feePercent = comanda.service_fee_percent ?? 0
  const hasFee = feePercent > 0

  const [applyFee, setApplyFee] = useState(hasFee)

  const feeAmount = useMemo(() => {
    if (!hasFee || !applyFee) return 0
    return Math.round((toCents(subtotal) * feePercent) / 100) / 100
  }, [hasFee, applyFee, subtotal, feePercent])

  const total = subtotal + feeAmount

  const [payments, setPayments] = useState<PaymentLine[]>(() => [
    { id: crypto.randomUUID(), method: 'cash', amount: total.toFixed(2) },
  ])
  const [splitParts, setSplitParts] = useState('2')
  const [isSubmitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const paidTotal = payments.reduce(
    (sum, line) => sum + (Number.isFinite(Number(line.amount)) ? Number(line.amount) : 0),
    0,
  )
  const remaining = total - paidTotal
  const matches = toCents(paidTotal) === toCents(total)

  function resetToSingleLine(newTotal: number) {
    setPayments([{ id: crypto.randomUUID(), method: 'cash', amount: newTotal.toFixed(2) }])
  }

  function handleToggleFee(next: boolean) {
    setApplyFee(next)
    const nextFee = hasFee && next ? Math.round((toCents(subtotal) * feePercent) / 100) / 100 : 0
    resetToSingleLine(subtotal + nextFee)
  }

  function addPaymentLine() {
    const suggested = remaining > 0 ? remaining.toFixed(2) : '0.00'
    setPayments((current) => [...current, { id: crypto.randomUUID(), method: 'cash', amount: suggested }])
  }

  function updatePayment(id: string, patch: Partial<Pick<PaymentLine, 'method' | 'amount'>>) {
    setPayments((current) => current.map((line) => (line.id === id ? { ...line, ...patch } : line)))
  }

  function removePayment(id: string) {
    setPayments((current) => (current.length > 1 ? current.filter((line) => line.id !== id) : current))
  }

  // Divisão em N partes iguais: última linha recebe o resto dos centavos.
  function splitEqually() {
    const parts = Math.max(2, Math.min(20, Math.floor(Number(splitParts)) || 0))
    const totalCents = toCents(total)
    const base = Math.floor(totalCents / parts)
    const lines: PaymentLine[] = Array.from({ length: parts }, (_, index) => {
      const cents = index === parts - 1 ? totalCents - base * (parts - 1) : base
      return { id: crypto.randomUUID(), method: 'cash', amount: (cents / 100).toFixed(2) }
    })
    setPayments(lines)
  }

  async function handleConfirm() {
    if (!matches) return
    setSubmitting(true)
    setError(null)
    try {
      const payload = {
        apply_service_fee: applyFee,
        payments: payments.map((line) => ({ method: line.method, amount: Number(line.amount) })),
      }
      const order = await balcaoService.closeComanda(comanda.uuid, payload)
      onCompleted(order, payload.payments)
    } catch (err) {
      if (err instanceof ApiRequestError && err.code === 'PAYMENT_AMOUNT_MISMATCH') {
        setError(
          hasFee && !applyFee
            ? 'A soma não confere. A taxa de serviço pode ser obrigatória neste estabelecimento — ative a taxa e refaça a divisão.'
            : 'A soma das formas de pagamento não confere com o total. Revise os valores.',
        )
      } else if (err instanceof ApiRequestError && err.code === 'COMANDA_ERROR') {
        // Ex.: comanda já fechada por outro operador.
        setError(err.message)
      } else {
        setError(getApiErrorMessage(err, 'Não foi possível fechar a conta agora.'))
      }
      setSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} fullWidth maxWidth="sm">
      <DialogTitle sx={{ fontWeight: 700 }}>Fechar conta</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2.5}>
          {/* Resumo de valores */}
          <Box sx={{ p: 1.5, ...SOFT_PANEL_SX }}>
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography variant="body2">Subtotal</Typography>
              <Typography variant="body2" sx={{ fontWeight: 600 }}>
                {formatCurrency(subtotal)}
              </Typography>
            </Stack>
            {hasFee ? (
              <Stack direction="row" sx={{ justifyContent: 'space-between', mt: 0.5 }}>
                <Typography variant="body2">Taxa de serviço ({feePercent}%)</Typography>
                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                  {applyFee ? formatCurrency(feeAmount) : '—'}
                </Typography>
              </Stack>
            ) : null}
            <Divider sx={{ my: 1 }} />
            <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                Total
              </Typography>
              <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
                {formatCurrency(total)}
              </Typography>
            </Stack>
          </Box>

          {hasFee ? (
            <FormControlLabel
              control={<Switch checked={applyFee} onChange={(event) => handleToggleFee(event.target.checked)} />}
              label={`Cobrar taxa de serviço (${feePercent}%)`}
            />
          ) : null}

          {/* Divisão em N partes */}
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
            <TextField
              size="small"
              type="number"
              label="Dividir em"
              value={splitParts}
              onChange={(event) => setSplitParts(event.target.value)}
              sx={{ width: 110 }}
              slotProps={{
                htmlInput: { min: 2, max: 20, step: '1' },
                input: { endAdornment: <InputAdornment position="end">partes</InputAdornment> },
              }}
            />
            <Button variant="outlined" onClick={splitEqually}>
              Dividir igualmente
            </Button>
          </Stack>

          {/* Formas de pagamento */}
          <Box>
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                Pagamentos
              </Typography>
              <Button size="small" startIcon={<AddIcon />} onClick={addPaymentLine}>
                Adicionar forma
              </Button>
            </Stack>

            <Stack spacing={1.25}>
              {payments.map((line) => (
                <Stack key={line.id} direction="row" spacing={1}>
                  <TextField
                    select
                    size="small"
                    label="Forma"
                    value={line.method}
                    onChange={(event) => updatePayment(line.id, { method: event.target.value as BalcaoPaymentMethod })}
                    sx={{ flex: 1 }}
                  >
                    {BALCAO_PAYMENT_METHODS.map((method) => (
                      <MenuItem key={method} value={method}>
                        {BALCAO_PAYMENT_METHOD_LABELS[method]}
                      </MenuItem>
                    ))}
                  </TextField>
                  <TextField
                    size="small"
                    label="Valor"
                    type="number"
                    value={line.amount}
                    onChange={(event) => updatePayment(line.id, { amount: event.target.value })}
                    sx={{ width: 130 }}
                    slotProps={{
                      htmlInput: { min: 0, step: '0.01' },
                      input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                    }}
                  />
                  <IconButton
                    aria-label="Remover forma de pagamento"
                    onClick={() => removePayment(line.id)}
                    disabled={payments.length <= 1}
                  >
                    <DeleteOutlineIcon fontSize="small" />
                  </IconButton>
                </Stack>
              ))}
            </Stack>
          </Box>

          <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
            <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
              Pago {formatCurrency(paidTotal)}
            </Typography>
            {matches ? (
              <Chip size="small" color="success" label="Confere" />
            ) : (
              <Chip
                size="small"
                color={remaining > 0 ? 'warning' : 'error'}
                label={remaining > 0 ? `Falta ${formatCurrency(remaining)}` : `Sobra ${formatCurrency(-remaining)}`}
              />
            )}
          </Stack>

          {error ? <Alert severity="error">{error}</Alert> : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>
          Cancelar
        </Button>
        <Button variant="contained" onClick={handleConfirm} disabled={!matches || isSubmitting}>
          {isSubmitting ? 'Fechando…' : 'Confirmar fechamento'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
