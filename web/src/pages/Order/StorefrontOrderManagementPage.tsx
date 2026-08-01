import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import PaymentsOutlinedIcon from '@mui/icons-material/PaymentsOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import { Alert, Box, Button, Chip, IconButton, Stack, Tooltip, Typography } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import type { DragEvent } from 'react'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { StorefrontOrderActionDialog } from '../../components/order/StorefrontOrderActionDialog'
import { WorkflowActionDropZone } from '../../components/workflow/WorkflowActionDropZone'
import { WorkflowBoardColumn } from '../../components/workflow/WorkflowBoardColumn'
import { WorkflowReasonDialog } from '../../components/workflow/WorkflowReasonDialog'
import { WorkflowTimelineDialog } from '../../components/workflow/WorkflowTimelineDialog'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as storefrontOrderService from '../../services/storefrontOrderService'
import * as workflowService from '../../services/workflowService'
import { UI_RADIUS } from '../../styles/layoutStandards'
import type { Order, OrderOperationStage } from '../../types/order'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'

const STAGE_META: Record<OrderOperationStage, { label: string; caption: string; accent: string }> = {
  approval: {
    label: 'Aprovação',
    caption: 'Pedidos aguardando aceite da loja.',
    accent: 'var(--pt-warning)',
  },
  production: {
    label: 'Produção',
    caption: 'Pedidos confirmados que ainda estão em preparo.',
    accent: 'var(--pt-primary)',
  },
  dispatch: {
    label: 'Expedição',
    caption: 'Pedidos que já saíram para entrega.',
    accent: 'var(--pt-info)',
  },
  financial_pending: {
    label: 'Financeiro pendente',
    caption: 'Pedidos entregues aguardando baixa de pagamento.',
    accent: 'var(--pt-danger)',
  },
}

type StorefrontBoardDropTarget = OrderOperationStage | 'cancel' | 'complete'

interface StorefrontBoardAction {
  orderUuid: string
  title: string
  description: string
  confirmLabel: string
  requiresReason: boolean
  execute: (reason: string) => Promise<Order>
}

function deriveStage(order: Order): OrderOperationStage | null {
  if (order.cancelled_at || order.status === 'rejected') return null
  if (order.status === 'pending_approval') return 'approval'
  if (order.is_delivered && !order.is_paid) return 'financial_pending'
  if (order.status === 'confirmed' && order.is_out_for_delivery && !order.is_delivered) return 'dispatch'
  if (order.status === 'confirmed' && !order.is_out_for_delivery && !order.is_delivered) return 'production'
  return null
}

const POLL_PER_PAGE = 50
const REFRESH_INTERVAL_MS = 30000

const BOARD_STAGE_FILTER_OPTIONS: Array<{ value: 'all' | OrderOperationStage; label: string }> = [
  { value: 'all', label: 'Todas as etapas' },
  { value: 'approval', label: 'Aprovação' },
  { value: 'production', label: 'Produção' },
  { value: 'dispatch', label: 'Expedição' },
  { value: 'financial_pending', label: 'Financeiro pendente' },
]

/**
 * Gestão de pedidos vindos do canal público (`/vendas-online`, permissão própria
 * storefront-orders,*). Grid enxuto (código/cliente/ação) no mesmo padrão de
 * /pedidos; toda a gestão fica no modal por pedido (`StorefrontOrderActionDialog`),
 * que mostra sempre 2 botões de ação escolhidos pelo status atual.
 *
 * A listagem usa `active_only=1`: o backend exclui pedidos concluídos/
 * recusados/cancelados e força a ordenação fixa (pendentes primeiro, depois
 * mais antigo). Polling de 30s toca `notificacao.mp3` quando um pedido inédito
 * aparece com a aba visível e recarrega o grid.
 */
export function StorefrontOrderManagementPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { activeTenantUuid } = useAuth()
  const { can } = useAccessControl()
  const gridApiRef = useRef<GridApi | null>(null)
  const canManageCancellation = can(ACCESS.ordersUpdate)
  const stageFromQuery = searchParams.get('stage')
  const sourceFromQuery = searchParams.get('source')
  const isStageFromQueryValid = stageFromQuery === 'approval'
    || stageFromQuery === 'production'
    || stageFromQuery === 'dispatch'
    || stageFromQuery === 'financial_pending'
  const openedFromDashboard = sourceFromQuery === 'dashboard'

  const [selectedOrderUuid, setSelectedOrderUuid] = useState<string | null>(null)
  const [selectedTimelineOrderUuid, setSelectedTimelineOrderUuid] = useState<string | null>(null)
  const [boardOrders, setBoardOrders] = useState<Order[]>([])
  const [boardError, setBoardError] = useState<string | null>(null)
  const [draggedOrderUuid, setDraggedOrderUuid] = useState<string | null>(null)
  const [activeDropTarget, setActiveDropTarget] = useState<StorefrontBoardDropTarget | null>(null)
  const [pendingBoardAction, setPendingBoardAction] = useState<StorefrontBoardAction | null>(null)
  const [isSubmittingBoardAction, setIsSubmittingBoardAction] = useState(false)
  const [boardStageFilter, setBoardStageFilter] = useState<'all' | OrderOperationStage>(isStageFromQueryValid ? stageFromQuery : 'all')
  // Filtro rápido "só pedidos com cancelamento pendente" — aditivo ao
  // active_only já forçado (backend combina os dois filtros com AND).
  const [onlyCancellationRequested, setOnlyCancellationRequested] = useState(false)

  // UUIDs já vistos — populado sem tocar som no primeiro polling; só um UUID
  // inédito num polling posterior dispara o som de pedido novo.
  const seenOrderUuidsRef = useRef<Set<string>>(new Set())
  const seededRef = useRef(false)

  const fetchPage = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<Order>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      // `active_only` ignora sort_by/sort_dir no backend (ordem fixa), então
      // não repassamos os do grid — a coluna de ações não é ordenável mesmo.
      const result = await storefrontOrderService.listStorefrontOrders({
        active_only: true,
        ...(onlyCancellationRequested ? { status: 'cancellation_requested' } : {}),
        page,
        per_page: perPage,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, onlyCancellationRequested],
  )

  const refreshBoard = useCallback(async () => {
    if (!activeTenantUuid) {
      setBoardOrders([])
      return
    }

    try {
      const result = await storefrontOrderService.listStorefrontOrders({ active_only: true, per_page: POLL_PER_PAGE })
      setBoardOrders(result.items)
      setBoardError(null)
      return result.items
    } catch {
      setBoardError('Não foi possível atualizar o board da loja agora.')
      return null
    }
  }, [activeTenantUuid])

  useEffect(() => {
    if (!activeTenantUuid) return
    seenOrderUuidsRef.current = new Set()
    seededRef.current = false

    const poll = async () => {
      try {
        const result = await storefrontOrderService.listStorefrontOrders({ active_only: true, per_page: POLL_PER_PAGE })
        setBoardOrders(result.items)
        setBoardError(null)
        const incomingUuids = result.items.map((item) => item.uuid)
        if (seededRef.current) {
          const hasNew = incomingUuids.some((uuid) => !seenOrderUuidsRef.current.has(uuid))
          if (hasNew) {
            if (document.visibilityState === 'visible') {
              new Audio('/notificacao.mp3').play().catch(() => undefined)
            }
            gridApiRef.current?.refreshInfiniteCache()
          }
        }
        seenOrderUuidsRef.current = new Set(incomingUuids)
        seededRef.current = true
      } catch {
        // Polling silencioso — o grid já trata seu próprio erro de carga.
      }
    }

    void poll()
    const interval = setInterval(() => void poll(), REFRESH_INTERVAL_MS)
    return () => clearInterval(interval)
  }, [activeTenantUuid])

  useEffect(() => {
    void refreshBoard()
  }, [refreshBoard])

  useEffect(() => {
    const nextBoardStage = isStageFromQueryValid ? stageFromQuery : 'all'
    setBoardStageFilter((current) => (current === nextBoardStage ? current : nextBoardStage))
  }, [isStageFromQueryValid, stageFromQuery])

  const columns = useMemo<ServerGridColumn<Order>[]>(
    () => [
      {
        field: 'codigo',
        headerName: 'Código',
        width: 120,
        filterType: 'text',
        cellRenderer: (row) => row.codigo,
        exportValue: (row) => row.codigo,
      },
      {
        field: 'client_name',
        headerName: 'Cliente',
        filterType: 'text',
        cellRenderer: (row) => `${row.final_customer?.name ?? ''} ${row.final_customer?.last_name ?? ''}`.trim(),
        exportValue: (row) => `${row.final_customer?.name ?? ''} ${row.final_customer?.last_name ?? ''}`.trim(),
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 210,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) =>
          row.status === 'cancellation_requested' ? (
            <Chip
              size="small"
              icon={<CancelOutlinedIcon fontSize="small" />}
              label="Cancelamento solicitado"
              color="warning"
              sx={{ fontWeight: 600 }}
            />
          ) : null,
        exportValue: (row) => (row.status === 'cancellation_requested' ? 'Cancelamento solicitado' : ''),
      },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 140,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
            <Tooltip title="Gerenciar pedido" arrow>
              <IconButton
                size="small"
                aria-label={`Gerenciar pedido do cliente ${`${row.final_customer?.name ?? ''} ${row.final_customer?.last_name ?? ''}`.trim()}`}
                onClick={() => setSelectedOrderUuid(row.uuid)}
                sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
              >
                <VisibilityOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title="Histórico operacional" arrow>
              <IconButton
                size="small"
                aria-label={`Ver histórico operacional do pedido ${row.codigo}`}
                onClick={() => setSelectedTimelineOrderUuid(row.uuid)}
                sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
              >
                <HistoryOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </Stack>
        ),
      },
    ],
    [],
  )

  const groupedBoardOrders = useMemo<Record<OrderOperationStage, Order[]>>(
    () => ({
      approval: boardOrders.filter((order) => deriveStage(order) === 'approval'),
      production: boardOrders.filter((order) => deriveStage(order) === 'production'),
      dispatch: boardOrders.filter((order) => deriveStage(order) === 'dispatch'),
      financial_pending: boardOrders.filter((order) => deriveStage(order) === 'financial_pending'),
    }),
    [boardOrders],
  )
  const visibleBoardStages = useMemo(
    () => (boardStageFilter === 'all' ? (['approval', 'production', 'dispatch', 'financial_pending'] as OrderOperationStage[]) : [boardStageFilter]),
    [boardStageFilter],
  )

  const boardOrderMap = useMemo(() => new Map(boardOrders.map((order) => [order.uuid, order])), [boardOrders])
  const draggedBoardOrder = draggedOrderUuid ? (boardOrderMap.get(draggedOrderUuid) ?? null) : null

  const resolveBoardAction = useCallback(
    (order: Order, target: StorefrontBoardDropTarget): StorefrontBoardAction | null => {
      const stage = deriveStage(order)
      if (!stage || target === stage) return null

      if (target === 'production' && stage === 'approval') {
        return {
          orderUuid: order.uuid,
          title: 'Aceitar pedido',
          description: 'O pedido entrará na etapa de produção.',
          confirmLabel: 'Aceitar pedido',
          requiresReason: false,
          execute: () => storefrontOrderService.approveStorefrontOrder(order.uuid),
        }
      }

      if (target === 'dispatch' && stage === 'production') {
        return {
          orderUuid: order.uuid,
          title: 'Enviar para expedição',
          description: 'O pedido será marcado como saiu para entrega.',
          confirmLabel: 'Enviar para expedição',
          requiresReason: false,
          execute: () => storefrontOrderService.dispatchStorefrontOrder(order.uuid),
        }
      }

      if (target === 'production' && stage === 'dispatch') {
        return {
          orderUuid: order.uuid,
          title: 'Voltar para produção',
          description: 'O pedido sairá da expedição e voltará para a etapa de produção.',
          confirmLabel: 'Voltar para produção',
          requiresReason: false,
          execute: () => storefrontOrderService.undispatchStorefrontOrder(order.uuid),
        }
      }

      if (target === 'financial_pending' && stage === 'dispatch') {
        return {
          orderUuid: order.uuid,
          title: 'Marcar como entregue',
          description: 'O pedido será marcado como entregue e seguirá para a baixa financeira.',
          confirmLabel: 'Marcar como entregue',
          requiresReason: false,
          execute: () => storefrontOrderService.deliverStorefrontOrder(order.uuid),
        }
      }

      if (target === 'dispatch' && stage === 'financial_pending') {
        return {
          orderUuid: order.uuid,
          title: 'Voltar para expedição',
          description: 'O pedido deixará de constar como entregue e voltará para a expedição.',
          confirmLabel: 'Voltar para expedição',
          requiresReason: false,
          execute: () => storefrontOrderService.undeliverStorefrontOrder(order.uuid),
        }
      }

      if (target === 'cancel') {
        if (stage === 'approval') {
          return {
            orderUuid: order.uuid,
            title: 'Recusar pedido',
            description: 'Informe o motivo da recusa. O pedido sairá da fila da loja.',
            confirmLabel: 'Recusar pedido',
            requiresReason: true,
            execute: (reason) => storefrontOrderService.rejectStorefrontOrder(order.uuid, reason),
          }
        }

        if (stage === 'production' || stage === 'dispatch') {
          return {
            orderUuid: order.uuid,
            title: 'Cancelar pedido',
            description: 'Informe o motivo do cancelamento. Esse motivo ficará registrado no histórico operacional.',
            confirmLabel: 'Cancelar pedido',
            requiresReason: true,
            execute: (reason) => storefrontOrderService.cancelStorefrontOrder(order.uuid, reason),
          }
        }
      }

      if (target === 'complete' && stage === 'financial_pending') {
        return {
          orderUuid: order.uuid,
          title: 'Concluir pedido',
          description: 'O pedido será marcado como pago e sairá da fila da loja.',
          confirmLabel: 'Concluir pedido',
          requiresReason: false,
          execute: () => storefrontOrderService.payStorefrontOrder(order.uuid),
        }
      }

      return null
    },
    [],
  )

  const runBoardAction = useCallback(
    async (action: StorefrontBoardAction, reason = '') => {
      setIsSubmittingBoardAction(true)
      setBoardError(null)
      try {
        await action.execute(reason)
        await refreshBoard()
        gridApiRef.current?.refreshInfiniteCache()
      } catch (error) {
        setBoardError(getApiErrorMessage(error, 'Não foi possível mover o pedido agora.'))
      } finally {
        setIsSubmittingBoardAction(false)
        setPendingBoardAction(null)
        setDraggedOrderUuid(null)
        setActiveDropTarget(null)
      }
    },
    [refreshBoard],
  )

  const onDragStart = useCallback((orderUuid: string, event: DragEvent<HTMLDivElement>) => {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', orderUuid)
    setDraggedOrderUuid(orderUuid)
    setActiveDropTarget(null)
    setBoardError(null)
  }, [])

  const onDragEnd = useCallback(() => {
    setDraggedOrderUuid(null)
    setActiveDropTarget(null)
  }, [])

  const onDragOverTarget = useCallback(
    (event: DragEvent<HTMLDivElement>, target: StorefrontBoardDropTarget) => {
      event.preventDefault()
      if (!draggedBoardOrder) return
      if (!resolveBoardAction(draggedBoardOrder, target)) return
      setActiveDropTarget(target)
      event.dataTransfer.dropEffect = 'move'
    },
    [draggedBoardOrder, resolveBoardAction],
  )

  const onDragLeaveTarget = useCallback(() => {
    setActiveDropTarget(null)
  }, [])

  const onDropTarget = useCallback(
    async (event: DragEvent<HTMLDivElement>, target: StorefrontBoardDropTarget) => {
      event.preventDefault()
      const orderUuid = event.dataTransfer.getData('text/plain') || draggedOrderUuid
      const order = orderUuid ? boardOrderMap.get(orderUuid) : null
      if (!order) {
        setDraggedOrderUuid(null)
        setActiveDropTarget(null)
        return
      }

      const action = resolveBoardAction(order, target)
      if (!action) {
        setDraggedOrderUuid(null)
        setActiveDropTarget(null)
        return
      }

      if (action.requiresReason) {
        setPendingBoardAction(action)
        return
      }

      await runBoardAction(action)
    },
    [boardOrderMap, draggedOrderUuid, resolveBoardAction, runBoardAction],
  )

  const boardToolbar = (
    <Stack spacing={1.25}>
      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: {
            xs: '1fr',
            xl: boardStageFilter === 'all' ? 'repeat(4, minmax(0, 1fr)) 280px' : 'minmax(0, 1fr) 280px',
          },
          gap: 1.25,
          alignItems: 'start',
        }}
      >
        {visibleBoardStages.map((stage) => (
          <WorkflowBoardColumn
            key={stage}
            title={STAGE_META[stage].label}
            caption={STAGE_META[stage].caption}
            accent={STAGE_META[stage].accent}
            countLabel={`${groupedBoardOrders[stage].length} pedido(s)`}
            isActiveDrop={activeDropTarget === stage}
            onDragOver={(event) => onDragOverTarget(event, stage)}
            onDragLeave={onDragLeaveTarget}
            onDrop={(event) => void onDropTarget(event, stage)}
            emptyMessage="Arraste para esta coluna quando a transição for permitida."
          >
            {groupedBoardOrders[stage].length === 0 ? (
              <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhum pedido nesta etapa agora.</Typography>
            ) : (
              groupedBoardOrders[stage].map((order) => (
                <Box
                  key={order.uuid}
                  draggable
                  onDragStart={(event) => onDragStart(order.uuid, event)}
                  onDragEnd={onDragEnd}
                  sx={{
                    p: 1.25,
                    borderRadius: '16px',
                    border: '1px solid var(--pt-border)',
                    bgcolor: 'color-mix(in srgb, var(--pt-surface) 94%, white)',
                    opacity: draggedOrderUuid === order.uuid ? 0.46 : 1,
                    cursor: 'grab',
                    transition: 'opacity 140ms ease',
                  }}
                >
                  <Stack spacing={0.9}>
                    <Stack direction="row" spacing={1} sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1 }}>
                      <Box sx={{ minWidth: 0 }}>
                        <Typography sx={{ fontSize: 13.5, fontWeight: 700 }}>{order.codigo}</Typography>
                        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }} noWrap>
                          {order.final_customer
                            ? `${order.final_customer.name} ${order.final_customer.last_name ?? ''}`.trim()
                            : 'Cliente não identificado'}
                        </Typography>
                      </Box>
                      {order.status === 'cancellation_requested' ? (
                        <Chip size="small" color="warning" label="Cancelamento solicitado" sx={{ fontWeight: 700 }} />
                      ) : null}
                    </Stack>
                    <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 0.75, alignItems: 'center' }}>
                      <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{formatCurrency(order.total_amount)}</Typography>
                      <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{formatDateTimeBR(order.created_at)}</Typography>
                    </Stack>
                    <Stack direction="row" spacing={1}>
                      <Button size="small" variant="text" onClick={() => setSelectedOrderUuid(order.uuid)} sx={{ px: 0 }}>
                        Gerenciar
                      </Button>
                      <Button size="small" variant="text" onClick={() => setSelectedTimelineOrderUuid(order.uuid)} sx={{ px: 0 }}>
                        Histórico
                      </Button>
                    </Stack>
                  </Stack>
                </Box>
              ))
            )}
          </WorkflowBoardColumn>
        ))}

        <Stack spacing={1.25}>
          <Box
            sx={{
              p: 1.35,
              borderRadius: '16px',
              border: '1px solid var(--pt-border)',
              bgcolor: 'color-mix(in srgb, var(--pt-surface) 96%, white)',
            }}
          >
            <Stack spacing={1}>
              <Typography sx={{ fontSize: 14, fontWeight: 800 }}>Foco do board</Typography>
              <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 1 }}>
                {BOARD_STAGE_FILTER_OPTIONS.map((option) => (
                  <Chip
                    key={option.value}
                    clickable
                    label={option.label}
                    color={boardStageFilter === option.value ? 'primary' : 'default'}
                    variant={boardStageFilter === option.value ? 'filled' : 'outlined'}
                    onClick={() => {
                      setBoardStageFilter(option.value)
                      setSearchParams((current) => {
                        const next = new URLSearchParams(current)
                        if (option.value === 'all') {
                          next.delete('stage')
                        } else {
                          next.set('stage', option.value)
                        }
                        return next
                      }, { replace: true })
                    }}
                  />
                ))}
              </Stack>
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                Esse foco atua no board da loja e pode ser aberto diretamente pela URL.
              </Typography>
            </Stack>
          </Box>
          <WorkflowActionDropZone
            title="Cancelar / recusar"
            description="Use esta área para recusar pedidos em aprovação ou cancelar pedidos que já estão em operação. O motivo é obrigatório."
            accent="var(--pt-danger)"
            icon={<CancelOutlinedIcon fontSize="small" />}
            isActiveDrop={activeDropTarget === 'cancel'}
            isDisabled={!draggedBoardOrder || !resolveBoardAction(draggedBoardOrder, 'cancel')}
            onDragOver={(event) => onDragOverTarget(event, 'cancel')}
            onDragLeave={onDragLeaveTarget}
            onDrop={(event) => void onDropTarget(event, 'cancel')}
          />
          <WorkflowActionDropZone
            title="Concluir pedido"
            description="Solte aqui os pedidos entregues para registrar a baixa do pagamento e encerrar a fila."
            accent="var(--pt-success)"
            icon={<PaymentsOutlinedIcon fontSize="small" />}
            isActiveDrop={activeDropTarget === 'complete'}
            isDisabled={!draggedBoardOrder || !resolveBoardAction(draggedBoardOrder, 'complete')}
            onDragOver={(event) => onDragOverTarget(event, 'complete')}
            onDragLeave={onDragLeaveTarget}
            onDrop={(event) => void onDropTarget(event, 'complete')}
          />
          <Box
            sx={{
              p: 1.35,
              borderRadius: '16px',
              border: '1px solid var(--pt-border)',
              bgcolor: 'color-mix(in srgb, var(--pt-surface) 96%, white)',
            }}
          >
            <Stack spacing={0.8}>
              <Typography sx={{ fontSize: 14, fontWeight: 800 }}>Board da loja</Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                Arraste os pedidos entre as etapas para acelerar a operação. O modal continua disponível quando você precisar
                revisar itens, endereço, QR de preparo e detalhes do pedido.
              </Typography>
            </Stack>
          </Box>
        </Stack>
      </Box>

      {boardError ? (
        <Alert severity="warning" variant="outlined">
          {boardError}
        </Alert>
      ) : null}
    </Stack>
  )

  return (
    <>
      <CrudListPage
        title="Pedidos da loja"
        subtitle="Acompanhe e gerencie os pedidos recebidos pela loja."
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
        toolbar={
          <Stack spacing={1.25}>
            {boardToolbar}
            {canManageCancellation ? (
              <Chip
                clickable
                icon={<CancelOutlinedIcon fontSize="small" />}
                label="Só cancelamento pendente"
                color={onlyCancellationRequested ? 'warning' : 'default'}
                variant={onlyCancellationRequested ? 'filled' : 'outlined'}
                onClick={() => setOnlyCancellationRequested((current) => !current)}
                sx={{ fontWeight: 600, height: 32, width: 'fit-content', borderRadius: UI_RADIUS.pill }}
              />
            ) : null}
          </Stack>
        }
      >
        {openedFromDashboard && isStageFromQueryValid ? (
          <Alert severity="info" variant="outlined" sx={{ mb: 2 }}>
            Esta fila foi aberta a partir do dashboard com foco na etapa{' '}
            <strong>{BOARD_STAGE_FILTER_OPTIONS.find((option) => option.value === stageFromQuery)?.label ?? stageFromQuery}</strong>.
          </Alert>
        ) : null}

        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 900 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="pedidos-loja"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={
                onlyCancellationRequested
                  ? {
                      icon: <CancelOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                      title: 'Nenhum cancelamento pendente no momento',
                      description: 'Solicitações de cancelamento feitas pelos clientes aparecerão aqui.',
                    }
                  : {
                      icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                      title: 'Nenhum pedido pendente de ação no momento',
                      description: 'Pedidos concluídos, recusados ou cancelados não aparecem aqui.',
                    }
              }
            />
          </Box>
        </Box>
      </CrudListPage>

      <StorefrontOrderActionDialog
        orderUuid={selectedOrderUuid}
        canManageCancellation={canManageCancellation}
        open={selectedOrderUuid !== null}
        onClose={() => setSelectedOrderUuid(null)}
        onChanged={() => {
          gridApiRef.current?.refreshInfiniteCache()
          void refreshBoard()
        }}
      />

      <WorkflowReasonDialog
        open={pendingBoardAction !== null}
        title={pendingBoardAction?.title ?? 'Registrar motivo'}
        description={pendingBoardAction?.description ?? ''}
        confirmLabel={pendingBoardAction?.confirmLabel ?? 'Confirmar'}
        loading={isSubmittingBoardAction}
        onClose={() => {
          if (isSubmittingBoardAction) return
          setPendingBoardAction(null)
          setDraggedOrderUuid(null)
          setActiveDropTarget(null)
        }}
        onConfirm={(reason) => {
          if (!pendingBoardAction) return
          void runBoardAction(pendingBoardAction, reason)
        }}
      />

      <WorkflowTimelineDialog
        open={selectedTimelineOrderUuid !== null}
        title="Histórico operacional do pedido"
        subjectLabel={selectedTimelineOrderUuid ? `pedido ${selectedTimelineOrderUuid}` : 'pedido'}
        loader={() =>
          selectedTimelineOrderUuid
            ? workflowService.getStorefrontOrderWorkflowTimeline(selectedTimelineOrderUuid)
            : Promise.resolve([])
        }
        stageLabel={(stage) => {
          if (!stage) return 'Sem etapa'
          return STAGE_META[stage as OrderOperationStage]?.label ?? stage
        }}
        onClose={() => setSelectedTimelineOrderUuid(null)}
      />
    </>
  )
}
