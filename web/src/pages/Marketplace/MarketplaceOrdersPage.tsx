import AutorenewOutlinedIcon from '@mui/icons-material/AutorenewOutlined'
import CheckCircleOutlineOutlinedIcon from '@mui/icons-material/CheckCircleOutlineOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import PlayArrowOutlinedIcon from '@mui/icons-material/PlayArrowOutlined'
import ReplayOutlinedIcon from '@mui/icons-material/ReplayOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import RefreshOutlinedIcon from '@mui/icons-material/RefreshOutlined'
import SyncOutlinedIcon from '@mui/icons-material/SyncOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
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
  MenuItem,
  Paper,
  Snackbar,
  Stack,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link as RouterLink, useSearchParams } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { EmptyState } from '../../components/layout/EmptyState'
import { OrderDetailDialog } from '../../components/order/OrderDetailDialog'
import { useAccessControl } from '../../hooks/useAccessControl'
import * as marketplaceService from '../../services/marketplaceService'
import { PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { MarketplaceCancellationReason, MarketplaceIntegration, MarketplaceOperationsSummary, MarketplaceOrder } from '../../types/marketplace'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'

const QUEUE_OPTIONS: Array<{ value: MarketplaceOrder['queue_status'] | ''; label: string }> = [
  { value: '', label: 'Todas as filas' },
  { value: 'pending_import', label: 'Aguardando importação' },
  { value: 'import_error', label: 'Com erro de importação' },
  { value: 'imported', label: 'Importado' },
]

const ACTION_OPTIONS: Array<{ value: 'confirm' | 'startPreparation' | 'readyToPickup' | 'dispatch'; label: string }> = [
  { value: 'confirm', label: 'Confirmar no iFood' },
  { value: 'startPreparation', label: 'Iniciar preparo' },
  { value: 'readyToPickup', label: 'Marcar como pronto' },
  { value: 'dispatch', label: 'Despachar pedido' },
]

const MARKETPLACE_FOCUS_OPTIONS = [
  { value: 'all', label: 'Visão completa' },
  { value: 'pending_critical', label: 'Pendentes críticos' },
  { value: 'import_error', label: 'Erros de importação' },
  { value: 'stale_imported', label: 'Importados sem sinal' },
] as const

type MarketplaceFocusFilter = (typeof MARKETPLACE_FOCUS_OPTIONS)[number]['value']

function queueStatusChip(status: MarketplaceOrder['queue_status']) {
  if (status === 'imported') return <Chip size="small" color="success" variant="outlined" label="Importado" />
  if (status === 'import_error') return <Chip size="small" color="error" variant="outlined" label="Erro de importação" />
  return <Chip size="small" color="warning" variant="outlined" label="Aguardando importação" />
}

function stringifyPayload(value: unknown): string {
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return 'Não foi possível renderizar o payload.'
  }
}

function formatOptionalDateTime(value: string | null | undefined): string {
  return value ? formatDateTimeBR(value) : '—'
}

type MarketplaceSlaTone = 'ok' | 'attention' | 'critical'

interface MarketplaceTimelineEntry {
  id: string
  type: 'event' | 'action' | 'import'
  title: string
  subtitle: string
  occurredAt: string | null
  status: string
  tone: MarketplaceSlaTone
  errorMessage?: string | null
  retryEventUuid?: string | null
}

function minutesSince(value: string | null | undefined): number | null {
  if (!value) return null
  const timestamp = new Date(value).getTime()
  if (Number.isNaN(timestamp)) return null
  return Math.max(0, Math.floor((Date.now() - timestamp) / 60000))
}

function formatElapsed(minutes: number | null): string {
  if (minutes === null) return 'Sem referência'
  if (minutes < 60) return `${minutes} min`
  const hours = Math.floor(minutes / 60)
  const remainingMinutes = minutes % 60
  if (hours < 24) return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}min` : `${hours}h`
  const days = Math.floor(hours / 24)
  const remainingHours = hours % 24
  return remainingHours > 0 ? `${days}d ${remainingHours}h` : `${days}d`
}

function slaMeta(tone: MarketplaceSlaTone): { label: string; color: 'success' | 'warning' | 'error' } {
  if (tone === 'critical') return { label: 'Crítico', color: 'error' }
  if (tone === 'attention') return { label: 'Atenção', color: 'warning' }
  return { label: 'Dentro do esperado', color: 'success' }
}

function deriveMarketplaceOrderSla(order: MarketplaceOrder): { tone: MarketplaceSlaTone; label: string; detail: string } {
  if (order.queue_status === 'import_error') {
    const age = minutesSince(order.last_synced_at ?? order.latest_event_at ?? order.raw_updated_at)
    return {
      tone: 'critical',
      label: 'Erro de importação',
      detail: age === null ? 'Pedido com falha aberta.' : `Falha em aberto há ${formatElapsed(age)}.`,
    }
  }

  if (order.queue_status === 'pending_import') {
    const age = minutesSince(order.latest_event_at ?? order.last_synced_at ?? order.raw_updated_at)
    if (age !== null && age >= 15) {
      return { tone: 'critical', label: 'Importação atrasada', detail: `Sem conversão interna há ${formatElapsed(age)}.` }
    }
    if (age !== null && age >= 5) {
      return { tone: 'attention', label: 'Aguardando importação', detail: `Pedido aguardando há ${formatElapsed(age)}.` }
    }
    return { tone: 'ok', label: 'Fila recente', detail: age === null ? 'Pedido recebido recentemente.' : `Recebido há ${formatElapsed(age)}.` }
  }

  const age = minutesSince(order.latest_event_at ?? order.last_synced_at)
  if (age !== null && age >= 60) {
    return { tone: 'attention', label: 'Sem atualização recente', detail: `Último sinal há ${formatElapsed(age)}.` }
  }

  return {
    tone: 'ok',
    label: 'Fluxo acompanhado',
    detail: age === null ? 'Pedido interno já vinculado.' : `Último sinal há ${formatElapsed(age)}.`,
  }
}

function buildMarketplaceTimeline(order: MarketplaceOrder): MarketplaceTimelineEntry[] {
  const entries: MarketplaceTimelineEntry[] = []

  for (const event of order.events ?? []) {
    entries.push({
      id: `event:${event.uuid}`,
      type: 'event',
      title: event.event_full_code ?? event.event_type,
      subtitle: `Evento recebido${event.processing_attempts > 0 ? ` • tentativas: ${event.processing_attempts}` : ''}`,
      occurredAt: event.occurred_at ?? event.processed_at ?? event.last_attempted_at,
      status: event.acknowledged_at ? 'Confirmado' : event.status,
      tone: event.dead_lettered_at || event.status === 'failed' ? 'critical' : event.acknowledged_at ? 'ok' : 'attention',
      errorMessage: event.error_message,
      retryEventUuid: event.status === 'failed' || event.dead_lettered_at ? event.uuid : null,
    })
  }

  for (const action of order.actions ?? []) {
    entries.push({
      id: `action:${action.uuid}`,
      type: 'action',
      title: `Ação enviada: ${action.action}`,
      subtitle: 'Comando operacional disparado pela empresa para o iFood.',
      occurredAt: action.executed_at,
      status: action.status,
      tone: action.status === 'failed' ? 'critical' : action.status === 'pending' ? 'attention' : 'ok',
      errorMessage: action.error_message,
    })
  }

  if (order.imported_at || order.internal_order) {
    entries.push({
      id: `import:${order.uuid}`,
      type: 'import',
      title: 'Pedido convertido para o fluxo interno',
      subtitle: order.internal_order?.codigo
        ? `Vinculado ao pedido interno ${order.internal_order.codigo}.`
        : 'Vinculado ao fluxo interno da empresa.',
      occurredAt: order.imported_at,
      status: 'Importado',
      tone: 'ok',
    })
  }

  return entries.sort((left, right) => {
    const leftTime = left.occurredAt ? new Date(left.occurredAt).getTime() : 0
    const rightTime = right.occurredAt ? new Date(right.occurredAt).getTime() : 0
    return rightTime - leftTime
  })
}

export function MarketplaceOrdersPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { can } = useAccessControl()
  const gridApiRef = useRef<GridApi | null>(null)
  const canUpdate = can(ACCESS.apiAccessUpdate)
  const queueStatusFromQuery = searchParams.get('queue_status')
  const focusFromQuery = searchParams.get('focus')
  const sourceFromQuery = searchParams.get('source')
  const isQueueStatusFromQueryValid = queueStatusFromQuery === 'pending_import'
    || queueStatusFromQuery === 'import_error'
    || queueStatusFromQuery === 'imported'
  const isFocusFromQueryValid = focusFromQuery === 'pending_critical'
    || focusFromQuery === 'import_error'
    || focusFromQuery === 'stale_imported'
  const openedFromDashboard = sourceFromQuery === 'dashboard'

  const [integrations, setIntegrations] = useState<MarketplaceIntegration[]>([])
  const [selectedIntegrationUuid, setSelectedIntegrationUuid] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [queueStatus, setQueueStatus] = useState<MarketplaceOrder['queue_status'] | ''>(
    isQueueStatusFromQueryValid ? queueStatusFromQuery : '',
  )
  const [focusFilter, setFocusFilter] = useState<MarketplaceFocusFilter>(isFocusFromQueryValid ? focusFromQuery : 'all')
  const [isLoadingIntegrations, setIsLoadingIntegrations] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [operationsSummary, setOperationsSummary] = useState<MarketplaceOperationsSummary | null>(null)

  const [selectedOrderUuid, setSelectedOrderUuid] = useState<string | null>(null)
  const [selectedOrder, setSelectedOrder] = useState<MarketplaceOrder | null>(null)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [detailError, setDetailError] = useState<string | null>(null)
  const [selectedInternalOrderUuid, setSelectedInternalOrderUuid] = useState<string | null>(null)
  const [cancelDialogOpen, setCancelDialogOpen] = useState(false)
  const [cancellationReasons, setCancellationReasons] = useState<MarketplaceCancellationReason[]>([])
  const [selectedCancellationCode, setSelectedCancellationCode] = useState('')
  const [cancelReasonText, setCancelReasonText] = useState('')
  const [isLoadingCancellationReasons, setIsLoadingCancellationReasons] = useState(false)
  const [processingAction, setProcessingAction] = useState<string | null>(null)
  const [feedback, setFeedback] = useState<{ severity: 'success' | 'error'; message: string } | null>(null)

  const selectedIntegration = integrations.find((item) => item.uuid === selectedIntegrationUuid) ?? null

  const loadIntegrations = useCallback(async () => {
    setIsLoadingIntegrations(true)
    setLoadError(null)

    try {
      const list = await marketplaceService.listMarketplaceIntegrations()
      setIntegrations(list)
      setSelectedIntegrationUuid((current) => current || list[0]?.uuid || '')
    } catch (error) {
      setLoadError(getApiErrorMessage(error, 'Não foi possível carregar as integrações de delivery agora.'))
    } finally {
      setIsLoadingIntegrations(false)
    }
  }, [])

  useEffect(() => {
    void loadIntegrations()
  }, [loadIntegrations])

  useEffect(() => {
    if (!selectedIntegrationUuid) {
      setOperationsSummary(null)
      return
    }

    let cancelled = false
    marketplaceService
      .getMarketplaceOperationsSummary(selectedIntegrationUuid)
      .then((summary) => {
        if (!cancelled) setOperationsSummary(summary)
      })
      .catch(() => {
        if (!cancelled) setOperationsSummary(null)
      })

    return () => {
      cancelled = true
    }
  }, [selectedIntegrationUuid])

  useEffect(() => {
    const nextQueueStatus = isQueueStatusFromQueryValid ? queueStatusFromQuery : ''
    const nextFocus = isFocusFromQueryValid ? focusFromQuery : 'all'

    setQueueStatus((current) => (current === nextQueueStatus ? current : nextQueueStatus))
    setFocusFilter((current) => (current === nextFocus ? current : nextFocus))
  }, [focusFromQuery, isFocusFromQueryValid, isQueueStatusFromQueryValid, queueStatusFromQuery])

  const fetchPage = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<MarketplaceOrder>> => {
      if (!selectedIntegrationUuid) return { rows: [], total: 0 }
      const result = await marketplaceService.listMarketplaceOrdersPage(selectedIntegrationUuid, {
        page,
        per_page: perPage,
        search: search.trim() || undefined,
        status: status.trim() || undefined,
        queue_status: queueStatus || undefined,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [queueStatus, search, selectedIntegrationUuid, status],
  )

  const focusSummaryText = useMemo(() => {
    if (focusFilter === 'pending_critical') {
      return 'A tela foi aberta focando pedidos pendentes que já estouraram o SLA crítico de importação.'
    }
    if (focusFilter === 'import_error') {
      return 'A tela foi aberta focando pedidos com falha de importação para tratamento imediato.'
    }
    if (focusFilter === 'stale_imported') {
      return 'A tela foi aberta focando pedidos já importados que estão sem novo sinal recente da integração.'
    }
    return null
  }, [focusFilter])

  const loadOrderDetail = useCallback(async (uuid: string) => {
    setIsDetailLoading(true)
    setDetailError(null)
    try {
      const order = await marketplaceService.getMarketplaceOrder(uuid)
      setSelectedOrder(order)
    } catch (error) {
      setDetailError(getApiErrorMessage(error, 'Não foi possível carregar os detalhes do pedido externo agora.'))
    } finally {
      setIsDetailLoading(false)
    }
  }, [])

  useEffect(() => {
    if (!selectedOrderUuid) {
      setSelectedOrder(null)
      setDetailError(null)
      return
    }

    void loadOrderDetail(selectedOrderUuid)
  }, [loadOrderDetail, selectedOrderUuid])

  const handleRefreshExternal = useCallback(async (orderUuid: string) => {
    setProcessingAction(`refresh:${orderUuid}`)
    try {
      const order = await marketplaceService.refreshMarketplaceOrder(orderUuid)
      if (selectedOrderUuid === orderUuid) setSelectedOrder(order)
      gridApiRef.current?.refreshInfiniteCache()
      setFeedback({ severity: 'success', message: 'Pedido externo sincronizado novamente com sucesso.' })
    } catch (error) {
      setFeedback({ severity: 'error', message: getApiErrorMessage(error, 'Não foi possível sincronizar este pedido externo agora.') })
    } finally {
      setProcessingAction(null)
    }
  }, [selectedOrderUuid])

  const handleImport = useCallback(async (orderUuid: string) => {
    setProcessingAction(`import:${orderUuid}`)
    try {
      const order = await marketplaceService.importMarketplaceOrder(orderUuid)
      if (selectedOrderUuid === orderUuid) setSelectedOrder(order)
      gridApiRef.current?.refreshInfiniteCache()
      if (selectedIntegrationUuid) setOperationsSummary(await marketplaceService.getMarketplaceOperationsSummary(selectedIntegrationUuid))
      setFeedback({ severity: 'success', message: 'Pedido externo importado com sucesso para o fluxo interno.' })
    } catch (error) {
      setFeedback({ severity: 'error', message: getApiErrorMessage(error, 'Não foi possível importar este pedido externo agora.') })
    } finally {
      setProcessingAction(null)
    }
  }, [selectedIntegrationUuid, selectedOrderUuid])

  async function handleSendAction(orderUuid: string, action: 'confirm' | 'startPreparation' | 'readyToPickup' | 'dispatch') {
    setProcessingAction(`${action}:${orderUuid}`)
    try {
      await marketplaceService.performMarketplaceOrderAction(orderUuid, { action })
      await loadOrderDetail(orderUuid)
      gridApiRef.current?.refreshInfiniteCache()
      if (selectedIntegrationUuid) setOperationsSummary(await marketplaceService.getMarketplaceOperationsSummary(selectedIntegrationUuid))
      setFeedback({ severity: 'success', message: 'Ação enviada ao iFood com sucesso.' })
    } catch (error) {
      setFeedback({ severity: 'error', message: getApiErrorMessage(error, 'Não foi possível enviar esta ação para o iFood agora.') })
    } finally {
      setProcessingAction(null)
    }
  }

  async function handleRetryEvent(eventUuid: string) {
    setProcessingAction(`retry-event:${eventUuid}`)
    try {
      await marketplaceService.retryMarketplaceEvent(eventUuid)
      if (selectedOrderUuid) await loadOrderDetail(selectedOrderUuid)
      gridApiRef.current?.refreshInfiniteCache()
      if (selectedIntegrationUuid) setOperationsSummary(await marketplaceService.getMarketplaceOperationsSummary(selectedIntegrationUuid))
      setFeedback({ severity: 'success', message: 'Evento reenfileirado e reprocessado com sucesso.' })
    } catch (error) {
      setFeedback({ severity: 'error', message: getApiErrorMessage(error, 'Não foi possível reprocessar este evento agora.') })
    } finally {
      setProcessingAction(null)
    }
  }

  async function openCancelDialog() {
    if (!selectedOrder) return
    setCancelDialogOpen(true)
    setSelectedCancellationCode('')
    setCancelReasonText('')
    setIsLoadingCancellationReasons(true)
    try {
      const reasons = await marketplaceService.listMarketplaceCancellationReasons(selectedOrder.uuid)
      setCancellationReasons(reasons)
      if (reasons[0]) {
        setSelectedCancellationCode(reasons[0].code)
        setCancelReasonText(reasons[0].description)
      }
    } catch (error) {
      setFeedback({ severity: 'error', message: getApiErrorMessage(error, 'Não foi possível carregar os motivos de cancelamento agora.') })
      setCancelDialogOpen(false)
    } finally {
      setIsLoadingCancellationReasons(false)
    }
  }

  async function handleCancelOrder() {
    if (!selectedOrder || !selectedCancellationCode) return
    setProcessingAction(`cancel:${selectedOrder.uuid}`)
    try {
      await marketplaceService.performMarketplaceOrderAction(selectedOrder.uuid, {
        action: 'cancel',
        code: selectedCancellationCode,
        reason: cancelReasonText.trim() || undefined,
      })
      await loadOrderDetail(selectedOrder.uuid)
      gridApiRef.current?.refreshInfiniteCache()
      if (selectedIntegrationUuid) setOperationsSummary(await marketplaceService.getMarketplaceOperationsSummary(selectedIntegrationUuid))
      setCancelDialogOpen(false)
      setFeedback({ severity: 'success', message: 'Solicitação de cancelamento enviada ao iFood com sucesso.' })
    } catch (error) {
      setFeedback({ severity: 'error', message: getApiErrorMessage(error, 'Não foi possível solicitar o cancelamento no iFood agora.') })
    } finally {
      setProcessingAction(null)
    }
  }

  const columns = useMemo<ServerGridColumn<MarketplaceOrder>[]>(
    () => [
      {
        field: 'last_synced_at',
        headerName: 'Última sincronização',
        width: 175,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => formatOptionalDateTime(row.last_synced_at),
      },
      {
        field: 'merchant',
        headerName: 'Loja externa',
        width: 180,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => row.merchant?.name ?? '—',
      },
      {
        field: 'display_id',
        headerName: 'Pedido externo',
        width: 140,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => row.display_id ?? row.order_number ?? row.external_id,
      },
      {
        field: 'customer_name',
        headerName: 'Cliente',
        filterType: 'none',
        cellRenderer: (row) => row.customer_name ?? 'Cliente não informado',
      },
      {
        field: 'status',
        headerName: 'Status no iFood',
        width: 160,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => row.status ?? '—',
      },
      {
        field: 'queue_status',
        headerName: 'Fila interna',
        width: 175,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => queueStatusChip(row.queue_status),
      },
      {
        field: 'sla',
        headerName: 'SLA',
        width: 170,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => {
          const sla = deriveMarketplaceOrderSla(row)
          const meta = slaMeta(sla.tone)
          return <Chip size="small" color={meta.color} variant="outlined" label={sla.label} />
        },
      },
      {
        field: 'total_amount',
        headerName: 'Total',
        width: 120,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => (row.total_amount !== null ? formatCurrency(row.total_amount) : '—'),
      },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 176,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.5}>
            <Tooltip title="Ver operação" arrow>
              <IconButton size="small" onClick={() => setSelectedOrderUuid(row.uuid)} sx={{ minWidth: 44, minHeight: 44 }}>
                <VisibilityOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title="Sincronizar novamente" arrow>
              <span>
                <IconButton
                  size="small"
                  disabled={!canUpdate || processingAction === `refresh:${row.uuid}`}
                  onClick={() => void handleRefreshExternal(row.uuid)}
                  sx={{ minWidth: 44, minHeight: 44 }}
                >
                  <RefreshOutlinedIcon fontSize="small" />
                </IconButton>
              </span>
            </Tooltip>
            <Tooltip title="Importar para pedido interno" arrow>
              <span>
                <IconButton
                  size="small"
                  disabled={!canUpdate || row.queue_status === 'imported' || processingAction === `import:${row.uuid}`}
                  onClick={() => void handleImport(row.uuid)}
                  sx={{ minWidth: 44, minHeight: 44 }}
                >
                  <SyncOutlinedIcon fontSize="small" />
                </IconButton>
              </span>
            </Tooltip>
          </Stack>
        ),
      },
    ],
    [canUpdate, handleImport, handleRefreshExternal, processingAction],
  )

  if (!isLoadingIntegrations && !loadError && integrations.length === 0) {
    return (
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1200 }}>
        <EmptyState
          icon={<LocalShippingOutlinedIcon sx={{ fontSize: 44, color: 'var(--mk-muted)' }} />}
          title="Nenhuma integração de delivery cadastrada"
          description="Cadastre e conecte a integração do iFood primeiro. Depois disso, os pedidos externos começam a aparecer aqui como fila operacional."
          action={
            <Button component={RouterLink} to="/configuracoes/integracoes" variant="contained" sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
              Abrir integrações
            </Button>
          }
        />
      </Box>
    )
  }

  return (
    <>
      <CrudListPage
        title="Pedidos iFood"
        subtitle="Acompanhe a fila de pedidos externos, sincronize eventos e importe para o fluxo interno da empresa."
        secondaryAction={
          selectedIntegration ? (
            <Button component={RouterLink} to="/configuracoes/integracoes" variant="outlined" startIcon={<AutorenewOutlinedIcon />} sx={{ minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
              Gerenciar integração
            </Button>
          ) : undefined
        }
        toolbar={
          <>
            <TextField
              select
              label="Integração"
              size="small"
              value={selectedIntegrationUuid}
              onChange={(event) => setSelectedIntegrationUuid(event.target.value)}
              sx={{ minWidth: 240 }}
            >
              {integrations.map((integration) => (
                <MenuItem key={integration.uuid} value={integration.uuid}>
                  {integration.name} • {integration.environment === 'production' ? 'Produção' : 'Sandbox'}
                </MenuItem>
              ))}
            </TextField>
            <TextField
              label="Buscar pedido"
              size="small"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Cliente, pedido externo ou ID"
              sx={{ minWidth: 240 }}
            />
            <TextField
              label="Status iFood"
              size="small"
              value={status}
              onChange={(event) => setStatus(event.target.value)}
              placeholder="Ex.: PLACED"
              sx={{ minWidth: 180 }}
            />
            <TextField
              select
              label="Fila interna"
              size="small"
              value={queueStatus}
              onChange={(event) => {
                const nextValue = event.target.value as MarketplaceOrder['queue_status'] | ''
                setQueueStatus(nextValue)
                setSearchParams((current) => {
                  const next = new URLSearchParams(current)
                  if (nextValue) {
                    next.set('queue_status', nextValue)
                  } else {
                    next.delete('queue_status')
                  }
                  return next
                }, { replace: true })
              }}
              sx={{ minWidth: 220 }}
            >
              {QUEUE_OPTIONS.map((option) => (
                <MenuItem key={option.value || 'all'} value={option.value}>
                  {option.label}
                </MenuItem>
              ))}
            </TextField>
            <TextField
              select
              label="Foco operacional"
              size="small"
              value={focusFilter}
              onChange={(event) => {
                const nextValue = event.target.value as MarketplaceFocusFilter
                setFocusFilter(nextValue)
                setSearchParams((current) => {
                  const next = new URLSearchParams(current)
                  if (nextValue === 'all') {
                    next.delete('focus')
                  } else {
                    next.set('focus', nextValue)
                  }

                  if (nextValue === 'pending_critical') {
                    next.set('queue_status', 'pending_import')
                    setQueueStatus('pending_import')
                  } else if (nextValue === 'import_error') {
                    next.set('queue_status', 'import_error')
                    setQueueStatus('import_error')
                  } else if (nextValue === 'stale_imported') {
                    next.set('queue_status', 'imported')
                    setQueueStatus('imported')
                  }

                  return next
                }, { replace: true })
              }}
              sx={{ minWidth: 220 }}
            >
              {MARKETPLACE_FOCUS_OPTIONS.map((option) => (
                <MenuItem key={option.value} value={option.value}>
                  {option.label}
                </MenuItem>
              ))}
            </TextField>
          </>
        }
        error={loadError}
        onRetry={() => void loadIntegrations()}
        isLoading={isLoadingIntegrations}
        isEmpty={false}
      >
        {operationsSummary?.needs_attention && (
          <Alert severity={operationsSummary.is_stale ? 'warning' : 'error'} variant="outlined" sx={{ mb: 2 }}>
            {operationsSummary.is_stale
              ? `A integração está sem sinal recente há ${operationsSummary.silent_since_minutes ?? 0} minuto(s). Verifique polling, webhook ou credenciais.`
              : 'Existem exceções operacionais em aberto nesta integração. Revise falhas, pedidos sem importação há muito tempo e pedidos internos sem atualização recente.'}
          </Alert>
        )}

        {focusSummaryText && (
          <Alert severity="info" variant="outlined" sx={{ mb: 2 }}>
            {focusSummaryText}
          </Alert>
        )}

        {openedFromDashboard ? (
          <Alert severity="info" variant="outlined" sx={{ mb: 2 }}>
            Esta fila externa foi aberta a partir do dashboard para tratamento operacional contextualizado.
          </Alert>
        ) : null}

        {operationsSummary && (
          <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'repeat(2, minmax(0, 1fr))', lg: 'repeat(4, minmax(0, 1fr))' }, gap: 1.5, mb: 2 }}>
            {[
              { label: 'Pendentes', value: operationsSummary.orders_pending_import },
              { label: 'Com erro', value: operationsSummary.orders_with_import_error },
              { label: 'Eventos com falha', value: operationsSummary.events_failed },
              { label: 'Letra morta', value: operationsSummary.events_dead_letter },
              { label: 'Pendentes em atenção', value: operationsSummary.orders_pending_import_attention },
              { label: 'Pendentes críticos', value: operationsSummary.orders_pending_import_critical },
              { label: 'Importados sem sinal', value: operationsSummary.orders_imported_without_recent_signal },
              {
                label: 'Maior fila pendente',
                value:
                  operationsSummary.oldest_pending_import_minutes === null
                    ? '—'
                    : `${operationsSummary.oldest_pending_import_minutes} min`,
              },
            ].map((item) => (
              <Paper key={item.label} variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
                <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{item.label}</Typography>
                <Typography sx={{ fontSize: 22, fontWeight: 700 }}>{item.value}</Typography>
              </Paper>
            ))}
          </Box>
        )}

        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 1080 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="pedidos-ifood"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
                title: 'Nenhum pedido externo encontrado',
                description: 'Quando os pedidos do iFood chegarem nesta integração, eles aparecerão aqui para operação e acompanhamento.',
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <Dialog open={selectedOrderUuid !== null} onClose={() => setSelectedOrderUuid(null)} fullWidth maxWidth="md">
        <DialogTitle sx={{ fontWeight: 700 }}>Operação do pedido iFood</DialogTitle>
        <DialogContent dividers>
          {detailError && <Alert severity="error">{detailError}</Alert>}
          {!detailError && isDetailLoading && <Typography>Carregando detalhes do pedido externo…</Typography>}

          {!detailError && !isDetailLoading && selectedOrder && (
            <Stack spacing={2}>
              {(() => {
                const sla = deriveMarketplaceOrderSla(selectedOrder)
                const meta = slaMeta(sla.tone)
                const timeline = buildMarketplaceTimeline(selectedOrder)
                const signalAge = minutesSince(selectedOrder.latest_event_at ?? selectedOrder.last_synced_at ?? selectedOrder.raw_updated_at)
                const importLeadTime =
                  selectedOrder.imported_at && selectedOrder.latest_event_at
                    ? Math.max(
                        0,
                        Math.floor(
                          (new Date(selectedOrder.imported_at).getTime() - new Date(selectedOrder.latest_event_at).getTime()) / 60000,
                        ),
                      )
                    : null

                return (
                  <>
              <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', alignItems: { xs: 'stretch', md: 'center' } }}>
                <Stack spacing={0.5}>
                  <Typography sx={{ fontSize: 20, fontWeight: 700 }}>
                    {selectedOrder.display_id ?? selectedOrder.order_number ?? selectedOrder.external_id}
                  </Typography>
                  <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                    {selectedOrder.customer_name ?? 'Cliente não informado'} • {selectedOrder.merchant?.name ?? 'Loja externa'}
                  </Typography>
                </Stack>
                <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
                  {queueStatusChip(selectedOrder.queue_status)}
                  <Chip size="small" variant="outlined" label={selectedOrder.status ?? 'Sem status'} />
                  <Chip size="small" color={meta.color} variant="outlined" label={sla.label} />
                </Stack>
              </Stack>

              {selectedOrder.import_error_message && (
                <Alert severity="warning" variant="outlined">
                  {selectedOrder.import_error_message}
                </Alert>
              )}

              <Alert severity={meta.color === 'success' ? 'info' : meta.color} variant="outlined">
                {sla.detail}
              </Alert>

              <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'repeat(3, minmax(0, 1fr))' }, gap: 1.5 }}>
                {[
                  { label: 'Última sincronização', value: formatOptionalDateTime(selectedOrder.last_synced_at) },
                  { label: 'Último evento', value: formatOptionalDateTime(selectedOrder.latest_event_at) },
                  { label: 'Total', value: selectedOrder.total_amount !== null ? formatCurrency(selectedOrder.total_amount) : '—' },
                ].map((item) => (
                  <Paper key={item.label} variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
                    <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{item.label}</Typography>
                    <Typography sx={{ fontSize: 16, fontWeight: 700 }}>{item.value}</Typography>
                  </Paper>
                ))}
              </Box>

              <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(3, minmax(0, 1fr))' }, gap: 1.5 }}>
                {[
                  { label: 'Tempo sem novo sinal', value: formatElapsed(signalAge) },
                  { label: 'Tempo até importar', value: importLeadTime === null ? 'Ainda não importado' : formatElapsed(importLeadTime) },
                  {
                    label: 'Trilha operacional',
                    value: `${timeline.length} registro(s)`,
                  },
                ].map((item) => (
                  <Paper key={item.label} variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
                    <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{item.label}</Typography>
                    <Typography sx={{ fontSize: 16, fontWeight: 700 }}>{item.value}</Typography>
                  </Paper>
                ))}
              </Box>

              <Stack direction={{ xs: 'column', lg: 'row' }} spacing={2}>
                <Stack spacing={1.5} sx={{ flex: 1 }}>
                  <Typography sx={{ fontSize: 15.5, fontWeight: 700 }}>Ações operacionais</Typography>
                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ flexWrap: 'wrap' }}>
                    <Button
                      variant="outlined"
                      startIcon={<RefreshOutlinedIcon />}
                      disabled={!canUpdate || processingAction === `refresh:${selectedOrder.uuid}`}
                      onClick={() => void handleRefreshExternal(selectedOrder.uuid)}
                    >
                      Sincronizar novamente
                    </Button>
                    <Button
                      variant="outlined"
                      startIcon={<SyncOutlinedIcon />}
                      disabled={!canUpdate || selectedOrder.queue_status === 'imported' || processingAction === `import:${selectedOrder.uuid}`}
                      onClick={() => void handleImport(selectedOrder.uuid)}
                    >
                      Importar pedido
                    </Button>
                    <Button
                      variant="outlined"
                      color="error"
                      disabled={!canUpdate || processingAction === `cancel:${selectedOrder.uuid}`}
                      onClick={() => void openCancelDialog()}
                    >
                      Solicitar cancelamento
                    </Button>
                    {ACTION_OPTIONS.map((option) => (
                      <Button
                        key={option.value}
                        variant="outlined"
                        startIcon={<PlayArrowOutlinedIcon />}
                        disabled={!canUpdate || processingAction === `${option.value}:${selectedOrder.uuid}`}
                        onClick={() => void handleSendAction(selectedOrder.uuid, option.value)}
                      >
                        {option.label}
                      </Button>
                    ))}
                  </Stack>
                </Stack>

                <Stack spacing={1} sx={{ width: { xs: '100%', lg: 280 } }}>
                  <Typography sx={{ fontSize: 15.5, fontWeight: 700 }}>Pedido interno</Typography>
                  {selectedOrder.internal_order ? (
                    <Paper variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
                      <Typography sx={{ fontWeight: 700 }}>{selectedOrder.internal_order.codigo ?? selectedOrder.internal_order.uuid}</Typography>
                      <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>
                        {selectedOrder.internal_order.client_name ?? 'Cliente interno'} • {selectedOrder.internal_order.status}
                      </Typography>
                      <Button
                        sx={{ mt: 1.25 }}
                        variant="contained"
                        size="small"
                        startIcon={<CheckCircleOutlineOutlinedIcon />}
                        onClick={() => setSelectedInternalOrderUuid(selectedOrder.internal_order?.uuid ?? null)}
                      >
                        Abrir pedido interno
                      </Button>
                    </Paper>
                  ) : (
                    <Alert severity="info" variant="outlined">
                      Este pedido externo ainda não foi convertido em pedido interno.
                    </Alert>
                  )}
                </Stack>
              </Stack>

              <Divider />

              <Stack spacing={1}>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                  <HistoryOutlinedIcon sx={{ fontSize: 18, color: 'var(--mk-primary)' }} />
                  <Typography sx={{ fontSize: 15.5, fontWeight: 700 }}>Timeline operacional consolidada</Typography>
                </Stack>
                {timeline.length > 0 ? (
                  <Stack spacing={1}>
                    {timeline.map((entry) => {
                      const tone = slaMeta(entry.tone)
                      return (
                      <Paper key={entry.id} variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.5 }}>
                        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ justifyContent: 'space-between' }}>
                          <Box>
                            <Typography sx={{ fontWeight: 700 }}>{entry.title}</Typography>
                            <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
                              {formatOptionalDateTime(entry.occurredAt)} • {entry.subtitle}
                            </Typography>
                          </Box>
                          <Stack direction="row" spacing={1}>
                            <Chip
                              size="small"
                              variant="outlined"
                              label={entry.type === 'event' ? 'Evento' : entry.type === 'action' ? 'Ação' : 'Importação'}
                            />
                            <Chip size="small" color={tone.color} variant="outlined" label={entry.status} />
                            {entry.retryEventUuid && (
                              <Tooltip title="Reprocessar evento" arrow>
                                <span>
                                  <IconButton
                                    size="small"
                                    disabled={!canUpdate || processingAction === `retry-event:${entry.retryEventUuid}`}
                                    onClick={() => {
                                      if (!entry.retryEventUuid) return
                                      void handleRetryEvent(entry.retryEventUuid)
                                    }}
                                    sx={{ minWidth: 36, minHeight: 36 }}
                                  >
                                    <ReplayOutlinedIcon fontSize="small" />
                                  </IconButton>
                                </span>
                              </Tooltip>
                            )}
                          </Stack>
                        </Stack>
                        {entry.errorMessage && (
                          <Alert severity="error" variant="outlined" sx={{ mt: 1.25 }}>
                            {entry.errorMessage}
                          </Alert>
                        )}
                      </Paper>
                    )})}
                  </Stack>
                ) : (
                  <Alert severity="info" variant="outlined">
                    Ainda não há movimentações associadas a este pedido externo.
                  </Alert>
                )}
              </Stack>

              <Stack spacing={1}>
                <Typography sx={{ fontSize: 15.5, fontWeight: 700 }}>Payload técnico</Typography>
                <Box
                  component="pre"
                  sx={{
                    m: 0,
                    p: 1.5,
                    overflowX: 'auto',
                    ...SOFT_PANEL_SX,
                    fontSize: 12.5,
                  }}
                >
                  {stringifyPayload(selectedOrder.payload)}
                </Box>
              </Stack>
                  </>
                )
              })()}
            </Stack>
          )}
        </DialogContent>
        <DialogActions sx={{ px: 3, py: 2 }}>
          <Button onClick={() => setSelectedOrderUuid(null)}>Fechar</Button>
        </DialogActions>
      </Dialog>

      <OrderDetailDialog
        orderUuid={selectedInternalOrderUuid}
        open={selectedInternalOrderUuid !== null}
        onClose={() => setSelectedInternalOrderUuid(null)}
        onChanged={() => {
          gridApiRef.current?.refreshInfiniteCache()
          if (selectedOrderUuid) void loadOrderDetail(selectedOrderUuid)
        }}
      />

      <Dialog open={cancelDialogOpen} onClose={processingAction?.startsWith('cancel:') ? undefined : () => setCancelDialogOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle sx={{ fontWeight: 700 }}>Solicitar cancelamento no iFood</DialogTitle>
        <DialogContent dividers>
          {isLoadingCancellationReasons ? (
            <Typography>Carregando motivos de cancelamento…</Typography>
          ) : (
            <Stack spacing={2}>
              <Alert severity="warning" variant="outlined">
                Esta ação solicita o cancelamento do pedido no iFood. Use um motivo válido para manter a trilha operacional consistente.
              </Alert>
              <TextField
                select
                label="Motivo de cancelamento"
                value={selectedCancellationCode}
                onChange={(event) => {
                  const nextCode = event.target.value
                  setSelectedCancellationCode(nextCode)
                  const reason = cancellationReasons.find((item) => item.code === nextCode)
                  if (reason) setCancelReasonText(reason.description)
                }}
                fullWidth
              >
                {cancellationReasons.map((reason) => (
                  <MenuItem key={reason.code} value={reason.code}>
                    {reason.code} • {reason.description}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label="Descrição para auditoria"
                value={cancelReasonText}
                onChange={(event) => setCancelReasonText(event.target.value)}
                multiline
                minRows={3}
                fullWidth
                helperText="Esse texto segue junto com a solicitação para fins operacionais."
              />
            </Stack>
          )}
        </DialogContent>
        <DialogActions sx={{ px: 3, py: 2 }}>
          <Button onClick={() => setCancelDialogOpen(false)} disabled={Boolean(processingAction?.startsWith('cancel:'))}>
            Fechar
          </Button>
          <Button
            variant="contained"
            color="error"
            disabled={isLoadingCancellationReasons || !selectedCancellationCode || Boolean(processingAction?.startsWith('cancel:'))}
            onClick={() => void handleCancelOrder()}
          >
            {processingAction?.startsWith('cancel:') ? 'Enviando…' : 'Solicitar cancelamento'}
          </Button>
        </DialogActions>
      </Dialog>

      <Snackbar
        open={feedback !== null}
        autoHideDuration={5000}
        onClose={() => setFeedback(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
      >
        {feedback ? (
          <Alert onClose={() => setFeedback(null)} severity={feedback.severity} variant="filled" sx={{ width: '100%' }}>
            {feedback.message}
          </Alert>
        ) : undefined}
      </Snackbar>
    </>
  )
}
