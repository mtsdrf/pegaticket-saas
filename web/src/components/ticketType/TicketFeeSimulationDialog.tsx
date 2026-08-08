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
  IconButton,
  InputAdornment,
  Stack,
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
  useMediaQuery,
  useTheme,
} from '@mui/material'
import CloseOutlinedIcon from '@mui/icons-material/CloseOutlined'
import { useEffect, useMemo, useState } from 'react'
import * as ticketFeeService from '../../services/ticketFeeService'
import { UI_RADIUS } from '../../styles/layoutStandards'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency } from '../../utils/format'
import {
  computeTicketFeePreview,
  type TicketFeePayer,
  type TicketFeePaymentMethodEstimate,
  type TicketFeeRule,
  type TicketFeeSimulationMode,
} from '../../types/ticketFee'

const QUANTITY_SHORTCUTS = [100, 500, 1000, 2500, 5000]
/** O endpoint `/simulate` limita `quantity` a 1000 — o cálculo local acima disso continua livre (só multiplicação), mas o valor enviado na chamada de rede é sempre clampado. */
const API_MAX_QUANTITY = 1000
const DEBOUNCE_MS = 400

interface TicketFeeSimulationDialogProps {
  open: boolean
  onClose: () => void
  rule: TicketFeeRule | null
  initialPriceReais: number
  initialFeePayer: TicketFeePayer
  onUsePrice: (priceReais: number) => void
}

/**
 * Inverte a fórmula `fee = max(price*percentage/100, minimum_amount)` para
 * achar o preço necessário a partir do líquido desejado pelo produtor —
 * fórmula fechada em duas faixas (mínimo constante vs. percentual), sem
 * iteração. Quando quem paga a taxa é o comprador, o produtor recebe
 * exatamente o preço definido (a taxa é somada por cima), então a inversão é
 * trivial (`price = net`).
 */
function reversePriceForTargetNet(netReais: number, rule: TicketFeeRule, feePayer: TicketFeePayer): number {
  if (feePayer === 'buyer') return Math.max(netReais, 0)
  if (netReais <= 0) return 0

  const pctFraction = rule.percentage / 100
  if (pctFraction <= 0 || pctFraction >= 1) return netReais + rule.minimum_amount

  const thresholdPrice = rule.minimum_amount / pctFraction
  const netAtThreshold = thresholdPrice - rule.minimum_amount

  if (netReais < netAtThreshold) {
    return netReais + rule.minimum_amount
  }
  return netReais / (1 - pctFraction)
}

const PAYMENT_METHOD_LABELS: Record<'pix' | 'card', string> = {
  pix: 'Pix',
  card: 'Cartão',
}

function paymentMethodLabel(estimate: TicketFeePaymentMethodEstimate): string {
  if (estimate.method === 'pix') return PAYMENT_METHOD_LABELS.pix
  return `${PAYMENT_METHOD_LABELS.card} ${estimate.installments}x`
}

export function TicketFeeSimulationDialog({
  open,
  onClose,
  rule,
  initialPriceReais,
  initialFeePayer,
  onUsePrice,
}: TicketFeeSimulationDialogProps) {
  const theme = useTheme()
  const fullScreen = useMediaQuery(theme.breakpoints.down('sm'))

  const [mode, setMode] = useState<TicketFeeSimulationMode>('price')
  const [feePayer, setFeePayer] = useState<TicketFeePayer>(initialFeePayer)
  const [amountInput, setAmountInput] = useState(initialPriceReais > 0 ? String(initialPriceReais) : '')
  const [quantityInput, setQuantityInput] = useState('1')
  const [paymentMethods, setPaymentMethods] = useState<TicketFeePaymentMethodEstimate[] | null>(null)
  const [isSimulating, setIsSimulating] = useState(false)
  const [simulationError, setSimulationError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    setMode('price')
    setFeePayer(initialFeePayer)
    setAmountInput(initialPriceReais > 0 ? String(initialPriceReais) : '')
    setQuantityInput('1')
    setPaymentMethods(null)
    setSimulationError(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const quantity = Math.max(1, Math.trunc(Number(quantityInput)) || 1)

  const priceReais = useMemo(() => {
    const amount = Number(amountInput.replace(',', '.'))
    if (!Number.isFinite(amount) || amount <= 0) return 0
    if (mode === 'price') return amount
    if (!rule) return 0
    return reversePriceForTargetNet(amount, rule, feePayer)
  }, [amountInput, mode, rule, feePayer])

  const preview = rule ? computeTicketFeePreview(priceReais, rule) : null
  const feeUnit = preview?.feeAmount ?? 0
  const buyerPaysUnit = feePayer === 'buyer' ? priceReais + feeUnit : priceReais
  const producerReceivesUnit = feePayer === 'buyer' ? priceReais : priceReais - feeUnit
  const buyerPaysTotal = buyerPaysUnit * quantity
  const producerReceivesTotal = producerReceivesUnit * quantity

  useEffect(() => {
    if (!open || !rule || priceReais <= 0) {
      setPaymentMethods(null)
      setSimulationError(null)
      return
    }

    const timer = window.setTimeout(() => {
      setIsSimulating(true)
      setSimulationError(null)
      ticketFeeService
        .simulateTicketFee({
          mode,
          amount: Math.round(Number(amountInput.replace(',', '.')) * 100),
          quantity: Math.min(quantity, API_MAX_QUANTITY),
          fee_payer: feePayer,
        })
        .then((result) => setPaymentMethods(result.payment_methods))
        .catch((error: unknown) => {
          setSimulationError(
            getApiErrorMessage(
              error,
              'Não foi possível carregar a comparação por forma de pagamento agora — a taxa PegaTicket acima continua válida.',
            ),
          )
        })
        .finally(() => setIsSimulating(false))
    }, DEBOUNCE_MS)

    return () => window.clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, rule, mode, feePayer, amountInput, quantity, priceReais])

  function handleUsePrice() {
    if (priceReais <= 0) return
    onUsePrice(Math.round(priceReais * 100) / 100)
    onClose()
  }

  return (
    <Dialog open={open} onClose={onClose} fullScreen={fullScreen} maxWidth="sm" fullWidth>
      <DialogTitle sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', fontWeight: 700 }}>
        Simular recebimento
        <IconButton onClick={onClose} size="small" aria-label="Fechar">
          <CloseOutlinedIcon fontSize="small" />
        </IconButton>
      </DialogTitle>

      <DialogContent dividers>
        {!rule ? (
          <Alert severity="warning">Não foi possível carregar a taxa de serviço vigente. Tente novamente mais tarde.</Alert>
        ) : (
          <Stack spacing={2.5}>
            <Stack spacing={1}>
              <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-muted)' }}>Modo de cálculo</Typography>
              <ToggleButtonGroup
                value={mode}
                exclusive
                onChange={(_, value: TicketFeeSimulationMode | null) => {
                  if (value) setMode(value)
                }}
                size="small"
                fullWidth
              >
                <ToggleButton value="price">Definir preço</ToggleButton>
                <ToggleButton value="target_net">Quanto quero receber</ToggleButton>
              </ToggleButtonGroup>
            </Stack>

            <Stack spacing={1}>
              <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-muted)' }}>Quem paga a taxa</Typography>
              <ToggleButtonGroup
                value={feePayer}
                exclusive
                onChange={(_, value: TicketFeePayer | null) => {
                  if (value) setFeePayer(value)
                }}
                size="small"
                fullWidth
              >
                <ToggleButton value="buyer">Comprador paga</ToggleButton>
                <ToggleButton value="producer">Produtor paga</ToggleButton>
              </ToggleButtonGroup>
            </Stack>

            <TextField
              label={mode === 'price' ? 'Preço do ingresso' : 'Quanto você quer receber por ingresso'}
              type="number"
              value={amountInput}
              onChange={(event) => setAmountInput(event.target.value)}
              fullWidth
              slotProps={{
                input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                htmlInput: { min: 0, step: '0.01' },
              }}
            />

            <Stack spacing={1}>
              <TextField
                label="Quantidade"
                type="number"
                value={quantityInput}
                onChange={(event) => setQuantityInput(event.target.value)}
                slotProps={{ htmlInput: { min: 1, step: '1' } }}
                sx={{ maxWidth: { sm: 200 } }}
              />
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                {QUANTITY_SHORTCUTS.map((shortcut) => (
                  <Chip
                    key={shortcut}
                    label={shortcut.toLocaleString('pt-BR')}
                    size="small"
                    variant={quantity === shortcut ? 'filled' : 'outlined'}
                    color={quantity === shortcut ? 'primary' : 'default'}
                    onClick={() => setQuantityInput(String(shortcut))}
                  />
                ))}
              </Box>
            </Stack>

            <Divider />

            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: 'repeat(3, minmax(0, 1fr))',
                gap: 1.5,
                p: 2,
                borderRadius: UI_RADIUS.md,
                background: 'var(--pt-form-section-bg)',
                border: '1px solid var(--pt-divider)',
              }}
            >
              <Box>
                <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>Preço unitário</Typography>
                <Typography sx={{ fontSize: 16, fontWeight: 700, color: 'var(--pt-text)' }}>
                  {formatCurrency(priceReais)}
                </Typography>
              </Box>
              <Box>
                <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>Comprador paga (total)</Typography>
                <Typography sx={{ fontSize: 16, fontWeight: 700, color: 'var(--pt-text)' }}>
                  {formatCurrency(buyerPaysTotal)}
                </Typography>
              </Box>
              <Box>
                <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>Você recebe (total)</Typography>
                <Typography sx={{ fontSize: 16, fontWeight: 700, color: 'var(--pt-success, #1a7f4b)' }}>
                  {formatCurrency(producerReceivesTotal)}
                </Typography>
              </Box>
            </Box>

            {preview?.isMinimumApplied && priceReais > 0 && (
              <Alert severity="info" sx={{ py: 0.5 }}>
                Taxa mínima de {formatCurrency(rule.minimum_amount)} aplicada para este valor.
              </Alert>
            )}

            <Stack spacing={1}>
              <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--pt-text)' }}>
                Comparativo por forma de pagamento
              </Typography>

              {simulationError && (
                <Alert severity="info" sx={{ py: 0.5 }}>
                  {simulationError}
                </Alert>
              )}

              {!simulationError && priceReais <= 0 && (
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                  Informe um valor para ver a comparação por forma de pagamento.
                </Typography>
              )}

              {!simulationError && priceReais > 0 && (
                <Stack spacing={0.5}>
                  {(paymentMethods ?? []).map((estimate) => (
                    <Box
                      key={`${estimate.method}-${estimate.installments}`}
                      sx={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        fontSize: 13,
                        py: 0.5,
                        borderBottom: '1px solid var(--pt-divider)',
                        opacity: isSimulating && !paymentMethods ? 0.5 : 1,
                      }}
                    >
                      <span>{paymentMethodLabel(estimate)}</span>
                      <span>
                        {estimate.estimated_processing_percentage == null
                          ? 'processamento não configurado'
                          : `≈ ${estimate.estimated_processing_percentage}% estimado`}
                      </span>
                    </Box>
                  ))}
                  {!paymentMethods && isSimulating && (
                    <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>Carregando comparação…</Typography>
                  )}
                </Stack>
              )}
            </Stack>
          </Stack>
        )}
      </DialogContent>

      <DialogActions sx={{ px: 3, py: 2, gap: 1 }}>
        <Button onClick={onClose} color="inherit" sx={{ flex: { xs: 1, sm: '0 0 auto' } }}>
          Cancelar
        </Button>
        <Button
          onClick={handleUsePrice}
          variant="contained"
          disabled={priceReais <= 0}
          sx={{ flex: { xs: 1, sm: '0 0 auto' } }}
        >
          Usar este preço
        </Button>
      </DialogActions>
    </Dialog>
  )
}
