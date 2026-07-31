import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import MyLocationOutlinedIcon from '@mui/icons-material/MyLocationOutlined'
import OpenInNewOutlinedIcon from '@mui/icons-material/OpenInNewOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import { Box, Button, Chip, Divider, Paper, Stack, Typography } from '@mui/material'
import { OrderStatusAction } from '../order/OrderStatusAction'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { googleMapsDirectionsUrl } from '../../services/osrmService'
import { formatCurrency, formatDateBR } from '../../utils/format'
import type { OptimizedStop, RouteInstallmentRef, RouteOrderRef, RouteType } from '../../types/route'

function addressSummary(stop: OptimizedStop['stop']): string {
  const { logradouro, numero, bairro_name, cidade_name } = stop.endereco
  const line = [logradouro, numero].filter(Boolean).join(', ')
  const locality = [bairro_name, cidade_name].filter(Boolean).join(' — ')
  return [line, locality].filter(Boolean).join(' · ')
}

function isDelivered(order: RouteOrderRef, overrides: Map<string, boolean>): boolean {
  return overrides.get(order.uuid) ?? order.is_delivered
}

function isOrderPaid(order: RouteOrderRef, overrides: Map<string, boolean>): boolean {
  return overrides.get(order.uuid) ?? order.is_paid
}

function isInstallmentPaid(installment: RouteInstallmentRef, overrides: Map<string, boolean>): boolean {
  return overrides.get(installment.uuid) ?? installment.is_paid
}

interface RouteResultStopCardProps {
  optimized: OptimizedStop
  type: RouteType
  processingKey: string | null
  deliveredOverrides: Map<string, boolean>
  paidOverrides: Map<string, boolean>
  installmentPaidOverrides: Map<string, boolean>
  isNearby?: boolean
  onDeliver: (orderUuid: string) => void
  onUndeliver: (orderUuid: string) => void
  onPayOrder: (orderUuid: string) => void
  onUnpayOrder: (orderUuid: string) => void
  onPayInstallment: (orderUuid: string, installmentUuid: string) => void
  onUnpayInstallment: (orderUuid: string, installmentUuid: string) => void
  /** Abre o `OrderDetailDialog` pro pedido de origem — disponível tanto em pedidos de entrega quanto em parcelas (toda installment tem `order_uuid`). */
  onViewOrder: (orderUuid: string) => void
}

export function RouteResultStopCard({
  optimized,
  type,
  processingKey,
  deliveredOverrides,
  paidOverrides,
  installmentPaidOverrides,
  isNearby = false,
  onDeliver,
  onUndeliver,
  onPayOrder,
  onUnpayOrder,
  onPayInstallment,
  onUnpayInstallment,
  onViewOrder,
}: RouteResultStopCardProps) {
  const { stop, position, location } = optimized
  const mapsUrl = googleMapsDirectionsUrl({ lat: location[1], lng: location[0] })

  return (
    <Paper
      variant="outlined"
      sx={{
        p: 2,
        ...ELEVATED_SURFACE_SX,
        borderColor: isNearby ? 'var(--mk-primary)' : 'var(--mk-border)',
        borderWidth: isNearby ? 2 : 1,
        background: isNearby
          ? 'color-mix(in srgb, var(--mk-primary) 6%, var(--mk-surface))'
          : ELEVATED_SURFACE_SX.background,
        transition: 'border-color 0.2s ease, background-color 0.2s ease',
      }}
    >
      {isNearby && (
        <Chip
          size="small"
          icon={<MyLocationOutlinedIcon />}
          label="Você está aqui"
          sx={{
            mb: 1.25,
            fontWeight: 700,
            bgcolor: 'var(--mk-primary)',
            color: '#fff',
            '& .MuiChip-icon': { color: 'inherit' },
          }}
        />
      )}

      <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', minHeight: 64 }}>
        <Box
          sx={{
            flexShrink: 0,
            width: 32,
            height: 32,
            borderRadius: '14px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontWeight: 700,
            fontSize: 14,
            bgcolor: 'var(--mk-secondary)',
            color: '#fff',
          }}
        >
          {position}
        </Box>

        <Box sx={{ minWidth: 0, flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', textAlign: 'center' }}>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 15, color: 'var(--mk-text)' }}>{stop.client_name}</Typography>
          <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)', mt: 0.25 }}>{addressSummary(stop)}</Typography>

          <Box sx={{ display: 'flex', justifyContent: 'center', mt: 0.5 }}>
            <Button
              size="small"
              variant="text"
              startIcon={<OpenInNewOutlinedIcon />}
              component="a"
              href={mapsUrl}
              target="_blank"
              rel="noreferrer"
              sx={{ minHeight: 36 }}
            >
              Abrir no mapa
            </Button>
          </Box>
        </Box>
      </Stack>

      <Divider sx={{ my: 1.5, borderColor: 'var(--mk-border)' }} />

      {type === 'delivery' ? (
        <Stack spacing={1.25}>
          {stop.orders.map((order) => {
            const delivered = isDelivered(order, deliveredOverrides)
            const paid = isOrderPaid(order, paidOverrides)
            const isDeliveryProcessing = processingKey === `order:${order.uuid}`
            const isPayProcessing = processingKey === `order-pay:${order.uuid}`

            return (
              <Stack key={order.uuid} spacing={0.75}>
                <Typography sx={{ fontSize: 14, color: 'var(--mk-text)' }}>
                  Pedido — {formatCurrency(order.total_amount)}
                  {!paid && (
                    <Typography component="span" sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
                      {' '}(a receber)
                    </Typography>
                  )}
                </Typography>

                <Stack
                  direction={{ xs: 'column', sm: 'row' }}
                  spacing={1.25}
                  sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 1.25 }}
                >
                  <OrderStatusAction
                    isDone={delivered}
                    isProcessing={isDeliveryProcessing}
                    doneLabel="Entregue"
                    doneIcon={<CheckCircleOutlineIcon />}
                    actionLabel="Marcar como entregue"
                    actionIcon={<LocalShippingOutlinedIcon />}
                    onMark={() => onDeliver(order.uuid)}
                    onUndo={() => onUndeliver(order.uuid)}
                  />

                  {!order.is_installment && (
                    <OrderStatusAction
                      isDone={paid}
                      isProcessing={isPayProcessing}
                      doneLabel="Pago"
                      doneIcon={<CheckCircleOutlineIcon />}
                      actionLabel="Marcar como pago"
                      actionIcon={<PaidOutlinedIcon />}
                      onMark={() => onPayOrder(order.uuid)}
                      onUndo={() => onUnpayOrder(order.uuid)}
                    />
                  )}

                  <Button
                    size="small"
                    variant="text"
                    startIcon={<ReceiptLongOutlinedIcon fontSize="small" />}
                    onClick={() => onViewOrder(order.uuid)}
                    sx={{ minHeight: 44, minWidth: 44, color: 'var(--mk-muted)' }}
                  >
                    Ver detalhes do pedido
                  </Button>
                </Stack>
              </Stack>
            )
          })}
        </Stack>
      ) : (
        <Stack spacing={1}>
          {stop.installments.map((installment) => {
            const paid = isInstallmentPaid(installment, installmentPaidOverrides)
            const isProcessing = processingKey === `installment:${installment.uuid}`
            return (
              <Stack
                key={installment.uuid}
                direction={{ xs: 'column', sm: 'row' }}
                spacing={1}
                sx={{ alignItems: { xs: 'stretch', sm: 'center' }, justifyContent: 'space-between', flexWrap: 'wrap', rowGap: 1 }}
              >
                <Box>
                  <Typography sx={{ fontSize: 14, color: 'var(--mk-text)' }}>
                    Parcela — {formatCurrency(installment.amount)}
                  </Typography>
                  <Typography sx={{ fontSize: 12.5, color: installment.is_overdue ? 'var(--mk-danger)' : 'var(--mk-muted)' }}>
                    Vencimento {formatDateBR(installment.due_date)}
                    {installment.is_overdue ? ' · Atrasada' : ''}
                  </Typography>
                </Box>

                <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                  <OrderStatusAction
                    isDone={paid}
                    isProcessing={isProcessing}
                    doneLabel="Pago"
                    doneIcon={<CheckCircleOutlineIcon />}
                    actionLabel="Marcar como pago"
                    actionIcon={<PaidOutlinedIcon />}
                    onMark={() => onPayInstallment(installment.order_uuid, installment.uuid)}
                    onUndo={() => onUnpayInstallment(installment.order_uuid, installment.uuid)}
                  />

                  <Button
                    size="small"
                    variant="text"
                    startIcon={<ReceiptLongOutlinedIcon fontSize="small" />}
                    onClick={() => onViewOrder(installment.order_uuid)}
                    sx={{ minHeight: 44, minWidth: 44, color: 'var(--mk-muted)' }}
                  >
                    Ver pedido
                  </Button>
                </Stack>
              </Stack>
            )
          })}
        </Stack>
      )}
    </Paper>
  )
}
