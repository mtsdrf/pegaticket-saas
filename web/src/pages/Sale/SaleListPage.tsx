import AddIcon from '@mui/icons-material/Add'
import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import FilterAltOutlinedIcon from '@mui/icons-material/FilterAltOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import LanguageOutlinedIcon from '@mui/icons-material/LanguageOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import { Box, Button, Chip, IconButton, Stack, Typography, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { SaleDetailDialog } from '../../components/sale/SaleDetailDialog'
import { WorkflowTimelineDialog } from '../../components/workflow/WorkflowTimelineDialog'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as saleService from '../../services/saleService'
import * as workflowService from '../../services/workflowService'
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
  staff: { label: 'Venda manual', shortLabel: 'Manual' },
  storefront: { label: 'Bilheteria online', shortLabel: 'Online' },
}

const STAGE_FILTERS: Array<{ value: 'all' | SaleOperationStage; label: string }> = [
  { value: 'all', label: 'Todas as etapas' },
  { value: 'approval', label: 'Aguardando aprovação' },
  { value: 'confirmed', label: 'Confirmado' },
  { value: 'financial_pending', label: 'Financeiro pendente' },
]

const STAGE_META: Record<SaleOperationStage, { label: string; accent: string }> = {
  approval: { label: 'Aguardando aprovação', accent: 'var(--pt-warning)' },
  confirmed: { label: 'Confirmado', accent: 'var(--pt-primary)' },
  financial_pending: { label: 'Financeiro pendente', accent: 'var(--pt-danger)' },
}

const STATUS_FILTERS: Array<{ value: 'all' | SaleStatus; label: string }> = [
  { value: 'all', label: 'Todos os status' },
  { value: 'pending_approval', label: 'Aguardando aprovação' },
  { value: 'confirmed', label: 'Confirmados' },
  { value: 'cancellation_requested', label: 'Cancelamento solicitado' },
  { value: 'rejected', label: 'Recusados' },
]

function deriveOperationStage(order: Sale): SaleOperationStage | null {
  if (order.cancelled_at || order.status === 'rejected') return null
  if (order.status === 'pending_approval') return 'approval'
  if (order.is_completed && !order.is_paid) return 'financial_pending'
  if (order.status === 'confirmed' && !order.is_completed) return 'confirmed'
  return null
}

function SaleStatusBadge({ order }: { order: Sale }) {
  const derived = deriveSaleStatus({
    is_cancelled: Boolean(order.cancelled_at),
    is_paid: order.is_paid,
    is_completed: order.is_completed,
    is_installment: order.is_installment,
    completed_at: order.completed_at,
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

/**
 * Lista de vendas do sistema (`/vendas`) e vendas manuais (`/vendas-manuais`,
 * mesma página com `origin` fixo em `staff`). Sem Kanban/board — o fluxo de
 * ingresso é `pago → concluído`, sem etapa operacional intermediária de
 * preparo/despacho, então uma lista filtrável já cobre o caso de uso.
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
  const isStageFilterFromQueryValid = stageFilterFromQuery === 'approval'
    || stageFilterFromQuery === 'confirmed'
    || stageFilterFromQuery === 'financial_pending'

  const [selectedSaleUuid, setSelectedSaleUuid] = useState<string | null>(null)
  const [selectedTimelineSaleUuid, setSelectedTimelineSaleUuid] = useState<string | null>(null)
  const [originFilter, setOriginFilter] = useState<'all' | SaleOrigin>(isManualOrdersPage ? 'staff' : 'all')
  const [statusFilter, setStatusFilter] = useState<'all' | SaleStatus>('all')
  const [stageFilter, setStageFilter] = useState<'all' | SaleOperationStage>(
    isStageFilterFromQueryValid ? stageFilterFromQuery : 'all',
  )
  const [activeOnly, setActiveOnly] = useState(true)
  const [originCounts, setOriginCounts] = useState<Partial<Record<SaleOrigin, number>>>({})

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

  useEffect(() => {
    const nextStageFilter = isStageFilterFromQueryValid ? stageFilterFromQuery : 'all'
    setStageFilter((current) => (current === nextStageFilter ? current : nextStageFilter))
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

  useEffect(() => {
    if (isManualOrdersPage || !activeTenantUuid) return
    let cancelled = false
    ;(async () => {
      const counts = await Promise.all(
        (['staff', 'storefront'] as SaleOrigin[]).map(async (origin) => {
          const pageResult = await saleService.listSales({ origin, active_only: true, per_page: 1 })
          return [origin, pageResult.pagination.total] as const
        }),
      )
      if (!cancelled) setOriginCounts(Object.fromEntries(counts))
    })()
    return () => {
      cancelled = true
    }
  }, [activeTenantUuid, isManualOrdersPage, activeOnly])

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
            headerName: 'Etapa',
            width: 170,
            sortable: false,
            filterType: 'none',
            cellRenderer: (row: Sale) => {
              const stage = deriveOperationStage(row)
              if (!stage) {
                return (
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                    Concluído / fora da lista
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
              return stage ? STAGE_META[stage].label : 'Concluído / fora da lista'
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
            headerName: 'Status',
            width: 300,
            sortable: false,
            filterType: 'none',
            cellRenderer: (row: Sale) => <SaleStatusBadge order={row} />,
            exportValue: (row: Sale) => deriveSaleStatus({
              is_cancelled: Boolean(row.cancelled_at),
              is_paid: row.is_paid,
              is_completed: row.is_completed,
              is_installment: row.is_installment,
              completed_at: row.completed_at,
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
        field: 'is_completed',
        headerName: 'Concluída',
        width: 120,
        filterType: 'boolean',
        cellRenderer: (row) => <ActiveChip isActive={row.is_completed} activeLabel="Sim" inactiveLabel="Não" />,
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
                onClick={() => setSelectedSaleUuid(row.uuid)}
                sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
              >
                <VisibilityOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title="Histórico" arrow>
              <IconButton
                size="small"
                aria-label={`Ver histórico da venda ${row.codigo}`}
                onClick={() => setSelectedTimelineSaleUuid(row.uuid)}
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

  const toolbar = !isManualOrdersPage ? (
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
            <Typography sx={{ fontSize: 14, fontWeight: 700 }}>Filtros</Typography>
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
            <Chip size="small" label={`Manual: ${originCounts.staff ?? 0}`} icon={<ApartmentOutlinedIcon fontSize="small" />} variant="outlined" />
            <Chip size="small" label={`Online: ${originCounts.storefront ?? 0}`} icon={<LanguageOutlinedIcon fontSize="small" />} variant="outlined" />
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
                  if (option.value !== 'all') setStatusFilter('all')
                  setSearchParams((current) => {
                    const next = new URLSearchParams(current)
                    if (option.value === 'all') next.delete('stage')
                    else next.set('stage', option.value)
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
            {STATUS_FILTERS.map((option) => (
              <Chip
                key={option.value}
                clickable
                label={option.label}
                color={statusFilter === option.value ? 'primary' : 'default'}
                variant={statusFilter === option.value ? 'filled' : 'outlined'}
                onClick={() => {
                  setStatusFilter(option.value)
                  if (option.value !== 'all') setStageFilter('all')
                  gridApiRef.current?.refreshInfiniteCache()
                }}
              />
            ))}
          </Stack>
        </Stack>
      </Stack>
    </Box>
  ) : undefined

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
          title="Vendas"
          subtitle="Acompanhe as vendas do sistema por canal, etapa e status."
          createLabel="Nova venda"
          canCreate={can(ACCESS.salesCreate)}
          onCreate={() => navigate('/vendas/nova')}
          error={null}
          onRetry={() => undefined}
          isLoading={!activeTenantUuid}
          isEmpty={false}
          toolbar={toolbar}
        >
          <Box sx={{ overflowX: 'auto' }}>
            <Box sx={{ minWidth: 1180 }}>
              <ServerDataGrid
                columns={columns}
                fetchPage={fetchPage}
                rowIdField="uuid"
                exportFileName="vendas"
                onGridReady={(api) => {
                  gridApiRef.current = api
                }}
                emptyState={{
                  icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                  title: activeOnly ? 'Nenhuma venda em andamento neste filtro' : 'Nenhuma venda encontrada',
                  description: activeOnly
                    ? 'Quando houver novas vendas, elas aparecerão aqui independente do canal de entrada.'
                    : 'Ajuste os filtros acima ou crie a primeira venda.',
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
        onClose={() => setSelectedSaleUuid(null)}
        onChanged={() => gridApiRef.current?.refreshInfiniteCache()}
      />

      <WorkflowTimelineDialog
        open={selectedTimelineSaleUuid !== null}
        title="Histórico da venda"
        subjectLabel={selectedTimelineSaleUuid ? `venda ${selectedTimelineSaleUuid}` : 'venda'}
        loader={() => (selectedTimelineSaleUuid ? workflowService.getSaleWorkflowTimeline(selectedTimelineSaleUuid) : Promise.resolve([]))}
        stageLabel={(stage) => {
          if (!stage) return 'Sem etapa'
          return STAGE_META[stage as SaleOperationStage]?.label ?? stage
        }}
        onClose={() => setSelectedTimelineSaleUuid(null)}
      />
    </>
  )
}
