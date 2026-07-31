import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import WhatsAppIcon from '@mui/icons-material/WhatsApp'
import { Alert, Box, Button, Divider, IconButton, Paper, Stack, Tooltip, Typography } from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { OrderDetailDialog } from '../../components/order/OrderDetailDialog'
import { OrderStatusAction } from '../../components/order/OrderStatusAction'
import { useAuth } from '../../hooks/useAuth'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import * as clientService from '../../services/clientService'
import * as orderService from '../../services/orderService'
import { getApiErrorMessage } from '../../types/api'
import type { Client, ClientEndereco } from '../../types/client'
import type { Order } from '../../types/order'
import { formatCurrency, formatDateBR } from '../../utils/format'
import { buildOrderSummaryWhatsAppMessage, buildWhatsAppUrl, isDigitsOnlyPhone } from '../../utils/whatsApp'

function clientAddressSummary(endereco: ClientEndereco): string {
  const line = [endereco.logradouro, endereco.numero].filter(Boolean).join(', ')
  const locality = [endereco.bairro_name, endereco.cidade_name].filter(Boolean).join(' — ')
  return [line, locality].filter(Boolean).join(' · ')
}

interface ClientOrderCardProps {
  order: Order
  primaryPhone: string | null
  secondaryPhone: string | null
  processingKey: string | null
  onDeliver: () => void
  onUndeliver: () => void
  onPayOrder: () => void
  onUnpayOrder: () => void
  onPayInstallment: (installmentUuid: string) => void
  onUnpayInstallment: (installmentUuid: string) => void
  onViewDetails: () => void
  onSendWhatsApp: (phone: string) => void
}

/** Card de um pedido do cliente — mesmo espírito visual/interação de `RouteResultStopCard`, reaproveitando `OrderStatusAction` pro par entregue/pago. */
function ClientOrderCard({
  order,
  primaryPhone,
  secondaryPhone,
  processingKey,
  onDeliver,
  onUndeliver,
  onPayOrder,
  onUnpayOrder,
  onPayInstallment,
  onUnpayInstallment,
  onViewDetails,
  onSendWhatsApp,
}: ClientOrderCardProps) {
  const isDeliveryProcessing = processingKey === `order:${order.uuid}`
  const isPayProcessing = processingKey === `order-pay:${order.uuid}`
  const isCancelled = Boolean(order.cancelled_at)

  return (
    <Paper variant="outlined" sx={{ p: 2.1, ...ELEVATED_SURFACE_SX }}>
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={{ xs: 0.5, sm: 1.25 }}
        sx={{ justifyContent: 'space-between', alignItems: { xs: 'flex-start', sm: 'center' }, mb: 1.5 }}
      >
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, color: 'var(--mk-text)' }}>{formatDateBR(order.created_at)}</Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
            {order.is_installment ? `Parcelado em ${order.installments?.length ?? 0}x` : 'Pagamento à vista'}
          </Typography>
        </Box>
        <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 18, color: 'var(--mk-text)' }}>{formatCurrency(order.total_amount)}</Typography>
      </Stack>

      {isCancelled ? (
        <Alert severity="warning" variant="outlined" sx={{ mb: 1.5 }}>
          Pedido cancelado{order.cancellation_reason ? `: ${order.cancellation_reason}` : '.'}
        </Alert>
      ) : (
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          spacing={{ xs: 0.5, sm: 1.25 }}
          sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: { xs: 0.5, sm: 1.25 }, mb: 1.5 }}
        >
          <OrderStatusAction
            isDone={order.is_delivered}
            isProcessing={isDeliveryProcessing}
            doneLabel="Entregue"
            doneIcon={<CheckCircleOutlineIcon />}
            actionLabel="Marcar como entregue"
            actionIcon={<LocalShippingOutlinedIcon />}
            onMark={onDeliver}
            onUndo={onUndeliver}
          />

          {!order.is_installment && (
            <OrderStatusAction
              isDone={order.is_paid}
              isProcessing={isPayProcessing}
              doneLabel="Pago"
              doneIcon={<CheckCircleOutlineIcon />}
              actionLabel="Marcar como pago"
              actionIcon={<PaidOutlinedIcon />}
              onMark={onPayOrder}
              onUndo={onUnpayOrder}
            />
          )}
        </Stack>
      )}

      {order.is_installment && order.installments && order.installments.length > 0 && (
        <>
          <Divider sx={{ mb: 1.5, borderColor: 'var(--mk-border)' }} />
          <Stack spacing={1} sx={{ mb: 1.5 }}>
            {order.installments.map((installment) => {
              const isInstallmentProcessing = processingKey === `installment:${installment.uuid}`
              return (
                <Stack
                  key={installment.uuid}
                  direction={{ xs: 'column', sm: 'row' }}
                  spacing={{ xs: 0.5, sm: 1.25 }}
                  sx={{
                    alignItems: { xs: 'stretch', sm: 'center' },
                    justifyContent: 'space-between',
                    flexWrap: 'wrap',
                    rowGap: { xs: 0.5, sm: 1.25 },
                  }}
                >
                  <Typography sx={{ fontSize: 13.5, color: 'var(--mk-text)' }}>
                    Parcela {installment.installment_number} — {formatCurrency(installment.amount)} · vence{' '}
                    {formatDateBR(installment.due_date)}
                  </Typography>

                  {!isCancelled && (
                    <OrderStatusAction
                      isDone={installment.is_paid}
                      isProcessing={isInstallmentProcessing}
                      doneLabel="Paga"
                      doneIcon={<CheckCircleOutlineIcon />}
                      actionLabel="Marcar como paga"
                      actionIcon={<PaidOutlinedIcon />}
                      onMark={() => onPayInstallment(installment.uuid)}
                      onUndo={() => onUnpayInstallment(installment.uuid)}
                    />
                  )}
                </Stack>
              )
            })}
          </Stack>
        </>
      )}

      <Divider sx={{ mb: 1.5, borderColor: 'var(--mk-border)' }} />

      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={{ xs: 0.5, sm: 1.25 }}
        sx={{ alignItems: { xs: 'stretch', sm: 'center' }, flexWrap: 'wrap', rowGap: { xs: 0.5, sm: 1.25 } }}
      >
        <Button
          size="small"
          variant="text"
          startIcon={<VisibilityOutlinedIcon fontSize="small" />}
          onClick={onViewDetails}
          fullWidth
          sx={{ minHeight: 44, width: { xs: '100%', sm: 'auto' }, color: 'var(--mk-muted)' }}
        >
          Ver detalhes
        </Button>

        {primaryPhone && (
          <Button
            size="small"
            variant="text"
            color="success"
            startIcon={<WhatsAppIcon fontSize="small" />}
            onClick={() => onSendWhatsApp(primaryPhone)}
            sx={{ minHeight: 44 }}
          >
            Enviar WhatsApp
          </Button>
        )}

        {secondaryPhone && (
          <Tooltip title="Enviar para o telefone secundário" arrow>
            <IconButton
              size="small"
              aria-label="Enviar WhatsApp para o telefone secundário"
              onClick={() => onSendWhatsApp(secondaryPhone)}
              sx={{ minWidth: 44, minHeight: 44, color: 'var(--mk-muted)', '&:hover': { color: '#25D366' } }}
            >
              <WhatsAppIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        )}
      </Stack>
    </Paper>
  )
}

export function ClientOrdersPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const { activeTenantUuid, activeTenant } = useAuth()

  const [client, setClient] = useState<Client | null>(null)
  const [orders, setOrders] = useState<Order[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [processingKey, setProcessingKey] = useState<string | null>(null)
  const [selectedOrderUuid, setSelectedOrderUuid] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    if (!activeTenantUuid || !uuid) return
    setIsLoading(true)
    setLoadError(null)
    try {
      const [clientResult, ordersResult] = await Promise.all([
        clientService.getClient(uuid),
        // Sem `sort_by`: `created_at` não está na whitelist de ordenação do
        // backend (`OrderService::paginate`, só client_name/total_amount/
        // is_installment/is_paid/is_delivered) — sem valor reconhecido ele já
        // cai em `orders.id` (auto-incremento), que dá a mesma ordem
        // "mais recente primeiro" com `sort_dir` padrão (`desc`).
        orderService.listOrders({ client_uuid: uuid, per_page: 100 }),
      ])
      setClient(clientResult)
      setOrders(ordersResult.items)
    } catch (error) {
      setLoadError(getApiErrorMessage(error, 'Não foi possível carregar os pedidos deste cliente agora.'))
    } finally {
      setIsLoading(false)
    }
  }, [activeTenantUuid, uuid])

  useEffect(() => {
    void loadData()
  }, [loadData])

  function updateOrderInPlace(updated: Order) {
    setOrders((current) => current.map((order) => (order.uuid === updated.uuid ? updated : order)))
  }

  async function handleDeliver(orderUuid: string) {
    setActionError(null)
    setProcessingKey(`order:${orderUuid}`)
    try {
      updateOrderInPlace(await orderService.deliverOrder(orderUuid))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível marcar este pedido como entregue agora.'))
    } finally {
      setProcessingKey(null)
    }
  }

  async function handleUndeliver(orderUuid: string) {
    setActionError(null)
    setProcessingKey(`order:${orderUuid}`)
    try {
      updateOrderInPlace(await orderService.undeliverOrder(orderUuid))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível desfazer a entrega deste pedido agora.'))
    } finally {
      setProcessingKey(null)
    }
  }

  async function handlePayOrder(orderUuid: string) {
    setActionError(null)
    setProcessingKey(`order-pay:${orderUuid}`)
    try {
      updateOrderInPlace(await orderService.payOrder(orderUuid))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível marcar este pedido como pago agora.'))
    } finally {
      setProcessingKey(null)
    }
  }

  async function handleUnpayOrder(orderUuid: string) {
    setActionError(null)
    setProcessingKey(`order-pay:${orderUuid}`)
    try {
      updateOrderInPlace(await orderService.unpayOrder(orderUuid))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível desfazer o pagamento deste pedido agora.'))
    } finally {
      setProcessingKey(null)
    }
  }

  async function handlePayInstallment(orderUuid: string, installmentUuid: string) {
    setActionError(null)
    setProcessingKey(`installment:${installmentUuid}`)
    try {
      updateOrderInPlace(await orderService.payInstallment(orderUuid, installmentUuid))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível marcar esta parcela como paga agora.'))
    } finally {
      setProcessingKey(null)
    }
  }

  async function handleUnpayInstallment(orderUuid: string, installmentUuid: string) {
    setActionError(null)
    setProcessingKey(`installment:${installmentUuid}`)
    try {
      updateOrderInPlace(await orderService.unpayInstallment(orderUuid, installmentUuid))
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível desfazer o pagamento desta parcela agora.'))
    } finally {
      setProcessingKey(null)
    }
  }

  async function handleSendWhatsApp(order: Order, phone: string) {
    if (!client) return

    // A listagem (`listOrders`) não carrega `items` (evita N+1 no grid) —
    // busca o pedido completo só na hora de montar a mensagem.
    let items = order.items
    if (!items) {
      try {
        items = (await orderService.getOrder(order.uuid)).items
      } catch {
        items = []
      }
    }

    const message = buildOrderSummaryWhatsAppMessage({
      codigo: order.codigo,
      clientName: client.name,
      isDelivered: order.is_delivered,
      deliveredAt: order.delivered_at,
      notes: order.notes,
      items: (items ?? []).map((item) => ({
        name: item.product.name,
        quantity: item.quantity,
        unit: item.product.unit,
        lineTotal: item.line_total,
      })),
      total: order.total_amount,
      paidAmount: order.paid_amount,
      trackingUrl: activeTenant?.send_tracking_link_whatsapp
        ? `${window.location.origin}/rastreio/${order.uuid}`
        : undefined,
    })
    window.open(buildWhatsAppUrl(phone, message), '_blank')
  }

  const primaryPhone = useMemo(
    () => (client && isDigitsOnlyPhone(client.phone_primary) ? client.phone_primary : null),
    [client],
  )
  const secondaryPhone = useMemo(
    () => (client && isDigitsOnlyPhone(client.phone_secondary) ? client.phone_secondary : null),
    [client],
  )

  return (
    <>
      <CrudListPage
        title={client?.name ?? 'Pedidos do cliente'}
        subtitle="Histórico de pedidos e ações de entrega, pagamento e cobrança por WhatsApp."
        breadcrumbs={[{ label: 'Clientes', to: '/clientes' }, { label: client?.name ?? 'Pedidos' }]}
        toolbar={
          client ? (
            <Paper variant="outlined" sx={{ p: 1.5, ...ELEVATED_SURFACE_SX, flex: 1 }}>
              <Stack spacing={0.25}>
                <Typography sx={{ fontSize: 13.5, color: 'var(--mk-text)' }}>
                  {client.phone_primary?.trim() ? client.phone_primary : 'Sem telefone principal'}
                  {client.phone_secondary?.trim() ? ` · ${client.phone_secondary}` : ''}
                </Typography>
                <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>{clientAddressSummary(client.endereco)}</Typography>
              </Stack>
            </Paper>
          ) : undefined
        }
        error={loadError}
        onRetry={() => void loadData()}
        isLoading={isLoading}
        isEmpty={!isLoading && !loadError && orders.length === 0}
        emptyIcon={<ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />}
        emptyTitle="Nenhum pedido para este cliente"
        emptyDescription="Os pedidos criados para este cliente aparecerão aqui."
      >
        <Stack spacing={2}>
          {actionError && (
            <Alert severity="error" variant="outlined" onClose={() => setActionError(null)}>
              {actionError}
            </Alert>
          )}

          <Stack spacing={1.5}>
            {orders.map((order) => (
              <ClientOrderCard
                key={order.uuid}
                order={order}
                primaryPhone={primaryPhone}
                secondaryPhone={secondaryPhone}
                processingKey={processingKey}
                onDeliver={() => void handleDeliver(order.uuid)}
                onUndeliver={() => void handleUndeliver(order.uuid)}
                onPayOrder={() => void handlePayOrder(order.uuid)}
                onUnpayOrder={() => void handleUnpayOrder(order.uuid)}
                onPayInstallment={(installmentUuid) => void handlePayInstallment(order.uuid, installmentUuid)}
                onUnpayInstallment={(installmentUuid) => void handleUnpayInstallment(order.uuid, installmentUuid)}
                onViewDetails={() => setSelectedOrderUuid(order.uuid)}
                onSendWhatsApp={(phone) => void handleSendWhatsApp(order, phone)}
              />
            ))}
          </Stack>
        </Stack>
      </CrudListPage>

      <OrderDetailDialog
        orderUuid={selectedOrderUuid}
        open={selectedOrderUuid !== null}
        onClose={() => setSelectedOrderUuid(null)}
        onChanged={() => void loadData()}
      />
    </>
  )
}
