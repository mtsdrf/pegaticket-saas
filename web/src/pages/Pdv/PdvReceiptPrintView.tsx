import PrintOutlinedIcon from '@mui/icons-material/PrintOutlined'
import { Box, Button, Divider, Stack, Typography } from '@mui/material'
import { useEffect } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../../hooks/useAuth'
import { PDV_PAYMENT_METHOD_LABELS } from '../../constants/pdvPaymentMethods'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import type { Order } from '../../types/order'
import type { PdvSalePaymentPayload } from '../../types/pdv'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'

interface ReceiptState {
  order: Order
  payments: PdvSalePaymentPayload[]
  clientName: string | null
  isOfflinePending?: boolean
  localSaleUuid?: string
}

/**
 * Recibo NÃO-FISCAL da venda concluída. Recebe `order`/`payments` via
 * `navigate` state (o `OrderResource` não devolve o split de pagamento, então
 * ele vem da tela de venda). CSS de impressão dedicado: `@media print` oculta
 * todo o resto da página (AppBar/sidebar) e imprime só o cupom estreito.
 */
export function PdvReceiptPrintView() {
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

  // Sem state (acesso direto / refresh) não há o que imprimir — volta pro PDV.
  if (!state) {
    return <Navigate to="/pdv" replace />
  }

  const { order, payments, clientName, isOfflinePending } = state
  const items = order.items ?? []

  return (
    <Box sx={{ maxWidth: 420, mx: 'auto' }}>
      <style>{`
        @media print {
          body * { visibility: hidden !important; }
          #pdv-receipt, #pdv-receipt * { visibility: visible !important; }
          #pdv-receipt {
            position: absolute; left: 0; top: 0; width: 80mm; margin: 0; padding: 4mm;
            box-shadow: none !important; border: none !important;
          }
          .pdv-no-print { display: none !important; }
        }
      `}</style>

      <Stack direction="row" spacing={1.5} className="pdv-no-print" sx={{ mb: 2 }}>
        <Button variant="outlined" fullWidth onClick={() => navigate('/pdv')}>
          Nova venda
        </Button>
        <Button variant="contained" fullWidth startIcon={<PrintOutlinedIcon />} onClick={() => window.print()}>
          Imprimir
        </Button>
      </Stack>

      <Box
        id="pdv-receipt"
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
          <Typography sx={{ fontSize: 11 }}>Pedido {order.codigo}</Typography>
          <Typography sx={{ fontSize: 11 }}>{formatDateTimeBR(order.created_at)}</Typography>
          {clientName ? <Typography sx={{ fontSize: 11 }}>Cliente: {clientName}</Typography> : null}
          {isOfflinePending ? (
            <Typography sx={{ fontSize: 11, fontWeight: 700, color: 'var(--mk-warning)' }}>
              Venda salva offline, aguardando sincronização
            </Typography>
          ) : null}
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

        <Stack direction="row" sx={{ justifyContent: 'space-between', py: 1 }}>
          <Typography sx={{ fontSize: 14, fontWeight: 800 }}>TOTAL</Typography>
          <Typography sx={{ fontSize: 14, fontWeight: 800 }}>{formatCurrency(order.total_amount)}</Typography>
        </Stack>

        <Divider sx={{ borderStyle: 'dashed' }} />

        <Stack spacing={0.5} sx={{ py: 1 }}>
          <Typography sx={{ fontSize: 11, fontWeight: 700 }}>Pagamento</Typography>
          {payments.map((payment, index) => (
            <Stack key={index} direction="row" sx={{ justifyContent: 'space-between' }}>
              <Typography sx={{ fontSize: 12 }}>{PDV_PAYMENT_METHOD_LABELS[payment.method]}</Typography>
              <Typography sx={{ fontSize: 12 }}>{formatCurrency(payment.amount)}</Typography>
            </Stack>
          ))}
        </Stack>

        <Divider sx={{ borderStyle: 'dashed' }} />

        {isOfflinePending ? (
          <>
            <Typography sx={{ fontSize: 11, textAlign: 'center', py: 1 }}>
              Este comprovante foi emitido no modo offline do PDV. A venda será enviada ao servidor assim que a conexão voltar.
            </Typography>
            <Divider sx={{ borderStyle: 'dashed' }} />
          </>
        ) : null}

        <Typography sx={{ fontSize: 11, textAlign: 'center', mt: 1.5 }}>Obrigado pela preferência!</Typography>
      </Box>
    </Box>
  )
}
