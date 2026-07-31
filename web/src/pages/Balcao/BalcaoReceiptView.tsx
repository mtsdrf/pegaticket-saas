import PrintOutlinedIcon from '@mui/icons-material/PrintOutlined'
import { Box, Button, Divider, Stack, Typography } from '@mui/material'
import { useEffect } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../../hooks/useAuth'
import type { Order } from '../../types/order'
import type { CloseComandaPaymentPayload } from '../../types/balcao'
import { BALCAO_PAYMENT_METHOD_LABELS } from '../../constants/balcao'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'

interface ReceiptState {
  order: Order
  payments: CloseComandaPaymentPayload[]
  comandaTitle: string
  tableLabel: string | null
}

/**
 * Recibo NÃO-FISCAL do fechamento da comanda. Recebe `order`/`payments` via
 * `navigate` state (o split de pagamento não volta no `OrderResource`). Mostra
 * mesa/comanda e destaca a taxa de serviço (já embutida em `order.service_fee`).
 * CSS de impressão dedicado (mesmo espírito do recibo do PDV).
 */
export function BalcaoReceiptView() {
  const location = useLocation()
  const navigate = useNavigate()
  const { activeTenant } = useAuth()
  const state = location.state as ReceiptState | null

  useEffect(() => {
    if (!state) return
    document.title = `Recibo ${state.order.codigo}`
    return () => {
      document.title = 'Maskats'
    }
  }, [state])

  if (!state) {
    return <Navigate to="/balcao/mesas" replace />
  }

  const { order, payments, comandaTitle, tableLabel } = state
  const items = order.items ?? []
  const serviceFee = order.service_fee ?? 0

  return (
    <Box sx={{ maxWidth: 420, mx: 'auto' }}>
      <style>{`
        @media print {
          body * { visibility: hidden !important; }
          #balcao-receipt, #balcao-receipt * { visibility: visible !important; }
          #balcao-receipt {
            position: absolute; left: 0; top: 0; width: 80mm; margin: 0; padding: 4mm;
            box-shadow: none !important; border: none !important;
          }
          .balcao-no-print { display: none !important; }
        }
      `}</style>

      <Stack direction="row" spacing={1.5} className="balcao-no-print" sx={{ mb: 2 }}>
        <Button variant="outlined" fullWidth onClick={() => navigate('/balcao/mesas')}>
          Voltar ao balcão
        </Button>
        <Button variant="contained" fullWidth startIcon={<PrintOutlinedIcon />} onClick={() => window.print()}>
          Imprimir
        </Button>
      </Stack>

      <Box
        id="balcao-receipt"
        sx={{
          ...SOFT_PANEL_SX,
          p: 2.5,
          fontFamily: 'monospace',
          color: 'var(--mk-text)',
        }}
      >
        <Stack spacing={0.25} sx={{ textAlign: 'center', mb: 1.5 }}>
          <Typography sx={{ fontWeight: 800, fontSize: 16 }}>{activeTenant?.tenant_name ?? 'Maskats'}</Typography>
          <Typography sx={{ fontSize: 11 }}>Recibo não-fiscal</Typography>
          <Typography sx={{ fontSize: 11 }}>{tableLabel ? `Mesa: ${tableLabel}` : comandaTitle}</Typography>
          <Typography sx={{ fontSize: 11 }}>Pedido {order.codigo}</Typography>
          <Typography sx={{ fontSize: 11 }}>{formatDateTimeBR(order.created_at)}</Typography>
        </Stack>

        <Divider sx={{ borderStyle: 'dashed' }} />

        <Stack spacing={0.75} sx={{ py: 1 }}>
          {items.map((item) => (
            <Box key={item.uuid} sx={{ fontSize: 12 }}>
              <Typography sx={{ fontSize: 12, fontWeight: 700 }}>{item.product.name}</Typography>
              <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
                <Typography sx={{ fontSize: 12 }}>
                  {item.quantity} x {formatCurrency(item.unit_price)}
                </Typography>
                <Typography sx={{ fontSize: 12 }}>{formatCurrency(item.line_total)}</Typography>
              </Stack>
            </Box>
          ))}
        </Stack>

        <Divider sx={{ borderStyle: 'dashed' }} />

        {serviceFee > 0 ? (
          <Stack direction="row" sx={{ justifyContent: 'space-between', pt: 1 }}>
            <Typography sx={{ fontSize: 12 }}>Taxa de serviço</Typography>
            <Typography sx={{ fontSize: 12 }}>{formatCurrency(serviceFee)}</Typography>
          </Stack>
        ) : null}

        <Stack direction="row" sx={{ justifyContent: 'space-between', py: 1 }}>
          <Typography sx={{ fontSize: 14, fontWeight: 800 }}>TOTAL</Typography>
          <Typography sx={{ fontSize: 14, fontWeight: 800 }}>{formatCurrency(order.total_amount)}</Typography>
        </Stack>

        <Divider sx={{ borderStyle: 'dashed' }} />

        <Stack spacing={0.5} sx={{ py: 1 }}>
          <Typography sx={{ fontSize: 11, fontWeight: 700 }}>Pagamento</Typography>
          {payments.map((payment, index) => (
            <Stack key={index} direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ fontSize: 12 }}>{BALCAO_PAYMENT_METHOD_LABELS[payment.method]}</Typography>
              <Typography sx={{ fontSize: 12 }}>{formatCurrency(payment.amount)}</Typography>
            </Stack>
          ))}
        </Stack>

        <Divider sx={{ borderStyle: 'dashed' }} />

        <Typography sx={{ fontSize: 11, textAlign: 'center', mt: 1.5 }}>Obrigado pela preferência!</Typography>
      </Box>
    </Box>
  )
}
