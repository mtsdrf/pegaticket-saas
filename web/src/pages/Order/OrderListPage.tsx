import AddIcon from '@mui/icons-material/Add'
import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import CheckCircleOutlineOutlinedIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import FilterAltOutlinedIcon from '@mui/icons-material/FilterAltOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import LanguageOutlinedIcon from '@mui/icons-material/LanguageOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import PaymentsOutlinedIcon from '@mui/icons-material/PaymentsOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import { Alert, Box, Button, Chip, IconButton, Stack, Typography, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import type { DragEvent } from 'react'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link as RouterLink, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { OrderDetailDialog } from '../../components/order/OrderDetailDialog'
import { WorkflowActionDropZone } from '../../components/workflow/WorkflowActionDropZone'
import { WorkflowBoardColumn } from '../../components/workflow/WorkflowBoardColumn'
import { WorkflowReasonDialog } from '../../components/workflow/WorkflowReasonDialog'
import { WorkflowTimelineDialog } from '../../components/workflow/WorkflowTimelineDialog'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as orderService from '../../services/orderService'
import * as storefrontOrderService from '../../services/storefrontOrderService'
import * as workflowService from '../../services/workflowService'
import { getApiErrorMessage } from '../../types/api'
import type { Order } from '../../types/order'
import type { OrderOperationStage, OrderOrigin, OrderStatus } from '../../types/order'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'
import { deriveOrderStatus, STATUS_TONE_COLORS } from '../../utils/orderStatus'

const ORIGIN_FILTERS: Array<{ value: 'all' | OrderOrigin; label: string }> = [
  { value: 'all', label: 'Todos os canais' },
  { value: 'staff', label: 'Manual' },
  { value: 'storefront', label: 'Online' },
  { value: 'ifood', label: 'iFood' },
]

const ORIGIN_META: Record<OrderOrigin, { label: string; shortLabel: string }> = {
  staff: { label: 'Pedido manual', shortLabel: 'Manual' },
  storefront: { label: 'Bilheteria online', shortLabel: 'Online' },
  ifood: { label: 'iFood importado', shortLabel: 'iFood' },
}

interface OperationSnapshot {
  activeTotal: number | null
  storefrontPendingApproval: number | null
  productionTotal: number | null
  dispatchTotal: number | null
  financialPendingTotal: number | null
  byOrigin: Partial<Record<OrderOrigin, number>>
}

type OperationQueuePreview = Record<OrderOperationStage, Order[]>

interface QuickQueueAction {
  label: string
  icon: React.ReactNode
  run: (order: Order) => Promise<Order>
}

type OperationPriority = 'normal' | 'attention' | 'urgent' | 'critical'
type OperationOwner = 'store' | 'operations' | 'delivery' | 'finance'

type OrderBoardDropTarget = OrderOperationStage | 'cancel' | 'complete'

interface ResolvedOrderBoardAction {
  orderUuid?: string
  title: string
  description: string
  confirmLabel: string
  requiresReason: boolean
  execute: (reason: string) => Promise<Order>
}

const STAGE_FILTERS: Array<{ value: 'all' | OrderOperationStage; label: string }> = [
  { value: 'all', label: 'Todas as filas' },
  { value: 'approval', label: 'Aprovação' },
  { value: 'production', label: 'Produção' },
  { value: 'dispatch', label: 'Expedição' },
  { value: 'financial_pending', label: 'Financeiro pendente' },
]

const STAGE_META: Record<OrderOperationStage, { label: string; caption: string; accent: string }> = {
  approval: {
    label: 'Aprovação',
    caption: 'Pedidos aguardando aceite antes de entrar no fluxo operacional.',
    accent: 'var(--pt-warning)',
  },
  production: {
    label: 'Produção',
    caption: 'Pedidos confirmados que ainda precisam ser preparados ou separados.',
    accent: 'var(--pt-primary)',
  },
  dispatch: {
    label: 'Expedição',
    caption: 'Pedidos já despachados e ainda não concluídos.',
    accent: 'var(--pt-info)',
  },
  financial_pending: {
    label: 'Financeiro pendente',
    caption: 'Pedidos entregues com cobrança ainda em aberto.',
    accent: 'var(--pt-danger)',
  },
}

const PRIORITY_META: Record<OperationPriority, { label: string; accent: string; bg: string; sortWeight: number }> = {
  normal: {
    label: 'Dentro do ritmo',
    accent: 'var(--pt-success)',
    bg: 'color-mix(in srgb, var(--pt-success) 12%, transparent)',
    sortWeight: 0,
  },
  attention: {
    label: 'Pedir atenção',
    accent: 'var(--pt-info)',
    bg: 'color-mix(in srgb, var(--pt-info) 12%, transparent)',
    sortWeight: 1,
  },
  urgent: {
    label: 'Prioridade alta',
    accent: 'var(--pt-warning)',
    bg: 'color-mix(in srgb, var(--pt-warning) 14%, transparent)',
    sortWeight: 2,
  },
  critical: {
    label: 'Ação imediata',
    accent: 'var(--pt-danger)',
    bg: 'color-mix(in srgb, var(--pt-danger) 14%, transparent)',
    sortWeight: 3,
  },
}

function deriveOperationStage(order: Order): OrderOperationStage | null {
  if (order.cancelled_at || order.status === 'rejected') return null
  if (order.status === 'pending_approval') return 'approval'
  if (order.is_delivered && !order.is_paid) return 'financial_pending'
  if (order.status === 'confirmed' && order.is_out_for_delivery && !order.is_delivered) return 'dispatch'
  if (order.status === 'confirmed' && !order.is_out_for_delivery && !order.is_delivered) return 'production'
  return null
}

function minutesSince(isoDate: string): number {
  const createdAt = new Date(isoDate).getTime()
  if (Number.isNaN(createdAt)) return 0
  return Math.max(0, Math.round((Date.now() - createdAt) / 60000))
}

function formatElapsedLabel(totalMinutes: number): string {
  if (totalMinutes < 60) return `Há ${totalMinutes} min`
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60
  if (hours < 24) return minutes > 0 ? `Há ${hours}h${String(minutes).padStart(2, '0')}` : `Há ${hours}h`
  const days = Math.floor(hours / 24)
  const remainingHours = hours % 24
  return remainingHours > 0 ? `Há ${days}d ${remainingHours}h` : `Há ${days}d`
}

function deriveOperationPriority(order: Order): OperationPriority {
  const stage = deriveOperationStage(order)
  const ageInMinutes = minutesSince(order.created_at)

  if (stage === 'approval') {
    if (ageInMinutes >= 15) return 'critical'
    if (ageInMinutes >= 7) return 'urgent'
    if (ageInMinutes >= 3) return 'attention'
    return 'normal'
  }

  if (stage === 'production') {
    if (ageInMinutes >= 45) return 'critical'
    if (ageInMinutes >= 25) return 'urgent'
    if (ageInMinutes >= 12) return 'attention'
    return 'normal'
  }

  if (stage === 'dispatch') {
    if (ageInMinutes >= 35) return 'critical'
    if (ageInMinutes >= 20) return 'urgent'
    if (ageInMinutes >= 10) return 'attention'
    return 'normal'
  }

  if (stage === 'financial_pending') {
    if (ageInMinutes >= 1440) return 'critical'
    if (ageInMinutes >= 480) return 'urgent'
    if (ageInMinutes >= 180) return 'attention'
    return 'normal'
  }

  return 'normal'
}

function deriveOperationOwner(order: Order): OperationOwner | null {
  const stage = deriveOperationStage(order)
  if (stage === 'approval') return 'store'
  if (stage === 'production') return 'operations'
  if (stage === 'dispatch') return 'delivery'
  if (stage === 'financial_pending') return 'finance'
  return null
}

function ownerMeta(owner: OperationOwner): { label: string; caption: string; accent: string; to?: string } {
  if (owner === 'store') {
    return {
      label: 'Atendimento da loja',
      caption: 'Confirma e libera pedidos recebidos do canal online.',
      accent: 'var(--pt-warning)',
      to: '/vendas-online',
    }
  }
  if (owner === 'operations') {
    return {
      label: 'Operação interna',
      caption: 'Produz, separa e prepara o pedido para sair.',
      accent: 'var(--pt-primary)',
    }
  }
  if (owner === 'delivery') {
    return {
      label: 'Entrega e rotas',
      caption: 'Organiza deslocamento, rota e confirmação de entrega.',
      accent: 'var(--pt-info)',
    }
  }
  return {
    label: 'Financeiro',
    caption: 'Finaliza recebimento e baixa pendências de cobrança.',
    accent: 'var(--pt-danger)',
    to: '/financeiro/conciliacao',
  }
}

function OrderStatusBadge({ order }: { order: Order }) {
  const derived = deriveOrderStatus({
    is_cancelled: Boolean(order.cancelled_at),
    is_paid: order.is_paid,
    is_delivered: order.is_delivered,
    is_installment: order.is_installment,
    delivered_at: order.delivered_at,
    paid_at: order.paid_at,
    status: order.status,
    is_out_for_delivery: order.is_out_for_delivery,
  })
  const colors = STATUS_TONE_COLORS[derived.tone]

  return (
    <Stack spacing={0.35}>
      <Box
        sx={{
          display: 'inline-flex',
          alignItems: 'center',
          gap: 0.75,
          color: colors.fg,
          fontSize: 12.5,
          fontWeight: 700,
          width: 'fit-content',
          maxWidth: '100%',
        }}
      >
        <Box sx={{ display: 'inline-flex', alignItems: 'center', '& svg': { fontSize: 16 } }}>{derived.icon}</Box>
        <Box sx={{ minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{derived.label}</Box>
      </Box>
      <Typography sx={{ fontSize: 11.5, color: 'var(--pt-muted)', lineHeight: 1.25 }}>
        {derived.caption}
      </Typography>
    </Stack>
  )
}

const ORDER_STAGE_CHIP_SX = {
  fontWeight: 700,
  width: 'fit-content',
  maxWidth: '100%',
  minHeight: 30,
  alignSelf: 'flex-start',
  '& .MuiChip-label': {
    display: 'block',
    overflow: 'visible',
    textOverflow: 'clip',
    whiteSpace: 'nowrap',
    lineHeight: 1.25,
    paddingTop: '4px',
    paddingBottom: '4px',
  },
} as const

function OperationMetricCard({
  label,
  value,
  caption,
  accent = 'var(--pt-primary)',
  action,
}: {
  label: string
  value: string
  caption: string
  accent?: string
  action?: React.ReactNode
}) {
  return (
    <Box
      sx={{
        p: 2,
        borderRadius: '20px',
        border: '1px solid var(--pt-border)',
        background: 'var(--pt-surface)',
        minHeight: 150,
      }}
    >
      <Stack spacing={1.1} sx={{ height: '100%' }}>
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', textTransform: 'uppercase', letterSpacing: 0.35 }}>
          {label}
        </Typography>
        <Typography sx={{ fontSize: 28, lineHeight: 1, fontWeight: 800, color: accent }}>{value}</Typography>
        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', flex: 1 }}>{caption}</Typography>
        {action}
      </Stack>
    </Box>
  )
}

function QueueOrderCard({
  order,
  onOpen,
  onOpenTimeline,
  quickAction,
  onQuickAction,
  isSubmitting,
  draggable = false,
  isDragging = false,
  onDragStart,
  onDragEnd,
}: {
  order: Order
  onOpen: (uuid: string) => void
  onOpenTimeline: (uuid: string) => void
  quickAction: QuickQueueAction | null
  onQuickAction: (order: Order) => void
  isSubmitting: boolean
  draggable?: boolean
  isDragging?: boolean
  onDragStart?: (event: DragEvent<HTMLDivElement>) => void
  onDragEnd?: (event: DragEvent<HTMLDivElement>) => void
}) {
  const stage = deriveOperationStage(order)
  const priority = deriveOperationPriority(order)
  const priorityMeta = PRIORITY_META[priority]
  const ageInMinutes = minutesSince(order.created_at)
  const owner = deriveOperationOwner(order)
  const ownerDetails = owner ? ownerMeta(owner) : null

  return (
    <Box
      draggable={draggable}
      onDragStart={onDragStart}
      onDragEnd={onDragEnd}
      sx={{
        p: 1.25,
        borderRadius: '16px',
        border: `1px solid ${priority === 'normal' ? 'var(--pt-border)' : priorityMeta.bg}`,
        bgcolor: 'color-mix(in srgb, var(--pt-surface) 94%, white)',
        boxShadow: priority === 'critical' ? '0 0 0 1px color-mix(in srgb, var(--pt-danger) 14%, transparent)' : 'none',
        opacity: isDragging ? 0.46 : 1,
        cursor: draggable ? 'grab' : 'default',
        transition: 'opacity 140ms ease, transform 140ms ease',
      }}
    >
      <Stack spacing={1}>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'flex-start', justifyContent: 'space-between', gap: 1 }}>
          <Box sx={{ minWidth: 0 }}>
            <Typography sx={{ fontSize: 13.5, fontWeight: 700 }}>{order.codigo}</Typography>
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }} noWrap>
              {order.final_customer?.name ?? 'Cliente não identificado'}
            </Typography>
          </Box>
          <Chip
            size="small"
            label={ORIGIN_META[order.origin]?.shortLabel ?? order.origin}
            sx={{
              fontWeight: 700,
              color: 'var(--pt-primary)',
              bgcolor: 'color-mix(in srgb, var(--pt-primary) 12%, transparent)',
              flexShrink: 0,
            }}
          />
        </Stack>

        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 0.75 }}>
          {stage ? (
            <Chip
              size="small"
              label={STAGE_META[stage].label}
              sx={{
                color: STAGE_META[stage].accent,
                bgcolor: `color-mix(in srgb, ${STAGE_META[stage].accent} 12%, transparent)`,
                ...ORDER_STAGE_CHIP_SX,
              }}
            />
          ) : null}
          <Chip
            size="small"
            label={priorityMeta.label}
            sx={{
              fontWeight: 700,
              color: priorityMeta.accent,
              bgcolor: priorityMeta.bg,
            }}
          />
          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{formatCurrency(order.total_amount)}</Typography>
          <Typography sx={{ fontSize: 12.5, color: priorityMeta.accent, fontWeight: 700 }}>
            {formatElapsedLabel(ageInMinutes)}
          </Typography>
          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{formatDateTimeBR(order.created_at)}</Typography>
        </Stack>

        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', rowGap: 0.75 }}>
          {ownerDetails ? (
            <Typography sx={{ fontSize: 12.5, color: ownerDetails.accent, fontWeight: 700 }}>
              Responsável sugerido: {ownerDetails.label}
            </Typography>
          ) : null}
          <Button size="small" variant="text" onClick={() => onOpen(order.uuid)} sx={{ alignSelf: 'flex-start', px: 0 }}>
            Abrir pedido
          </Button>
          <Button size="small" variant="text" onClick={() => onOpenTimeline(order.uuid)} sx={{ alignSelf: 'flex-start', px: 0 }}>
            Histórico
          </Button>
          {quickAction ? (
            <Button
              size="small"
              variant="contained"
              startIcon={quickAction.icon}
              onClick={() => onQuickAction(order)}
              disabled={isSubmitting}
              sx={{ minHeight: 34 }}
            >
              {isSubmitting ? 'Processando...' : quickAction.label}
            </Button>
          ) : null}
        </Stack>
      </Stack>
    </Box>
  )
}

/**
 * Primeira versão da central operacional multi-origem do PegaTicket. A rota
 * permanece `/pedidos`, mas a experiência deixa de ser "só pedidos manuais"
 * e passa a reunir o pedido canônico do sistema por origem (`staff`,
 * `storefront`, `ifood` e canais legados ainda presentes em histórico),
 * mantendo `/vendas-online` e `/pedidos-ifood` como filas especializadas
 * complementares.
 */
export function OrderListPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const isManualOrdersPage = location.pathname === '/pedidos-manuais'

  const stageFilterFromQuery = searchParams.get('stage')
  const sourceFromQuery = searchParams.get('source')
  const isStageFilterFromQueryValid = stageFilterFromQuery === 'approval'
    || stageFilterFromQuery === 'production'
    || stageFilterFromQuery === 'dispatch'
    || stageFilterFromQuery === 'financial_pending'
  const openedFromDashboard = sourceFromQuery === 'dashboard'

  const [selectedOrderUuid, setSelectedOrderUuid] = useState<string | null>(null)
  const [selectedTimelineOrderUuid, setSelectedTimelineOrderUuid] = useState<string | null>(null)
  const [originFilter, setOriginFilter] = useState<'all' | OrderOrigin>(isManualOrdersPage ? 'staff' : 'all')
  const [statusFilter, setStatusFilter] = useState<'all' | OrderStatus>('all')
  const [stageFilter, setStageFilter] = useState<'all' | OrderOperationStage>(
    isStageFilterFromQueryValid ? stageFilterFromQuery : 'all',
  )
  const [activeOnly, setActiveOnly] = useState(true)
  const [snapshot, setSnapshot] = useState<OperationSnapshot>({
    activeTotal: null,
    storefrontPendingApproval: null,
    productionTotal: null,
    dispatchTotal: null,
    financialPendingTotal: null,
    byOrigin: {},
  })
  const [snapshotError, setSnapshotError] = useState<string | null>(null)
  const [queuePreview, setQueuePreview] = useState<OperationQueuePreview>({
    approval: [],
    production: [],
    dispatch: [],
    financial_pending: [],
  })
  const [queuePreviewError, setQueuePreviewError] = useState<string | null>(null)
  const [quickActionError, setQuickActionError] = useState<string | null>(null)
  const [draggedOrderUuid, setDraggedOrderUuid] = useState<string | null>(null)
  const [activeOrderDropTarget, setActiveOrderDropTarget] = useState<OrderBoardDropTarget | null>(null)
  const [pendingOrderBoardAction, setPendingOrderBoardAction] = useState<ResolvedOrderBoardAction | null>(null)
  const [boardActionSubmitting, setBoardActionSubmitting] = useState(false)
  const [submittingOrderUuid, setSubmittingOrderUuid] = useState<string | null>(null)

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Order>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await orderService.listOrders({
        ...filters,
        ...(isManualOrdersPage ? { origin: 'staff' as const } : originFilter !== 'all' ? { origin: originFilter } : {}),
        ...(statusFilter !== 'all' ? { status: statusFilter } : {}),
        ...(stageFilter !== 'all' ? { stage: stageFilter } : {}),
        ...(activeOnly && !isManualOrdersPage ? { active_only: true } : {}),
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeOnly, activeTenantUuid, isManualOrdersPage, originFilter, stageFilter, statusFilter],
  )

  const canReadStorefrontQueue = can(ACCESS.storefrontOrdersRead)
  const canReadFinance = can(ACCESS.financeRead)
  const canApproveStorefrontOrder = can(ACCESS.storefrontOrdersApprove)
  const canCancelStorefrontOrder = can(ACCESS.storefrontOrdersCancel)
  const canDispatchStorefrontOrder = can(ACCESS.storefrontOrdersDispatch)
  const canUndispatchStorefrontOrder = can(ACCESS.storefrontOrdersUndispatch)
  const canDeliverStorefrontOrder = can(ACCESS.storefrontOrdersDeliver)
  const canUndeliverStorefrontOrder = can(ACCESS.storefrontOrdersUndeliver)
  const canPayStorefrontOrder = can(ACCESS.storefrontOrdersPay)
  const canUpdateOrders = can(ACCESS.ordersUpdate)

  useEffect(() => {
    const nextStageFilter = isStageFilterFromQueryValid ? stageFilterFromQuery : 'all'

    setStageFilter((current) => {
      if (current === nextStageFilter) {
        return current
      }

      return nextStageFilter
    })

    if (nextStageFilter !== 'all') {
      setStatusFilter('all')
    }
  }, [isStageFilterFromQueryValid, stageFilterFromQuery])

  useEffect(() => {
    if (!isManualOrdersPage) return
    setOriginFilter('staff')
    setStatusFilter('all')
    setStageFilter('all')
    setActiveOnly(false)
  }, [isManualOrdersPage])

  useEffect(() => {
    if (!gridApiRef.current) return

    gridApiRef.current.refreshInfiniteCache()
  }, [stageFilter])

  const queuePrioritySummary = useMemo(() => {
    const orders = Object.values(queuePreview).flat()

    return orders.reduce(
      (accumulator, order) => {
        const priority = deriveOperationPriority(order)
        accumulator.total += 1
        if (priority === 'critical') accumulator.critical += 1
        if (priority === 'urgent') accumulator.urgent += 1
        if (priority === 'attention') accumulator.attention += 1
        return accumulator
      },
      { total: 0, critical: 0, urgent: 0, attention: 0 },
    )
  }, [queuePreview])

  const ownerSummary = useMemo(() => {
    const base = {
      store: 0,
      operations: 0,
      delivery: 0,
      finance: 0,
    } satisfies Record<OperationOwner, number>

    for (const order of Object.values(queuePreview).flat()) {
      const owner = deriveOperationOwner(order)
      if (owner) {
        base[owner] += 1
      }
    }

    return base
  }, [queuePreview])

  const previewOrderMap = useMemo(() => {
    return new Map(Object.values(queuePreview).flat().map((order) => [order.uuid, order]))
  }, [queuePreview])

  const draggedPreviewOrder = draggedOrderUuid ? (previewOrderMap.get(draggedOrderUuid) ?? null) : null

  const refreshOperationData = useCallback(async () => {
    if (!activeTenantUuid) return

    setSnapshotError(null)
    setQueuePreviewError(null)

    const activeTotalPromise = orderService.listOrders({ active_only: true, per_page: 1 })
    const approvalPromise = orderService.listOrders({ stage: 'approval', active_only: true, per_page: 5 })
    const productionPromise = orderService.listOrders({ stage: 'production', active_only: true, per_page: 5 })
    const dispatchPromise = orderService.listOrders({ stage: 'dispatch', active_only: true, per_page: 5 })
    const financialPendingPromise = orderService.listOrders({ stage: 'financial_pending', active_only: true, per_page: 5 })
    const originPromises = (['staff', 'storefront', 'ifood'] as OrderOrigin[]).map(async (origin) => {
      const page = await orderService.listOrders({ origin, active_only: true, per_page: 1 })
      return [origin, page.pagination.total] as const
    })

    const [activeTotalPage, approvalPage, productionPage, dispatchPage, financialPendingPage, originCounts] = await Promise.all([
      activeTotalPromise,
      approvalPromise,
      productionPromise,
      dispatchPromise,
      financialPendingPromise,
      Promise.all(originPromises),
    ])

    setSnapshot({
      activeTotal: activeTotalPage.pagination.total,
      storefrontPendingApproval: approvalPage.pagination.total,
      productionTotal: productionPage.pagination.total,
      dispatchTotal: dispatchPage.pagination.total,
      financialPendingTotal: financialPendingPage.pagination.total,
      byOrigin: Object.fromEntries(originCounts),
    })
    setQueuePreview({
      approval: approvalPage.items,
      production: productionPage.items,
      dispatch: dispatchPage.items,
      financial_pending: financialPendingPage.items,
    })
  }, [activeTenantUuid])

  useEffect(() => {
    if (isManualOrdersPage) return
    if (!activeTenantUuid) return

    let cancelled = false

    async function loadSnapshot() {
      try {
        if (cancelled) return
        await refreshOperationData()
      } catch {
        if (!cancelled) {
          setSnapshotError('Não foi possível atualizar o resumo operacional agora.')
          setQueuePreviewError('Não foi possível carregar as filas em foco agora.')
        }
      }
    }

    void loadSnapshot()

    return () => {
      cancelled = true
    }
  }, [activeTenantUuid, isManualOrdersPage, refreshOperationData])

  const resolveQuickAction = useCallback(
    (order: Order): QuickQueueAction | null => {
      const stage = deriveOperationStage(order)
      if (!stage || order.cancelled_at || order.status === 'rejected') return null

      if (stage === 'approval') {
        if (order.origin === 'storefront') {
          if (!canApproveStorefrontOrder) return null
          return {
            label: 'Aceitar pedido',
            icon: <CheckCircleOutlineOutlinedIcon />,
            run: (currentOrder) => storefrontOrderService.approveStorefrontOrder(currentOrder.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          label: 'Aprovar pedido',
          icon: <CheckCircleOutlineOutlinedIcon />,
          run: (currentOrder) => orderService.approveOrder(currentOrder.uuid),
        }
      }

      if (stage === 'production') {
        if (order.origin === 'storefront') {
          if (!canDispatchStorefrontOrder) return null
          return {
            label: 'Saiu para entrega',
            icon: <LocalShippingOutlinedIcon />,
            run: (currentOrder) => storefrontOrderService.dispatchStorefrontOrder(currentOrder.uuid),
          }
        }

        return null
      }

      if (stage === 'dispatch') {
        if (order.origin === 'storefront') {
          if (!canDeliverStorefrontOrder) return null
          return {
            label: 'Marcar entregue',
            icon: <CheckCircleOutlineOutlinedIcon />,
            run: (currentOrder) => storefrontOrderService.deliverStorefrontOrder(currentOrder.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          label: 'Marcar entregue',
          icon: <CheckCircleOutlineOutlinedIcon />,
          run: (currentOrder) => orderService.deliverOrder(currentOrder.uuid),
        }
      }

      if (stage === 'financial_pending') {
        if (order.origin === 'storefront') {
          if (!canPayStorefrontOrder) return null
          return {
            label: 'Concluir pedido',
            icon: <PaymentsOutlinedIcon />,
            run: (currentOrder) => storefrontOrderService.payStorefrontOrder(currentOrder.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          label: 'Marcar pago',
          icon: <PaymentsOutlinedIcon />,
          run: (currentOrder) => orderService.payOrder(currentOrder.uuid),
        }
      }

      return null
    },
    [
      canApproveStorefrontOrder,
      canDeliverStorefrontOrder,
      canDispatchStorefrontOrder,
      canPayStorefrontOrder,
      canUpdateOrders,
    ],
  )

  const handleQuickAction = useCallback(
    async (order: Order) => {
      const action = resolveQuickAction(order)
      if (!action) return

      setQuickActionError(null)
      setSubmittingOrderUuid(order.uuid)

      try {
        await action.run(order)
        await refreshOperationData()
        gridApiRef.current?.refreshInfiniteCache()
      } catch (error) {
        setQuickActionError(getApiErrorMessage(error, 'Não foi possível concluir esta ação agora.'))
      } finally {
        setSubmittingOrderUuid(null)
      }
    },
    [refreshOperationData, resolveQuickAction],
  )

  const resolveOrderBoardAction = useCallback(
    (order: Order, target: OrderBoardDropTarget): ResolvedOrderBoardAction | null => {
      const currentStage = deriveOperationStage(order)
      if (!currentStage) return null
      if (target === currentStage) return null

      if (target === 'production') {
        if (currentStage === 'approval') {
          if (order.origin === 'storefront') {
            if (!canApproveStorefrontOrder) return null
            return {
              title: 'Aprovar pedido',
              description: 'O pedido entrará na fila de produção.',
              confirmLabel: 'Aprovar pedido',
              requiresReason: false,
              execute: () => storefrontOrderService.approveStorefrontOrder(order.uuid),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Aprovar pedido',
            description: 'O pedido entrará na fila de produção.',
            confirmLabel: 'Aprovar pedido',
            requiresReason: false,
            execute: () => orderService.approveOrder(order.uuid),
          }
        }

        if (currentStage === 'dispatch') {
          if (order.origin === 'storefront') {
            if (!canUndispatchStorefrontOrder) return null
            return {
              title: 'Voltar para produção',
              description: 'O pedido sairá da expedição e voltará para a fila de produção.',
              confirmLabel: 'Voltar para produção',
              requiresReason: false,
              execute: () => storefrontOrderService.undispatchStorefrontOrder(order.uuid),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Voltar para produção',
            description: 'O pedido sairá da expedição e voltará para a fila de produção.',
            confirmLabel: 'Voltar para produção',
            requiresReason: false,
            execute: () => orderService.undispatchOrder(order.uuid),
          }
        }
      }

      if (target === 'dispatch') {
        if (currentStage === 'production') {
          if (order.origin === 'storefront') {
            if (!canDispatchStorefrontOrder) return null
            return {
              title: 'Enviar para expedição',
              description: 'O pedido será marcado como saiu para entrega.',
              confirmLabel: 'Enviar para expedição',
              requiresReason: false,
              execute: () => storefrontOrderService.dispatchStorefrontOrder(order.uuid),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Enviar para expedição',
            description: 'O pedido será marcado como saiu para entrega.',
            confirmLabel: 'Enviar para expedição',
            requiresReason: false,
            execute: () => orderService.dispatchOrder(order.uuid),
          }
        }

        if (currentStage === 'financial_pending') {
          if (order.origin === 'storefront') {
            if (!canUndeliverStorefrontOrder) return null
            return {
              title: 'Voltar para expedição',
              description: 'O pedido deixará de constar como entregue e voltará para a etapa de expedição.',
              confirmLabel: 'Voltar para expedição',
              requiresReason: false,
              execute: () => storefrontOrderService.undeliverStorefrontOrder(order.uuid),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Voltar para expedição',
            description: 'O pedido deixará de constar como entregue e voltará para a etapa de expedição.',
            confirmLabel: 'Voltar para expedição',
            requiresReason: false,
            execute: () => orderService.undeliverOrder(order.uuid),
          }
        }
      }

      if (target === 'financial_pending' && currentStage === 'dispatch') {
        if (order.origin === 'storefront') {
          if (!canDeliverStorefrontOrder) return null
          return {
            title: 'Marcar como entregue',
            description: 'O pedido será marcado como entregue e seguirá para a fila financeira, se ainda não estiver pago.',
            confirmLabel: 'Marcar como entregue',
            requiresReason: false,
            execute: () => storefrontOrderService.deliverStorefrontOrder(order.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          title: 'Marcar como entregue',
          description: 'O pedido será marcado como entregue e seguirá para a fila financeira, se ainda não estiver pago.',
          confirmLabel: 'Marcar como entregue',
          requiresReason: false,
          execute: () => orderService.deliverOrder(order.uuid),
        }
      }

      if (target === 'cancel') {
        if (currentStage === 'approval') {
          if (order.origin === 'storefront') {
            if (!canApproveStorefrontOrder) return null
            return {
              title: 'Recusar pedido',
              description: 'Informe o motivo da recusa. O pedido será retirado da fila operacional.',
              confirmLabel: 'Recusar pedido',
              requiresReason: true,
              execute: (reason) => storefrontOrderService.rejectStorefrontOrder(order.uuid, reason),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Recusar pedido',
            description: 'Informe o motivo da recusa. O pedido será retirado da fila operacional.',
            confirmLabel: 'Recusar pedido',
            requiresReason: true,
            execute: (reason) => orderService.rejectOrder(order.uuid, reason),
          }
        }

        if (currentStage === 'production' || currentStage === 'dispatch') {
          if (order.origin === 'storefront') {
            if (!canCancelStorefrontOrder) return null
            return {
              title: 'Cancelar pedido',
              description: 'Informe o motivo do cancelamento. Esse registro ficará salvo no histórico operacional.',
              confirmLabel: 'Cancelar pedido',
              requiresReason: true,
              execute: (reason) => storefrontOrderService.cancelStorefrontOrder(order.uuid, reason),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Cancelar pedido',
            description: 'Informe o motivo do cancelamento. Esse registro ficará salvo no histórico operacional.',
            confirmLabel: 'Cancelar pedido',
            requiresReason: true,
            execute: (reason) => orderService.cancelOrder(order.uuid, reason),
          }
        }
      }

      if (target === 'complete' && currentStage === 'financial_pending') {
        if (order.origin === 'storefront') {
          if (!canPayStorefrontOrder) return null
          return {
            title: 'Concluir pedido',
            description: 'O pedido será marcado como pago e sairá da fila operacional.',
            confirmLabel: 'Concluir pedido',
            requiresReason: false,
            execute: () => storefrontOrderService.payStorefrontOrder(order.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          title: 'Baixar recebimento',
          description: 'O pedido será marcado como pago e sairá da fila operacional.',
          confirmLabel: 'Baixar recebimento',
          requiresReason: false,
          execute: () => orderService.payOrder(order.uuid),
        }
      }

      return null
    },
    [
      canApproveStorefrontOrder,
      canCancelStorefrontOrder,
      canDeliverStorefrontOrder,
      canDispatchStorefrontOrder,
      canPayStorefrontOrder,
      canUndeliverStorefrontOrder,
      canUndispatchStorefrontOrder,
      canUpdateOrders,
    ],
  )

  const executeOrderBoardAction = useCallback(
    async (orderUuid: string, action: ResolvedOrderBoardAction, reason = '') => {
      setQuickActionError(null)
      setSubmittingOrderUuid(orderUuid)
      setBoardActionSubmitting(true)

      try {
        await action.execute(reason)
        await refreshOperationData()
        gridApiRef.current?.refreshInfiniteCache()
      } catch (error) {
        setQuickActionError(getApiErrorMessage(error, 'Não foi possível mover o card agora.'))
      } finally {
        setSubmittingOrderUuid(null)
        setBoardActionSubmitting(false)
        setPendingOrderBoardAction(null)
        setDraggedOrderUuid(null)
        setActiveOrderDropTarget(null)
      }
    },
    [refreshOperationData],
  )

  const handleOrderBoardDrop = useCallback(
    async (order: Order, target: OrderBoardDropTarget) => {
      const action = resolveOrderBoardAction(order, target)
      if (!action) return

      if (action.requiresReason) {
        setPendingOrderBoardAction({ ...action, orderUuid: order.uuid })
        return
      }

      await executeOrderBoardAction(order.uuid, action)
    },
    [executeOrderBoardAction, resolveOrderBoardAction],
  )

  const handleOrderCardDragStart = useCallback((orderUuid: string, event: DragEvent<HTMLDivElement>) => {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', orderUuid)
    setDraggedOrderUuid(orderUuid)
    setActiveOrderDropTarget(null)
    setQuickActionError(null)
  }, [])

  const handleOrderCardDragEnd = useCallback(() => {
    setDraggedOrderUuid(null)
    setActiveOrderDropTarget(null)
  }, [])

  const markOrderDropTarget = useCallback(
    (event: DragEvent<HTMLDivElement>, target: OrderBoardDropTarget) => {
      event.preventDefault()
      if (!draggedPreviewOrder) return
      const action = resolveOrderBoardAction(draggedPreviewOrder, target)
      if (!action) return
      setActiveOrderDropTarget(target)
      event.dataTransfer.dropEffect = 'move'
    },
    [draggedPreviewOrder, resolveOrderBoardAction],
  )

  const clearOrderDropTarget = useCallback(() => {
    setActiveOrderDropTarget(null)
  }, [])

  const receiveOrderDrop = useCallback(
    async (event: DragEvent<HTMLDivElement>, target: OrderBoardDropTarget) => {
      event.preventDefault()
      const orderUuid = event.dataTransfer.getData('text/plain') || draggedOrderUuid
      const order = orderUuid ? previewOrderMap.get(orderUuid) : null
      if (!order) {
        setDraggedOrderUuid(null)
        setActiveOrderDropTarget(null)
        return
      }

      await handleOrderBoardDrop(order, target)
    },
    [draggedOrderUuid, handleOrderBoardDrop, previewOrderMap],
  )

  const columns = useMemo<ServerGridColumn<Order>[]>(
    () => [
      ...(!isManualOrdersPage
        ? [{
            field: 'origin',
            headerName: 'Canal',
            width: 120,
            sortable: false,
            filterType: 'none',
            cellRenderer: (row: Order) => {
              const meta = ORIGIN_META[row.origin]
              return (
                <Chip
                  size="small"
                  label={meta?.shortLabel ?? row.origin}
                  sx={{
                    fontWeight: 700,
                    color: 'var(--pt-primary)',
                    bgcolor: 'color-mix(in srgb, var(--pt-primary) 12%, transparent)',
                  }}
                />
              )
            },
            exportValue: (row: Order) => ORIGIN_META[row.origin]?.label ?? row.origin,
          } satisfies ServerGridColumn<Order>]
        : []),
      ...(!isManualOrdersPage
        ? [{
            field: 'operation_stage',
            headerName: 'Fila',
            width: 170,
            sortable: false,
            filterType: 'none',
            cellRenderer: (row: Order) => {
              const stage = deriveOperationStage(row)
              if (!stage) {
                return (
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                    Concluído / fora da fila
                  </Typography>
                )
              }

              const meta = STAGE_META[stage]
              return (
                <Chip
                  size="small"
                  label={meta.label}
                  sx={{
                    color: meta.accent,
                    bgcolor: `color-mix(in srgb, ${meta.accent} 12%, transparent)`,
                    ...ORDER_STAGE_CHIP_SX,
                  }}
                />
              )
            },
            exportValue: (row: Order) => {
              const stage = deriveOperationStage(row)
              return stage ? STAGE_META[stage].label : 'Concluído / fora da fila'
            },
          } satisfies ServerGridColumn<Order>]
        : []),
      {
        field: 'codigo',
        headerName: 'Código',
        width: 100,
        filterType: 'text',
        cellRenderer: (row) => row.codigo,
        exportValue: (row) => row.codigo,
      },
      {
        field: 'client_name',
        headerName: 'Cliente',
        filterType: 'text',
        cellRenderer: (row) => row.final_customer?.name ?? '',
        exportValue: (row) => row.final_customer?.name ?? '',
      },
      {
        field: 'total_amount',
        headerName: 'Total',
        width: 140,
        filterType: 'number',
        cellRenderer: (row) => formatCurrency(row.total_amount),
        exportValue: (row) => formatCurrency(row.total_amount),
      },
      ...(!isManualOrdersPage
        ? [{
            field: 'status',
            headerName: 'Etapa',
            width: 300,
            sortable: false,
            filterType: 'none',
            cellRenderer: (row: Order) => <OrderStatusBadge order={row} />,
            exportValue: (row: Order) => deriveOrderStatus({
              is_cancelled: Boolean(row.cancelled_at),
              is_paid: row.is_paid,
              is_delivered: row.is_delivered,
              is_installment: row.is_installment,
              delivered_at: row.delivered_at,
              paid_at: row.paid_at,
              status: row.status,
              is_out_for_delivery: row.is_out_for_delivery,
            }).label,
          } satisfies ServerGridColumn<Order>]
        : []),
      {
        field: 'is_paid',
        headerName: 'Pago',
        width: 110,
        filterType: 'boolean',
        cellRenderer: (row) => <ActiveChip isActive={row.is_paid} activeLabel="Sim" inactiveLabel="Não" />,
      },
      {
        field: 'is_delivered',
        headerName: 'Entregue',
        width: 120,
        filterType: 'boolean',
        cellRenderer: (row) => <ActiveChip isActive={row.is_delivered} activeLabel="Sim" inactiveLabel="Não" />,
      },
      {
        field: 'created_at',
        headerName: 'Criado em',
        width: 170,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => formatDateTimeBR(row.created_at),
        exportValue: (row) => formatDateTimeBR(row.created_at),
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
            <Tooltip title="Abrir pedido" arrow>
              <IconButton
                size="small"
                aria-label={`Abrir pedido do cliente ${row.final_customer?.name ?? ''}`}
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
    [isManualOrdersPage],
  )

  const toolbar = (
    <Stack spacing={1.25} sx={{ width: '100%' }}>
      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', xl: 'repeat(7, 1fr)' },
          gap: 1.25,
        }}
      >
        <OperationMetricCard
          label="Pedidos em andamento"
          value={snapshot.activeTotal === null ? '—' : String(snapshot.activeTotal)}
          caption="Fila canônica com tudo que ainda pede ação operacional."
        />
        <OperationMetricCard
          label="Aguardando aprovação"
          value={snapshot.storefrontPendingApproval === null ? '—' : String(snapshot.storefrontPendingApproval)}
          caption="Vendas da bilheteria online esperando aceite da empresa."
          accent="var(--pt-warning)"
          action={
            canReadStorefrontQueue ? (
              <Button component={RouterLink} to="/vendas-online" size="small" variant="text">
                Abrir fila online
              </Button>
            ) : undefined
          }
        />
        <OperationMetricCard
          label="Produção"
          value={snapshot.productionTotal === null ? '—' : String(snapshot.productionTotal)}
          caption="Pedidos confirmados que ainda estão dentro da operação interna."
          accent={STAGE_META.production.accent}
          action={
            <Button
              size="small"
              variant="text"
              onClick={() => {
                setStageFilter('production')
                setStatusFilter('all')
                gridApiRef.current?.refreshInfiniteCache()
              }}
            >
              Filtrar produção
            </Button>
          }
        />
        <OperationMetricCard
          label="Expedição"
          value={snapshot.dispatchTotal === null ? '—' : String(snapshot.dispatchTotal)}
          caption="Pedidos já despachados e ainda não concluídos no cliente."
          accent={STAGE_META.dispatch.accent}
          action={
            <Button
              size="small"
              variant="text"
              onClick={() => {
                setStageFilter('dispatch')
                setStatusFilter('all')
                gridApiRef.current?.refreshInfiniteCache()
              }}
            >
              Filtrar expedição
            </Button>
          }
        />
        <OperationMetricCard
          label="Financeiro pendente"
          value={snapshot.financialPendingTotal === null ? '—' : String(snapshot.financialPendingTotal)}
          caption="Pedidos já entregues que ainda aguardam recebimento total."
          accent={STAGE_META.financial_pending.accent}
          action={
            <Button
              size="small"
              variant="text"
              onClick={() => {
                setStageFilter('financial_pending')
                setStatusFilter('all')
                gridApiRef.current?.refreshInfiniteCache()
              }}
            >
              Filtrar pendências
            </Button>
          }
        />
        <OperationMetricCard
          label="Ação imediata"
          value={String(queuePrioritySummary.critical)}
          caption="Pedidos nas filas superiores que já passaram do limite mais crítico do recorte."
          accent="var(--pt-danger)"
          action={
            <Button
              size="small"
              variant="text"
              onClick={() => {
                setActiveOnly(true)
                gridApiRef.current?.refreshInfiniteCache()
              }}
            >
              Atuar agora
            </Button>
          }
        />
        <OperationMetricCard
          label="Prioridade alta"
          value={String(queuePrioritySummary.urgent)}
          caption="Pedidos que ainda não estouraram, mas já merecem entrar no foco da operação."
          accent="var(--pt-warning)"
        />
      </Box>

      {queuePrioritySummary.critical > 0 ? (
        <Alert severity="error" variant="outlined">
          Existem {queuePrioritySummary.critical} pedido(s) pedindo ação imediata na operação. Priorize os cards destacados em vermelho nas filas acima.
        </Alert>
      ) : null}

      <Box
        sx={{
          p: 1.5,
          borderRadius: '18px',
          border: '1px solid var(--pt-border)',
          bgcolor: 'color-mix(in srgb, var(--pt-surface) 92%, white)',
        }}
      >
        <Stack spacing={1.2}>
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1, flexWrap: 'wrap' }}>
            <Box>
              <Typography sx={{ fontSize: 14, fontWeight: 800 }}>Coordenação da operação</Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                Quem entra em ação em cada etapa e quais módulos especializados destravam o próximo passo.
              </Typography>
            </Box>
          </Stack>

          <Box
            sx={{
              display: 'grid',
              gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)', xl: 'repeat(4, 1fr)' },
              gap: 1,
            }}
          >
            {(['store', 'operations', 'delivery', 'finance'] as OperationOwner[]).map((owner) => {
              const meta = ownerMeta(owner)
              const count = ownerSummary[owner]
              const extra = meta.caption

              return (
                <Box
                  key={owner}
                  sx={{
                    p: 1.25,
                    borderRadius: '16px',
                    border: '1px solid var(--pt-border)',
                    bgcolor: 'color-mix(in srgb, var(--pt-surface) 96%, white)',
                  }}
                >
                  <Stack spacing={0.75}>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}>
                      <Typography sx={{ fontSize: 13.5, fontWeight: 800, color: meta.accent }}>{meta.label}</Typography>
                      <Chip
                        size="small"
                        label={`${count} na mão`}
                        sx={{ fontWeight: 700, color: meta.accent, bgcolor: `color-mix(in srgb, ${meta.accent} 12%, transparent)` }}
                      />
                    </Stack>
                    <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{extra}</Typography>
                    {meta.to ? (
                      <Button component={RouterLink} to={meta.to} size="small" variant="text" sx={{ alignSelf: 'flex-start', px: 0 }}>
                        Abrir módulo
                      </Button>
                    ) : (
                      <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>Atue diretamente pelos pedidos desta fila.</Typography>
                    )}
                  </Stack>
                </Box>
              )
            })}
          </Box>
        </Stack>
      </Box>

      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: '1fr', xl: 'minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) 280px' },
          gap: 1.25,
          alignItems: 'start',
        }}
      >
        <WorkflowBoardColumn
          title={STAGE_META.approval.label}
          caption={STAGE_META.approval.caption}
          accent={STAGE_META.approval.accent}
          countLabel={snapshot.storefrontPendingApproval === null ? '—' : `${snapshot.storefrontPendingApproval} na fila`}
          onOpenQueue={() => {
            setStageFilter('approval')
            setStatusFilter('all')
            setActiveOnly(true)
            gridApiRef.current?.refreshInfiniteCache()
          }}
          headerAction={
            canReadStorefrontQueue ? (
              <Button component={RouterLink} to="/vendas-online" size="small" variant="text" sx={{ px: 0 }}>
                Abrir gestão online
              </Button>
            ) : undefined
          }
          isActiveDrop={activeOrderDropTarget === 'approval'}
          onDragOver={(event) => markOrderDropTarget(event, 'approval')}
          onDragLeave={clearOrderDropTarget}
          onDrop={(event) => void receiveOrderDrop(event, 'approval')}
          emptyMessage="Arraste para cá apenas se o card puder voltar para esta etapa."
        >
          {queuePreview.approval.length === 0 ? (
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhum pedido nesta fila agora.</Typography>
          ) : (
            queuePreview.approval.map((order) => (
              <QueueOrderCard
                key={order.uuid}
                order={order}
                onOpen={setSelectedOrderUuid}
                onOpenTimeline={setSelectedTimelineOrderUuid}
                quickAction={resolveQuickAction(order)}
                onQuickAction={(currentOrder) => void handleQuickAction(currentOrder)}
                isSubmitting={submittingOrderUuid === order.uuid}
                draggable
                isDragging={draggedOrderUuid === order.uuid}
                onDragStart={(event) => handleOrderCardDragStart(order.uuid, event)}
                onDragEnd={handleOrderCardDragEnd}
              />
            ))
          )}
        </WorkflowBoardColumn>

        <WorkflowBoardColumn
          title={STAGE_META.production.label}
          caption={STAGE_META.production.caption}
          accent={STAGE_META.production.accent}
          countLabel={snapshot.productionTotal === null ? '—' : `${snapshot.productionTotal} na fila`}
          onOpenQueue={() => {
            setStageFilter('production')
            setStatusFilter('all')
            setActiveOnly(true)
            gridApiRef.current?.refreshInfiniteCache()
          }}
          isActiveDrop={activeOrderDropTarget === 'production'}
          onDragOver={(event) => markOrderDropTarget(event, 'production')}
          onDragLeave={clearOrderDropTarget}
          onDrop={(event) => void receiveOrderDrop(event, 'production')}
          emptyMessage="Use esta coluna para pedidos aprovados que ainda estão sendo preparados."
        >
          {queuePreview.production.length === 0 ? (
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhum pedido nesta fila agora.</Typography>
          ) : (
            queuePreview.production.map((order) => (
              <QueueOrderCard
                key={order.uuid}
                order={order}
                onOpen={setSelectedOrderUuid}
                onOpenTimeline={setSelectedTimelineOrderUuid}
                quickAction={resolveQuickAction(order)}
                onQuickAction={(currentOrder) => void handleQuickAction(currentOrder)}
                isSubmitting={submittingOrderUuid === order.uuid}
                draggable
                isDragging={draggedOrderUuid === order.uuid}
                onDragStart={(event) => handleOrderCardDragStart(order.uuid, event)}
                onDragEnd={handleOrderCardDragEnd}
              />
            ))
          )}
        </WorkflowBoardColumn>

        <WorkflowBoardColumn
          title={STAGE_META.dispatch.label}
          caption={STAGE_META.dispatch.caption}
          accent={STAGE_META.dispatch.accent}
          countLabel={snapshot.dispatchTotal === null ? '—' : `${snapshot.dispatchTotal} na fila`}
          onOpenQueue={() => {
            setStageFilter('dispatch')
            setStatusFilter('all')
            setActiveOnly(true)
            gridApiRef.current?.refreshInfiniteCache()
          }}
          isActiveDrop={activeOrderDropTarget === 'dispatch'}
          onDragOver={(event) => markOrderDropTarget(event, 'dispatch')}
          onDragLeave={clearOrderDropTarget}
          onDrop={(event) => void receiveOrderDrop(event, 'dispatch')}
          emptyMessage="Arraste para cá quando o pedido sair para entrega."
        >
          {queuePreview.dispatch.length === 0 ? (
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhum pedido nesta fila agora.</Typography>
          ) : (
            queuePreview.dispatch.map((order) => (
              <QueueOrderCard
                key={order.uuid}
                order={order}
                onOpen={setSelectedOrderUuid}
                onOpenTimeline={setSelectedTimelineOrderUuid}
                quickAction={resolveQuickAction(order)}
                onQuickAction={(currentOrder) => void handleQuickAction(currentOrder)}
                isSubmitting={submittingOrderUuid === order.uuid}
                draggable
                isDragging={draggedOrderUuid === order.uuid}
                onDragStart={(event) => handleOrderCardDragStart(order.uuid, event)}
                onDragEnd={handleOrderCardDragEnd}
              />
            ))
          )}
        </WorkflowBoardColumn>

        <WorkflowBoardColumn
          title={STAGE_META.financial_pending.label}
          caption={STAGE_META.financial_pending.caption}
          accent={STAGE_META.financial_pending.accent}
          countLabel={snapshot.financialPendingTotal === null ? '—' : `${snapshot.financialPendingTotal} na fila`}
          onOpenQueue={() => {
            setStageFilter('financial_pending')
            setStatusFilter('all')
            setActiveOnly(true)
            gridApiRef.current?.refreshInfiniteCache()
          }}
          headerAction={
            canReadFinance ? (
              <Button component={RouterLink} to="/financeiro/conciliacao" size="small" variant="text" sx={{ px: 0 }}>
                Abrir conciliação
              </Button>
            ) : undefined
          }
          isActiveDrop={activeOrderDropTarget === 'financial_pending'}
          onDragOver={(event) => markOrderDropTarget(event, 'financial_pending')}
          onDragLeave={clearOrderDropTarget}
          onDrop={(event) => void receiveOrderDrop(event, 'financial_pending')}
          emptyMessage="Arraste para cá quando o pedido já estiver entregue e faltar só a baixa financeira."
        >
          {queuePreview.financial_pending.length === 0 ? (
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhum pedido nesta fila agora.</Typography>
          ) : (
            queuePreview.financial_pending.map((order) => (
              <QueueOrderCard
                key={order.uuid}
                order={order}
                onOpen={setSelectedOrderUuid}
                onOpenTimeline={setSelectedTimelineOrderUuid}
                quickAction={resolveQuickAction(order)}
                onQuickAction={(currentOrder) => void handleQuickAction(currentOrder)}
                isSubmitting={submittingOrderUuid === order.uuid}
                draggable
                isDragging={draggedOrderUuid === order.uuid}
                onDragStart={(event) => handleOrderCardDragStart(order.uuid, event)}
                onDragEnd={handleOrderCardDragEnd}
              />
            ))
          )}
        </WorkflowBoardColumn>

        <Stack spacing={1.25}>
          <WorkflowActionDropZone
            title="Cancelar / recusar"
            description="Use esta área para recusar pedidos em aprovação ou cancelar pedidos já em operação. O motivo é obrigatório."
            accent="var(--pt-danger)"
            icon={<CancelOutlinedIcon fontSize="small" />}
            isActiveDrop={activeOrderDropTarget === 'cancel'}
            isDisabled={!draggedPreviewOrder || !resolveOrderBoardAction(draggedPreviewOrder, 'cancel')}
            onDragOver={(event) => markOrderDropTarget(event, 'cancel')}
            onDragLeave={clearOrderDropTarget}
            onDrop={(event) => void receiveOrderDrop(event, 'cancel')}
          />
          <WorkflowActionDropZone
            title="Concluir / baixar"
            description="Solte aqui os pedidos da fila financeira para marcar pagamento e concluir o fluxo operacional."
            accent="var(--pt-success)"
            icon={<PaymentsOutlinedIcon fontSize="small" />}
            isActiveDrop={activeOrderDropTarget === 'complete'}
            isDisabled={!draggedPreviewOrder || !resolveOrderBoardAction(draggedPreviewOrder, 'complete')}
            onDragOver={(event) => markOrderDropTarget(event, 'complete')}
            onDragLeave={clearOrderDropTarget}
            onDrop={(event) => void receiveOrderDrop(event, 'complete')}
          />
          <Box
            sx={{
              p: 1.35,
              borderRadius: '16px',
              border: '1px solid var(--pt-border)',
              bgcolor: 'color-mix(in srgb, var(--pt-surface) 96%, white)',
            }}
          >
            <Stack spacing={0.75}>
              <Typography sx={{ fontSize: 14, fontWeight: 800 }}>Como usar o board</Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                Arraste o card para a coluna seguinte para avançar, para a coluna anterior quando houver retorno permitido e
                para as zonas laterais quando quiser concluir ou cancelar.
              </Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                Todo movimento fica registrado com usuário, horário e, quando necessário, motivo do cancelamento.
              </Typography>
            </Stack>
          </Box>
        </Stack>
      </Box>

      <Box
        sx={{
          p: 1.5,
          borderRadius: '18px',
          border: '1px solid var(--pt-border)',
          bgcolor: 'color-mix(in srgb, var(--pt-surface) 92%, white)',
        }}
      >
        <Stack spacing={1.25}>
          <Stack
            direction={{ xs: 'column', md: 'row' }}
            spacing={1}
            sx={{ alignItems: { xs: 'stretch', md: 'center' }, justifyContent: 'space-between' }}
          >
            <Stack direction="row" spacing={0.9} sx={{ alignItems: 'center' }}>
              <FilterAltOutlinedIcon sx={{ color: 'var(--pt-muted)', fontSize: 19 }} />
              <Typography sx={{ fontSize: 14, fontWeight: 700 }}>Recorte operacional</Typography>
            </Stack>
            <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap', rowGap: 0.75 }}>
              <Chip
                clickable
                color={activeOnly ? 'primary' : 'default'}
                variant={activeOnly ? 'filled' : 'outlined'}
                label={activeOnly ? 'Só em andamento' : 'Mostrar histórico também'}
                onClick={() => {
                  setActiveOnly((current) => !current)
                  gridApiRef.current?.refreshInfiniteCache()
                }}
              />
              <Chip
                size="small"
                label={`Manual: ${snapshot.byOrigin.staff ?? 0}`}
                icon={<ApartmentOutlinedIcon fontSize="small" />}
                variant="outlined"
              />
              <Chip
                size="small"
                label={`Online: ${snapshot.byOrigin.storefront ?? 0}`}
                icon={<LanguageOutlinedIcon fontSize="small" />}
                variant="outlined"
              />
              <Chip
                size="small"
                label={`iFood: ${snapshot.byOrigin.ifood ?? 0}`}
                icon={<LanguageOutlinedIcon fontSize="small" />}
                variant="outlined"
              />
            </Stack>
          </Stack>

          <Stack spacing={1}>
            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 1 }}>
              {STAGE_FILTERS.map((option) => (
                <Chip
                  key={option.value}
                  clickable
                  label={option.label}
                  color={stageFilter === option.value ? 'primary' : 'default'}
                  variant={stageFilter === option.value ? 'filled' : 'outlined'}
                  onClick={() => {
                    setStageFilter(option.value)
                    if (option.value !== 'all') {
                      setStatusFilter('all')
                    }
                    setSearchParams((current) => {
                      const next = new URLSearchParams(current)
                      if (option.value === 'all') {
                        next.delete('stage')
                      } else {
                        next.set('stage', option.value)
                      }
                      return next
                    }, { replace: true })
                    gridApiRef.current?.refreshInfiniteCache()
                  }}
                />
              ))}
            </Stack>

            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 1 }}>
              {ORIGIN_FILTERS.map((option) => (
                <Chip
                  key={option.value}
                  clickable
                  label={option.label}
                  color={originFilter === option.value ? 'primary' : 'default'}
                  variant={originFilter === option.value ? 'filled' : 'outlined'}
                  onClick={() => {
                    setOriginFilter(option.value)
                    gridApiRef.current?.refreshInfiniteCache()
                  }}
                />
              ))}
            </Stack>

            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 1 }}>
              {[
                { value: 'all' as const, label: 'Todas as etapas' },
                { value: 'pending_approval' as const, label: 'Aguardando aprovação' },
                { value: 'confirmed' as const, label: 'Confirmados' },
                { value: 'cancellation_requested' as const, label: 'Cancelamento solicitado' },
                { value: 'rejected' as const, label: 'Recusados' },
              ].map((option) => (
                <Chip
                  key={option.value}
                  clickable
                  label={option.label}
                  color={statusFilter === option.value ? 'primary' : 'default'}
                  variant={statusFilter === option.value ? 'filled' : 'outlined'}
                  onClick={() => {
                    setStatusFilter(option.value)
                    if (option.value !== 'all') {
                      setStageFilter('all')
                    }
                    gridApiRef.current?.refreshInfiniteCache()
                  }}
                />
              ))}
            </Stack>
          </Stack>
        </Stack>
      </Box>

      {snapshotError ? (
        <Alert severity="warning" variant="outlined">
          {snapshotError}
        </Alert>
      ) : null}

      {queuePreviewError ? (
        <Alert severity="warning" variant="outlined">
          {queuePreviewError}
        </Alert>
      ) : null}

      {quickActionError ? (
        <Alert severity="warning" variant="outlined">
          {quickActionError}
        </Alert>
      ) : null}
    </Stack>
  )

  const manualPage = (
    <CrudListPage
      title="Pedidos manuais"
      subtitle="Gerencie os pedidos lançados manualmente pela equipe."
      createLabel="Novo pedido"
      canCreate={can(ACCESS.ordersCreate)}
      onCreate={() => navigate('/pedidos/novo')}
      error={null}
      onRetry={() => undefined}
      isLoading={!activeTenantUuid}
      isEmpty={false}
    >
      <Box sx={{ overflowX: 'auto' }}>
        <Box sx={{ minWidth: 980 }}>
          <ServerDataGrid
            columns={columns}
            fetchPage={fetchPage}
            rowIdField="uuid"
            exportFileName="pedidos-manuais"
            onGridReady={(api) => {
              gridApiRef.current = api
            }}
            emptyState={{
              icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
              title: 'Nenhum pedido manual encontrado',
              description: 'Assim que a equipe criar pedidos manualmente, eles aparecerão aqui.',
              action: can(ACCESS.ordersCreate) ? (
                <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/pedidos/novo')}>
                  Criar primeiro pedido
                </Button>
              ) : undefined,
            }}
          />
        </Box>
      </Box>
    </CrudListPage>
  )

  return (
    <>
      {isManualOrdersPage ? manualPage : (
        <CrudListPage
          title="Central de operação"
          subtitle="Acompanhe a fila canônica de pedidos do sistema por canal, etapa e urgência."
          createLabel="Novo pedido"
          canCreate={can(ACCESS.ordersCreate)}
          onCreate={() => navigate('/pedidos/novo')}
          error={null}
          onRetry={() => undefined}
          isLoading={!activeTenantUuid}
          isEmpty={false}
          toolbar={toolbar}
        >
          {openedFromDashboard && isStageFilterFromQueryValid ? (
            <Alert severity="info" variant="outlined" sx={{ mb: 2 }}>
              Esta fila foi aberta a partir do dashboard para acompanhar a etapa{' '}
              <strong>
                {{
                  approval: 'Aguardando aprovação',
                  production: 'Em produção',
                  dispatch: 'Em expedição',
                  financial_pending: 'Financeiro pendente',
                }[stageFilterFromQuery]}
              </strong>
              .
            </Alert>
          ) : null}

          <Box sx={{ overflowX: 'auto' }}>
            <Box sx={{ minWidth: 1180 }}>
              <ServerDataGrid
                columns={columns}
                fetchPage={fetchPage}
                rowIdField="uuid"
                exportFileName="central-operacao"
                onGridReady={(api) => {
                  gridApiRef.current = api
                }}
                emptyState={{
                  icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                  title: activeOnly ? 'Nenhum pedido em andamento neste recorte' : 'Nenhum pedido encontrado',
                  description: activeOnly
                    ? 'Quando houver novos pedidos para operar, eles aparecerão aqui independente do canal de entrada.'
                    : 'Ajuste os filtros acima ou crie o primeiro pedido para iniciar a operação.',
                  action: can(ACCESS.ordersCreate) ? (
                    <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/pedidos/novo')}>
                      Criar primeiro pedido
                    </Button>
                  ) : undefined,
                }}
              />
            </Box>
          </Box>
        </CrudListPage>
      )}

      <OrderDetailDialog
        orderUuid={selectedOrderUuid}
        open={selectedOrderUuid !== null}
        onClose={() => setSelectedOrderUuid(null)}
        onChanged={() => {
          gridApiRef.current?.refreshInfiniteCache()
          if (!isManualOrdersPage) {
            void refreshOperationData()
          }
        }}
      />

      <WorkflowReasonDialog
        open={pendingOrderBoardAction !== null}
        title={pendingOrderBoardAction?.title ?? 'Registrar motivo'}
        description={pendingOrderBoardAction?.description ?? ''}
        confirmLabel={pendingOrderBoardAction?.confirmLabel ?? 'Confirmar'}
        loading={boardActionSubmitting}
        onClose={() => {
          if (boardActionSubmitting) return
          setPendingOrderBoardAction(null)
          setDraggedOrderUuid(null)
          setActiveOrderDropTarget(null)
        }}
        onConfirm={(reason) => {
          if (!pendingOrderBoardAction?.orderUuid) return
          void executeOrderBoardAction(pendingOrderBoardAction.orderUuid, pendingOrderBoardAction, reason)
        }}
      />

      <WorkflowTimelineDialog
        open={selectedTimelineOrderUuid !== null}
        title="Histórico operacional do pedido"
        subjectLabel={selectedTimelineOrderUuid ? `pedido ${selectedTimelineOrderUuid}` : 'pedido'}
        loader={() => (selectedTimelineOrderUuid ? workflowService.getOrderWorkflowTimeline(selectedTimelineOrderUuid) : Promise.resolve([]))}
        stageLabel={(stage) => {
          if (!stage) return 'Sem etapa'
          return STAGE_META[stage as OrderOperationStage]?.label ?? stage
        }}
        onClose={() => setSelectedTimelineOrderUuid(null)}
      />
    </>
  )
}
