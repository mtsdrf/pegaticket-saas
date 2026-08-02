import AddIcon from '@mui/icons-material/Add'
import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import CheckCircleOutlineOutlinedIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import FilterAltOutlinedIcon from '@mui/icons-material/FilterAltOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import LanguageOutlinedIcon from '@mui/icons-material/LanguageOutlined'
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
import { SaleDetailDialog } from '../../components/sale/SaleDetailDialog'
import { WorkflowActionDropZone } from '../../components/workflow/WorkflowActionDropZone'
import { WorkflowBoardColumn } from '../../components/workflow/WorkflowBoardColumn'
import { WorkflowReasonDialog } from '../../components/workflow/WorkflowReasonDialog'
import { WorkflowTimelineDialog } from '../../components/workflow/WorkflowTimelineDialog'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as saleService from '../../services/saleService'
import * as storefrontSaleService from '../../services/storefrontSaleService'
import * as workflowService from '../../services/workflowService'
import { getApiErrorMessage } from '../../types/api'
import type { Sale } from '../../types/sale'
import type { SaleOperationStage, SaleOrigin, SaleStatus } from '../../types/sale'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'
import { deriveSaleStatus, STATUS_TONE_COLORS } from '../../utils/saleStatus'

const ORIGIN_FILTERS: Array<{ value: 'all' | SaleOrigin; label: string }> = [
  { value: 'all', label: 'Todos os canais' },
  { value: 'staff', label: 'Manual' },
  { value: 'storefront', label: 'Online' },
]

const ORIGIN_META: Record<SaleOrigin, { label: string; shortLabel: string }> = {
  staff: { label: 'Pedido manual', shortLabel: 'Manual' },
  storefront: { label: 'Bilheteria online', shortLabel: 'Online' },
}

interface OperationSnapshot {
  activeTotal: number | null
  storefrontPendingApproval: number | null
  confirmedTotal: number | null
  financialPendingTotal: number | null
  byOrigin: Partial<Record<SaleOrigin, number>>
}

type OperationQueuePreview = Record<SaleOperationStage, Sale[]>

interface QuickQueueAction {
  label: string
  icon: React.ReactNode
  run: (order: Sale) => Promise<Sale>
}

type OperationPriority = 'normal' | 'attention' | 'urgent' | 'critical'
type OperationOwner = 'store' | 'operations' | 'finance'

type SaleBoardDropTarget = SaleOperationStage | 'cancel' | 'complete'

interface ResolvedSaleBoardAction {
  orderUuid?: string
  title: string
  description: string
  confirmLabel: string
  requiresReason: boolean
  execute: (reason: string) => Promise<Sale>
}

const STAGE_FILTERS: Array<{ value: 'all' | SaleOperationStage; label: string }> = [
  { value: 'all', label: 'Todas as filas' },
  { value: 'approval', label: 'Aprovação' },
  { value: 'confirmed', label: 'Confirmado' },
  { value: 'financial_pending', label: 'Financeiro pendente' },
]

const STAGE_META: Record<SaleOperationStage, { label: string; caption: string; accent: string }> = {
  approval: {
    label: 'Aprovação',
    caption: 'Pedidos aguardando aceite antes de entrar no fluxo operacional.',
    accent: 'var(--pt-warning)',
  },
  confirmed: {
    label: 'Confirmado',
    caption: 'Pedidos confirmados que ainda não foram concluídos.',
    accent: 'var(--pt-primary)',
  },
  financial_pending: {
    label: 'Financeiro pendente',
    caption: 'Pedidos já entregues com cobrança ainda em aberto.',
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

function deriveOperationStage(order: Sale): SaleOperationStage | null {
  if (order.cancelled_at || order.status === 'rejected') return null
  if (order.status === 'pending_approval') return 'approval'
  if (order.is_delivered && !order.is_paid) return 'financial_pending'
  if (order.status === 'confirmed' && !order.is_delivered) return 'confirmed'
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

function deriveOperationPriority(order: Sale): OperationPriority {
  const stage = deriveOperationStage(order)
  const ageInMinutes = minutesSince(order.created_at)

  if (stage === 'approval') {
    if (ageInMinutes >= 15) return 'critical'
    if (ageInMinutes >= 7) return 'urgent'
    if (ageInMinutes >= 3) return 'attention'
    return 'normal'
  }

  if (stage === 'confirmed') {
    if (ageInMinutes >= 45) return 'critical'
    if (ageInMinutes >= 25) return 'urgent'
    if (ageInMinutes >= 12) return 'attention'
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

function deriveOperationOwner(order: Sale): OperationOwner | null {
  const stage = deriveOperationStage(order)
  if (stage === 'approval') return 'store'
  if (stage === 'confirmed') return 'operations'
  if (stage === 'financial_pending') return 'finance'
  return null
}

function ownerMeta(owner: OperationOwner): { label: string; caption: string; accent: string; to?: string } {
  if (owner === 'store') {
    return {
      label: 'Atendimento da loja',
      caption: 'Confirma e libera vendas recebidas do canal online.',
      accent: 'var(--pt-warning)',
      to: '/vendas-online',
    }
  }
  if (owner === 'operations') {
    return {
      label: 'Operação interna',
      caption: 'Prepara a venda até a conclusão (entrega/retirada).',
      accent: 'var(--pt-primary)',
    }
  }
  return {
    label: 'Financeiro',
    caption: 'Finaliza recebimento e baixa pendências de cobrança.',
    accent: 'var(--pt-danger)',
    to: '/financeiro/conciliacao',
  }
}

function SaleStatusBadge({ order }: { order: Sale }) {
  const derived = deriveSaleStatus({
    is_cancelled: Boolean(order.cancelled_at),
    is_paid: order.is_paid,
    is_delivered: order.is_delivered,
    is_installment: order.is_installment,
    delivered_at: order.delivered_at,
    paid_at: order.paid_at,
    status: order.status,
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

function QueueSaleCard({
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
  order: Sale
  onOpen: (uuid: string) => void
  onOpenTimeline: (uuid: string) => void
  quickAction: QuickQueueAction | null
  onQuickAction: (order: Sale) => void
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
            Abrir venda
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
 * Central operacional multi-origem do PegaTicket. A rota permanece
 * `/vendas`, reunindo a venda canônica do sistema por origem (`staff`,
 * `storefront`), mantendo `/vendas-online` como fila especializada
 * complementar.
 */
export function SaleListPage() {
  const location = useLocation()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const isManualOrdersPage = location.pathname === '/vendas-manuais'

  const stageFilterFromQuery = searchParams.get('stage')
  const sourceFromQuery = searchParams.get('source')
  const isStageFilterFromQueryValid = stageFilterFromQuery === 'approval'
    || stageFilterFromQuery === 'confirmed'
    || stageFilterFromQuery === 'financial_pending'
  const openedFromDashboard = sourceFromQuery === 'dashboard'

  const [selectedSaleUuid, setSelectedOrderUuid] = useState<string | null>(null)
  const [selectedTimelineSaleUuid, setSelectedTimelineOrderUuid] = useState<string | null>(null)
  const [originFilter, setOriginFilter] = useState<'all' | SaleOrigin>(isManualOrdersPage ? 'staff' : 'all')
  const [statusFilter, setStatusFilter] = useState<'all' | SaleStatus>('all')
  const [stageFilter, setStageFilter] = useState<'all' | SaleOperationStage>(
    isStageFilterFromQueryValid ? stageFilterFromQuery : 'all',
  )
  const [activeOnly, setActiveOnly] = useState(true)
  const [snapshot, setSnapshot] = useState<OperationSnapshot>({
    activeTotal: null,
    storefrontPendingApproval: null,
    confirmedTotal: null,
    financialPendingTotal: null,
    byOrigin: {},
  })
  const [snapshotError, setSnapshotError] = useState<string | null>(null)
  const [queuePreview, setQueuePreview] = useState<OperationQueuePreview>({
    approval: [],
    confirmed: [],
    financial_pending: [],
  })
  const [queuePreviewError, setQueuePreviewError] = useState<string | null>(null)
  const [quickActionError, setQuickActionError] = useState<string | null>(null)
  const [draggedSaleUuid, setDraggedOrderUuid] = useState<string | null>(null)
  const [activeSaleDropTarget, setActiveOrderDropTarget] = useState<SaleBoardDropTarget | null>(null)
  const [pendingSaleBoardAction, setPendingOrderBoardAction] = useState<ResolvedSaleBoardAction | null>(null)
  const [boardActionSubmitting, setBoardActionSubmitting] = useState(false)
  const [submittingSaleUuid, setSubmittingOrderUuid] = useState<string | null>(null)

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Sale>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await saleService.listSales({
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

  const canReadStorefrontQueue = can(ACCESS.storefrontSalesRead)
  const canReadFinance = can(ACCESS.financeRead)
  const canApproveStorefrontOrder = can(ACCESS.storefrontSalesApprove)
  const canCancelStorefrontOrder = can(ACCESS.storefrontSalesCancel)
  const canDeliverStorefrontOrder = can(ACCESS.storefrontSalesDeliver)
  const canUndeliverStorefrontOrder = can(ACCESS.storefrontSalesUndeliver)
  const canPayStorefrontOrder = can(ACCESS.storefrontSalesPay)
  const canUpdateOrders = can(ACCESS.salesUpdate)

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

  const previewSaleMap = useMemo(() => {
    return new Map(Object.values(queuePreview).flat().map((order) => [order.uuid, order]))
  }, [queuePreview])

  const draggedPreviewSale = draggedSaleUuid ? (previewSaleMap.get(draggedSaleUuid) ?? null) : null

  const refreshOperationData = useCallback(async () => {
    if (!activeTenantUuid) return

    setSnapshotError(null)
    setQueuePreviewError(null)

    const activeTotalPromise = saleService.listSales({ active_only: true, per_page: 1 })
    const approvalPromise = saleService.listSales({ stage: 'approval', active_only: true, per_page: 5 })
    const confirmedPromise = saleService.listSales({ stage: 'confirmed', active_only: true, per_page: 5 })
    const financialPendingPromise = saleService.listSales({ stage: 'financial_pending', active_only: true, per_page: 5 })
    const originPromises = (['staff', 'storefront'] as SaleOrigin[]).map(async (origin) => {
      const page = await saleService.listSales({ origin, active_only: true, per_page: 1 })
      return [origin, page.pagination.total] as const
    })

    const [activeTotalPage, approvalPage, confirmedPage, financialPendingPage, originCounts] = await Promise.all([
      activeTotalPromise,
      approvalPromise,
      confirmedPromise,
      financialPendingPromise,
      Promise.all(originPromises),
    ])

    setSnapshot({
      activeTotal: activeTotalPage.pagination.total,
      storefrontPendingApproval: approvalPage.pagination.total,
      confirmedTotal: confirmedPage.pagination.total,
      financialPendingTotal: financialPendingPage.pagination.total,
      byOrigin: Object.fromEntries(originCounts),
    })
    setQueuePreview({
      approval: approvalPage.items,
      confirmed: confirmedPage.items,
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
    (order: Sale): QuickQueueAction | null => {
      const stage = deriveOperationStage(order)
      if (!stage || order.cancelled_at || order.status === 'rejected') return null

      if (stage === 'approval') {
        if (order.origin === 'storefront') {
          if (!canApproveStorefrontOrder) return null
          return {
            label: 'Confirmar venda',
            icon: <CheckCircleOutlineOutlinedIcon />,
            run: (currentOrder) => storefrontSaleService.approveStorefrontSale(currentOrder.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          label: 'Aprovar venda',
          icon: <CheckCircleOutlineOutlinedIcon />,
          run: (currentOrder) => saleService.approveSale(currentOrder.uuid),
        }
      }

      if (stage === 'confirmed') {
        if (order.origin === 'storefront') {
          if (!canDeliverStorefrontOrder) return null
          return {
            label: 'Marcar entregue',
            icon: <CheckCircleOutlineOutlinedIcon />,
            run: (currentOrder) => storefrontSaleService.deliverStorefrontSale(currentOrder.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          label: 'Marcar entregue',
          icon: <CheckCircleOutlineOutlinedIcon />,
          run: (currentOrder) => saleService.deliverSale(currentOrder.uuid),
        }
      }

      if (stage === 'financial_pending') {
        if (order.origin === 'storefront') {
          if (!canPayStorefrontOrder) return null
          return {
            label: 'Concluir venda',
            icon: <PaymentsOutlinedIcon />,
            run: (currentOrder) => storefrontSaleService.payStorefrontSale(currentOrder.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          label: 'Marcar pago',
          icon: <PaymentsOutlinedIcon />,
          run: (currentOrder) => saleService.paySale(currentOrder.uuid),
        }
      }

      return null
    },
    [
      canApproveStorefrontOrder,
      canDeliverStorefrontOrder,
      canPayStorefrontOrder,
      canUpdateOrders,
    ],
  )

  const handleQuickAction = useCallback(
    async (order: Sale) => {
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

  const resolveSaleBoardAction = useCallback(
    (order: Sale, target: SaleBoardDropTarget): ResolvedSaleBoardAction | null => {
      const currentStage = deriveOperationStage(order)
      if (!currentStage) return null
      if (target === currentStage) return null

      if (target === 'confirmed') {
        if (currentStage === 'approval') {
          if (order.origin === 'storefront') {
            if (!canApproveStorefrontOrder) return null
            return {
              title: 'Aprovar venda',
              description: 'A venda entrará na fila de confirmadas.',
              confirmLabel: 'Aprovar venda',
              requiresReason: false,
              execute: () => storefrontSaleService.approveStorefrontSale(order.uuid),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Aprovar venda',
            description: 'A venda entrará na fila de confirmadas.',
            confirmLabel: 'Aprovar venda',
            requiresReason: false,
            execute: () => saleService.approveSale(order.uuid),
          }
        }

        if (currentStage === 'financial_pending') {
          if (order.origin === 'storefront') {
            if (!canUndeliverStorefrontOrder) return null
            return {
              title: 'Voltar para confirmado',
              description: 'A venda deixará de constar como entregue e voltará para a fila de confirmadas.',
              confirmLabel: 'Voltar para confirmado',
              requiresReason: false,
              execute: () => storefrontSaleService.undeliverStorefrontSale(order.uuid),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Voltar para confirmado',
            description: 'A venda deixará de constar como entregue e voltará para a fila de confirmadas.',
            confirmLabel: 'Voltar para confirmado',
            requiresReason: false,
            execute: () => saleService.undeliverSale(order.uuid),
          }
        }
      }

      if (target === 'financial_pending' && currentStage === 'confirmed') {
        if (order.origin === 'storefront') {
          if (!canDeliverStorefrontOrder) return null
          return {
            title: 'Marcar como entregue',
            description: 'A venda será marcada como entregue e seguirá para a fila financeira, se ainda não estiver paga.',
            confirmLabel: 'Marcar como entregue',
            requiresReason: false,
            execute: () => storefrontSaleService.deliverStorefrontSale(order.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          title: 'Marcar como entregue',
          description: 'A venda será marcada como entregue e seguirá para a fila financeira, se ainda não estiver paga.',
          confirmLabel: 'Marcar como entregue',
          requiresReason: false,
          execute: () => saleService.deliverSale(order.uuid),
        }
      }

      if (target === 'cancel') {
        if (currentStage === 'approval') {
          if (order.origin === 'storefront') {
            if (!canApproveStorefrontOrder) return null
            return {
              title: 'Recusar venda',
              description: 'Informe o motivo da recusa. A venda será retirada da fila operacional.',
              confirmLabel: 'Recusar venda',
              requiresReason: true,
              execute: (reason) => storefrontSaleService.rejectStorefrontSale(order.uuid, reason),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Recusar venda',
            description: 'Informe o motivo da recusa. A venda será retirada da fila operacional.',
            confirmLabel: 'Recusar venda',
            requiresReason: true,
            execute: (reason) => saleService.rejectSale(order.uuid, reason),
          }
        }

        if (currentStage === 'confirmed') {
          if (order.origin === 'storefront') {
            if (!canCancelStorefrontOrder) return null
            return {
              title: 'Cancelar venda',
              description: 'Informe o motivo do cancelamento. Esse registro ficará salvo no histórico operacional.',
              confirmLabel: 'Cancelar venda',
              requiresReason: true,
              execute: (reason) => storefrontSaleService.cancelStorefrontSale(order.uuid, reason),
            }
          }

          if (!canUpdateOrders) return null
          return {
            title: 'Cancelar venda',
            description: 'Informe o motivo do cancelamento. Esse registro ficará salvo no histórico operacional.',
            confirmLabel: 'Cancelar venda',
            requiresReason: true,
            execute: (reason) => saleService.cancelSale(order.uuid, reason),
          }
        }
      }

      if (target === 'complete' && currentStage === 'financial_pending') {
        if (order.origin === 'storefront') {
          if (!canPayStorefrontOrder) return null
          return {
            title: 'Concluir venda',
            description: 'A venda será marcada como paga e sairá da fila operacional.',
            confirmLabel: 'Concluir venda',
            requiresReason: false,
            execute: () => storefrontSaleService.payStorefrontSale(order.uuid),
          }
        }

        if (!canUpdateOrders) return null
        return {
          title: 'Baixar recebimento',
          description: 'A venda será marcada como paga e sairá da fila operacional.',
          confirmLabel: 'Baixar recebimento',
          requiresReason: false,
          execute: () => saleService.paySale(order.uuid),
        }
      }

      return null
    },
    [
      canApproveStorefrontOrder,
      canCancelStorefrontOrder,
      canDeliverStorefrontOrder,
      canPayStorefrontOrder,
      canUndeliverStorefrontOrder,
      canUpdateOrders,
    ],
  )

  const executeSaleBoardAction = useCallback(
    async (orderUuid: string, action: ResolvedSaleBoardAction, reason = '') => {
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

  const handleSaleBoardDrop = useCallback(
    async (order: Sale, target: SaleBoardDropTarget) => {
      const action = resolveSaleBoardAction(order, target)
      if (!action) return

      if (action.requiresReason) {
        setPendingOrderBoardAction({ ...action, orderUuid: order.uuid })
        return
      }

      await executeSaleBoardAction(order.uuid, action)
    },
    [executeSaleBoardAction, resolveSaleBoardAction],
  )

  const handleSaleCardDragStart = useCallback((orderUuid: string, event: DragEvent<HTMLDivElement>) => {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', orderUuid)
    setDraggedOrderUuid(orderUuid)
    setActiveOrderDropTarget(null)
    setQuickActionError(null)
  }, [])

  const handleSaleCardDragEnd = useCallback(() => {
    setDraggedOrderUuid(null)
    setActiveOrderDropTarget(null)
  }, [])

  const markSaleDropTarget = useCallback(
    (event: DragEvent<HTMLDivElement>, target: SaleBoardDropTarget) => {
      event.preventDefault()
      if (!draggedPreviewSale) return
      const action = resolveSaleBoardAction(draggedPreviewSale, target)
      if (!action) return
      setActiveOrderDropTarget(target)
      event.dataTransfer.dropEffect = 'move'
    },
    [draggedPreviewSale, resolveSaleBoardAction],
  )

  const clearSaleDropTarget = useCallback(() => {
    setActiveOrderDropTarget(null)
  }, [])

  const receiveSaleDrop = useCallback(
    async (event: DragEvent<HTMLDivElement>, target: SaleBoardDropTarget) => {
      event.preventDefault()
      const orderUuid = event.dataTransfer.getData('text/plain') || draggedSaleUuid
      const order = orderUuid ? previewSaleMap.get(orderUuid) : null
      if (!order) {
        setDraggedOrderUuid(null)
        setActiveOrderDropTarget(null)
        return
      }

      await handleSaleBoardDrop(order, target)
    },
    [draggedSaleUuid, handleSaleBoardDrop, previewSaleMap],
  )

  const columns = useMemo<ServerGridColumn<Sale>[]>(
    () => [
      ...(!isManualOrdersPage
        ? [{
            field: 'origin',
            headerName: 'Canal',
            width: 120,
            sortable: false,
            filterType: 'none',
            cellRenderer: (row: Sale) => {
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
            exportValue: (row: Sale) => ORIGIN_META[row.origin]?.label ?? row.origin,
          } satisfies ServerGridColumn<Sale>]
        : []),
      ...(!isManualOrdersPage
        ? [{
            field: 'operation_stage',
            headerName: 'Fila',
            width: 170,
            sortable: false,
            filterType: 'none',
            cellRenderer: (row: Sale) => {
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
            exportValue: (row: Sale) => {
              const stage = deriveOperationStage(row)
              return stage ? STAGE_META[stage].label : 'Concluído / fora da fila'
            },
          } satisfies ServerGridColumn<Sale>]
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
            cellRenderer: (row: Sale) => <SaleStatusBadge order={row} />,
            exportValue: (row: Sale) => deriveSaleStatus({
              is_cancelled: Boolean(row.cancelled_at),
              is_paid: row.is_paid,
              is_delivered: row.is_delivered,
              is_installment: row.is_installment,
              delivered_at: row.delivered_at,
              paid_at: row.paid_at,
              status: row.status,
            }).label,
          } satisfies ServerGridColumn<Sale>]
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
            <Tooltip title="Abrir venda" arrow>
              <IconButton
                size="small"
                aria-label={`Abrir venda do cliente ${row.final_customer?.name ?? ''}`}
                onClick={() => setSelectedOrderUuid(row.uuid)}
                sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
              >
                <VisibilityOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title="Histórico operacional" arrow>
              <IconButton
                size="small"
                aria-label={`Ver histórico operacional da venda ${row.codigo}`}
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
          gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', xl: 'repeat(6, 1fr)' },
          gap: 1.25,
        }}
      >
        <OperationMetricCard
          label="Vendas em andamento"
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
          label="Confirmado"
          value={snapshot.confirmedTotal === null ? '—' : String(snapshot.confirmedTotal)}
          caption="Vendas confirmadas que ainda não foram concluídas."
          accent={STAGE_META.confirmed.accent}
          action={
            <Button
              size="small"
              variant="text"
              onClick={() => {
                setStageFilter('confirmed')
                setStatusFilter('all')
                gridApiRef.current?.refreshInfiniteCache()
              }}
            >
              Filtrar confirmados
            </Button>
          }
        />
        <OperationMetricCard
          label="Financeiro pendente"
          value={snapshot.financialPendingTotal === null ? '—' : String(snapshot.financialPendingTotal)}
          caption="Vendas já concluídas que ainda aguardam recebimento total."
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
          caption="Vendas nas filas superiores que já passaram do limite mais crítico do recorte."
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
          caption="Vendas que ainda não estouraram, mas já merecem entrar no foco da operação."
          accent="var(--pt-warning)"
        />
      </Box>

      {queuePrioritySummary.critical > 0 ? (
        <Alert severity="error" variant="outlined">
          Existem {queuePrioritySummary.critical} venda(s) pedindo ação imediata na operação. Priorize os cards destacados em vermelho nas filas acima.
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
              gridTemplateColumns: { xs: '1fr', md: 'repeat(3, 1fr)' },
              gap: 1,
            }}
          >
            {(['store', 'operations', 'finance'] as OperationOwner[]).map((owner) => {
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
                      <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>Atue diretamente pelas vendas desta fila.</Typography>
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
          gridTemplateColumns: { xs: '1fr', xl: 'minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) 280px' },
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
          isActiveDrop={activeSaleDropTarget === 'approval'}
          onDragOver={(event) => markSaleDropTarget(event, 'approval')}
          onDragLeave={clearSaleDropTarget}
          onDrop={(event) => void receiveSaleDrop(event, 'approval')}
          emptyMessage="Arraste para cá apenas se o card puder voltar para esta etapa."
        >
          {queuePreview.approval.length === 0 ? (
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhuma venda nesta fila agora.</Typography>
          ) : (
            queuePreview.approval.map((order) => (
              <QueueSaleCard
                key={order.uuid}
                order={order}
                onOpen={setSelectedOrderUuid}
                onOpenTimeline={setSelectedTimelineOrderUuid}
                quickAction={resolveQuickAction(order)}
                onQuickAction={(currentOrder) => void handleQuickAction(currentOrder)}
                isSubmitting={submittingSaleUuid === order.uuid}
                draggable
                isDragging={draggedSaleUuid === order.uuid}
                onDragStart={(event) => handleSaleCardDragStart(order.uuid, event)}
                onDragEnd={handleSaleCardDragEnd}
              />
            ))
          )}
        </WorkflowBoardColumn>

        <WorkflowBoardColumn
          title={STAGE_META.confirmed.label}
          caption={STAGE_META.confirmed.caption}
          accent={STAGE_META.confirmed.accent}
          countLabel={snapshot.confirmedTotal === null ? '—' : `${snapshot.confirmedTotal} na fila`}
          onOpenQueue={() => {
            setStageFilter('confirmed')
            setStatusFilter('all')
            setActiveOnly(true)
            gridApiRef.current?.refreshInfiniteCache()
          }}
          isActiveDrop={activeSaleDropTarget === 'confirmed'}
          onDragOver={(event) => markSaleDropTarget(event, 'confirmed')}
          onDragLeave={clearSaleDropTarget}
          onDrop={(event) => void receiveSaleDrop(event, 'confirmed')}
          emptyMessage="Use esta coluna para vendas aprovadas que ainda estão sendo preparadas ou aguardando conclusão."
        >
          {queuePreview.confirmed.length === 0 ? (
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhuma venda nesta fila agora.</Typography>
          ) : (
            queuePreview.confirmed.map((order) => (
              <QueueSaleCard
                key={order.uuid}
                order={order}
                onOpen={setSelectedOrderUuid}
                onOpenTimeline={setSelectedTimelineOrderUuid}
                quickAction={resolveQuickAction(order)}
                onQuickAction={(currentOrder) => void handleQuickAction(currentOrder)}
                isSubmitting={submittingSaleUuid === order.uuid}
                draggable
                isDragging={draggedSaleUuid === order.uuid}
                onDragStart={(event) => handleSaleCardDragStart(order.uuid, event)}
                onDragEnd={handleSaleCardDragEnd}
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
          isActiveDrop={activeSaleDropTarget === 'financial_pending'}
          onDragOver={(event) => markSaleDropTarget(event, 'financial_pending')}
          onDragLeave={clearSaleDropTarget}
          onDrop={(event) => void receiveSaleDrop(event, 'financial_pending')}
          emptyMessage="Arraste para cá quando a venda já estiver concluída e faltar só a baixa financeira."
        >
          {queuePreview.financial_pending.length === 0 ? (
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>Nenhuma venda nesta fila agora.</Typography>
          ) : (
            queuePreview.financial_pending.map((order) => (
              <QueueSaleCard
                key={order.uuid}
                order={order}
                onOpen={setSelectedOrderUuid}
                onOpenTimeline={setSelectedTimelineOrderUuid}
                quickAction={resolveQuickAction(order)}
                onQuickAction={(currentOrder) => void handleQuickAction(currentOrder)}
                isSubmitting={submittingSaleUuid === order.uuid}
                draggable
                isDragging={draggedSaleUuid === order.uuid}
                onDragStart={(event) => handleSaleCardDragStart(order.uuid, event)}
                onDragEnd={handleSaleCardDragEnd}
              />
            ))
          )}
        </WorkflowBoardColumn>

        <Stack spacing={1.25}>
          <WorkflowActionDropZone
            title="Cancelar / recusar"
            description="Use esta área para recusar vendas em aprovação ou cancelar vendas já em operação. O motivo é obrigatório."
            accent="var(--pt-danger)"
            icon={<CancelOutlinedIcon fontSize="small" />}
            isActiveDrop={activeSaleDropTarget === 'cancel'}
            isDisabled={!draggedPreviewSale || !resolveSaleBoardAction(draggedPreviewSale, 'cancel')}
            onDragOver={(event) => markSaleDropTarget(event, 'cancel')}
            onDragLeave={clearSaleDropTarget}
            onDrop={(event) => void receiveSaleDrop(event, 'cancel')}
          />
          <WorkflowActionDropZone
            title="Concluir / baixar"
            description="Solte aqui as vendas da fila financeira para marcar pagamento e concluir o fluxo operacional."
            accent="var(--pt-success)"
            icon={<PaymentsOutlinedIcon fontSize="small" />}
            isActiveDrop={activeSaleDropTarget === 'complete'}
            isDisabled={!draggedPreviewSale || !resolveSaleBoardAction(draggedPreviewSale, 'complete')}
            onDragOver={(event) => markSaleDropTarget(event, 'complete')}
            onDragLeave={clearSaleDropTarget}
            onDrop={(event) => void receiveSaleDrop(event, 'complete')}
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
      title="Vendas manuais"
      subtitle="Gerencie as vendas lançadas manualmente pela equipe."
      createLabel="Nova venda"
      canCreate={can(ACCESS.salesCreate)}
      onCreate={() => navigate('/vendas/nova')}
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
            exportFileName="vendas-manuais"
            onGridReady={(api) => {
              gridApiRef.current = api
            }}
            emptyState={{
              icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
              title: 'Nenhuma venda manual encontrada',
              description: 'Assim que a equipe criar vendas manualmente, elas aparecerão aqui.',
              action: can(ACCESS.salesCreate) ? (
                <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/vendas/nova')}>
                  Criar primeira venda
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
          subtitle="Acompanhe a fila canônica de vendas do sistema por canal, etapa e urgência."
          createLabel="Nova venda"
          canCreate={can(ACCESS.salesCreate)}
          onCreate={() => navigate('/vendas/nova')}
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
                  confirmed: 'Confirmado',
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
                  title: activeOnly ? 'Nenhuma venda em andamento neste recorte' : 'Nenhuma venda encontrada',
                  description: activeOnly
                    ? 'Quando houver novas vendas para operar, elas aparecerão aqui independente do canal de entrada.'
                    : 'Ajuste os filtros acima ou crie a primeira venda para iniciar a operação.',
                  action: can(ACCESS.salesCreate) ? (
                    <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/vendas/nova')}>
                      Criar primeira venda
                    </Button>
                  ) : undefined,
                }}
              />
            </Box>
          </Box>
        </CrudListPage>
      )}

      <SaleDetailDialog
        saleUuid={selectedSaleUuid}
        open={selectedSaleUuid !== null}
        onClose={() => setSelectedOrderUuid(null)}
        onChanged={() => {
          gridApiRef.current?.refreshInfiniteCache()
          if (!isManualOrdersPage) {
            void refreshOperationData()
          }
        }}
      />

      <WorkflowReasonDialog
        open={pendingSaleBoardAction !== null}
        title={pendingSaleBoardAction?.title ?? 'Registrar motivo'}
        description={pendingSaleBoardAction?.description ?? ''}
        confirmLabel={pendingSaleBoardAction?.confirmLabel ?? 'Confirmar'}
        loading={boardActionSubmitting}
        onClose={() => {
          if (boardActionSubmitting) return
          setPendingOrderBoardAction(null)
          setDraggedOrderUuid(null)
          setActiveOrderDropTarget(null)
        }}
        onConfirm={(reason) => {
          if (!pendingSaleBoardAction?.orderUuid) return
          void executeSaleBoardAction(pendingSaleBoardAction.orderUuid, pendingSaleBoardAction, reason)
        }}
      />

      <WorkflowTimelineDialog
        open={selectedTimelineSaleUuid !== null}
        title="Histórico operacional da venda"
        subjectLabel={selectedTimelineSaleUuid ? `venda ${selectedTimelineSaleUuid}` : 'venda'}
        loader={() => (selectedTimelineSaleUuid ? workflowService.getSaleWorkflowTimeline(selectedTimelineSaleUuid) : Promise.resolve([]))}
        stageLabel={(stage) => {
          if (!stage) return 'Sem etapa'
          return STAGE_META[stage as SaleOperationStage]?.label ?? stage
        }}
        onClose={() => setSelectedTimelineOrderUuid(null)}
      />
    </>
  )
}
