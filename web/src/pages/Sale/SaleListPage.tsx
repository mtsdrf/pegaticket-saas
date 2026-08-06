import AddIcon from '@mui/icons-material/Add'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import { Box, Button, Chip, IconButton, Stack, Typography, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useMemo, useRef, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
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
import type { SaleOperationStage, SaleOrigin } from '../../types/sale'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'
import { deriveSaleStatus, STATUS_TONE_COLORS } from '../../utils/saleStatus'

const ORIGIN_META: Record<SaleOrigin, { label: string; shortLabel: string }> = {
  staff: { label: 'Venda manual', shortLabel: 'Manual' },
  storefront: { label: 'Bilheteria online', shortLabel: 'Online' },
}

const STAGE_META: Record<SaleOperationStage, { label: string; accent: string }> = {
  approval: { label: 'Aguardando aprovação', accent: 'var(--pt-warning)' },
  confirmed: { label: 'Confirmado', accent: 'var(--pt-primary)' },
}

function deriveOperationStage(sale: Sale): SaleOperationStage | null {
  if (sale.cancelled_at || sale.status === 'rejected') return null
  if (sale.status === 'pending_approval') return 'approval'
  if (sale.status === 'confirmed' && !sale.is_paid) return 'confirmed'
  return null
}

function SaleStatusBadge({ sale }: { sale: Sale }) {
  const derived = deriveSaleStatus({
    is_cancelled: Boolean(sale.cancelled_at),
    is_paid: sale.is_paid,
    is_installment: sale.is_installment,
    paid_at: sale.paid_at,
    status: sale.status,
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
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const isManualOrdersPage = location.pathname === '/vendas-manuais'

  const [selectedSaleUuid, setSelectedSaleUuid] = useState<string | null>(null)
  const [selectedTimelineSaleUuid, setSelectedTimelineSaleUuid] = useState<string | null>(null)

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Sale>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await saleService.listSales({
        ...filters,
        ...(isManualOrdersPage ? { origin: 'staff' as const } : {}),
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, isManualOrdersPage],
  )

  const columns = useMemo<ServerGridColumn<Sale>[]>(
    () => [
      ...(!isManualOrdersPage
        ? [{
            field: 'origin',
            headerName: 'Canal',
            width: 120,
            filterType: 'text',
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
            filterType: 'text',
            filterTextToBackend: (value) => {
              const normalized = value.trim().toLowerCase()
              if (normalized.includes('apro')) return 'approval'
              if (normalized.includes('conf')) return 'confirmed'
              return value
            },
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
      {
        // Motor de risco básico (roadmap Fase 7) — só um alerta pro staff
        // revisar manualmente, nunca bloqueia a venda automaticamente.
        field: 'risk_flagged',
        headerName: 'Risco',
        width: 90,
        filterType: 'boolean',
        cellRenderer: (row) =>
          row.risk_flagged ? (
            <Tooltip title={row.risk_reason ?? 'Padrão de compra suspeito — revise manualmente.'}>
              <Chip
                size="small"
                label="Revisar"
                sx={{
                  fontWeight: 700,
                  color: 'var(--pt-warning)',
                  bgcolor: 'color-mix(in srgb, var(--pt-warning) 14%, transparent)',
                }}
              />
            </Tooltip>
          ) : null,
        exportValue: (row) => (row.risk_flagged ? 'Sim' : 'Não'),
      },
      ...(!isManualOrdersPage
        ? [{
            field: 'status',
            headerName: 'Status',
            width: 300,
            filterType: 'text',
            cellRenderer: (row: Sale) => <SaleStatusBadge sale={row} />,
            exportValue: (row: Sale) => deriveSaleStatus({
              is_cancelled: Boolean(row.cancelled_at),
              is_paid: row.is_paid,
              is_installment: row.is_installment,
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
        field: 'created_at',
        headerName: 'Criado em',
        width: 170,
        filterType: 'text',
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

  const manualPage = (
    <CrudListPage
      title="Vendas manuais"
      subtitle="Gerencie as vendas lançadas manualmente pela equipe."
      createLabel="Nova venda"
      canCreate={can(ACCESS.salesCreate)}
      onCreate={() => navigate('/vendas-manuais/nova')}
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
                <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/vendas-manuais/nova')}>
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
          onCreate={() => navigate('/vendas-manuais/nova')}
          error={null}
          onRetry={() => undefined}
          isLoading={!activeTenantUuid}
          isEmpty={false}
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
                  title: 'Nenhuma venda encontrada',
                  description: 'Quando houver vendas registradas, elas aparecerão aqui e poderão ser refinadas pelos filtros do próprio grid.',
                  action: can(ACCESS.salesCreate) ? (
                    <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/vendas-manuais/nova')}>
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
