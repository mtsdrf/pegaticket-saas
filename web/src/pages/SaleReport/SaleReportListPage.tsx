import AssessmentOutlinedIcon from '@mui/icons-material/AssessmentOutlined'
import PictureAsPdfOutlinedIcon from '@mui/icons-material/PictureAsPdfOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import TaskAltOutlinedIcon from '@mui/icons-material/TaskAltOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
import { Box, Button, Chip } from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { MetricCard } from '../../components/dashboard/MetricCard'
import { useAuth } from '../../hooks/useAuth'
import * as reportDetailService from '../../services/reportDetailService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import type { Sale } from '../../types/sale'
import type { SaleReportFilters, SaleReportSummary } from '../../types/reportDetail'
import { CHANNEL_LABELS } from '../../types/report'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency, formatPercentage } from '../../utils/format'

export function SaleReportListPage() {
  const { activeTenantUuid } = useAuth()
  const [exportError, setExportError] = useState<string | null>(null)
  const [isExporting, setIsExporting] = useState(false)
  const [filters, setFilters] = useState<SaleReportFilters>({})
  const [summary, setSummary] = useState<SaleReportSummary | null>(null)
  const [isLoadingSummary, setIsLoadingSummary] = useState(true)
  // Drill-down do relatório "Resultado por canal" (/relatorios/canais) —
  // vem só na URL (?origin=X&date_from=Y&date_to=Z), aplicado por cima do
  // que o usuário filtrar na grid, não é estado do próprio filtro de grid.
  const [searchParams, setSearchParams] = useSearchParams()
  const drillDownOrigin = searchParams.get('origin')
  const drillDownFrom = searchParams.get('date_from')
  const drillDownTo = searchParams.get('date_to')
  const drillDownFilters = useMemo<SaleReportFilters>(
    () => ({
      ...(drillDownOrigin ? { origin: drillDownOrigin } : {}),
      ...(drillDownFrom ? { date_from: drillDownFrom } : {}),
      ...(drillDownTo ? { date_to: drillDownTo } : {}),
    }),
    [drillDownOrigin, drillDownFrom, drillDownTo],
  )

  const effectiveFilters = useMemo(() => ({ ...filters, ...drillDownFilters }), [filters, drillDownFilters])

  useEffect(() => {
    if (!activeTenantUuid) return
    let cancelled = false
    setIsLoadingSummary(true)
    reportDetailService
      .getOrdersSummary(effectiveFilters)
      .then((data) => {
        if (!cancelled) setSummary(data)
      })
      .catch(() => {
        if (!cancelled) setSummary(null)
      })
      .finally(() => {
        if (!cancelled) setIsLoadingSummary(false)
      })
    return () => {
      cancelled = true
    }
  }, [activeTenantUuid, effectiveFilters])

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters: gridFilters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Sale>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await reportDetailService.listOrderReports({
        ...gridFilters,
        ...drillDownFilters,
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, drillDownFilters],
  )

  const columns = useMemo<ServerGridColumn<Sale>[]>(
    () => [
      {
        field: 'client_name',
        headerName: 'Cliente',
        filterType: 'text',
        sortable: false,
        cellRenderer: (row) => row.final_customer?.name ?? '',
        exportValue: (row) => row.final_customer?.name ?? '',
      },
      {
        field: 'total_amount',
        headerName: 'Total',
        width: 130,
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => formatCurrency(row.total_amount),
        exportValue: (row) => formatCurrency(row.total_amount),
      },
      {
        field: 'is_paid',
        headerName: 'Pago',
        width: 110,
        filterType: 'boolean',
        cellRenderer: (row) => <ActiveChip isActive={row.is_paid} activeLabel="Sim" inactiveLabel="Não" />,
      },
      { field: 'is_completed', headerName: 'Concluída', width: 120, filterType: 'boolean' },
      { field: 'created_at', headerName: 'Criado em', width: 180, filterType: 'none', sortable: false },
    ],
    [],
  )

  async function handleExportPdf() {
    setExportError(null)
    setIsExporting(true)
    try {
      await reportDetailService.exportOrderReportsPdf(drillDownFilters)
    } catch (error) {
      setExportError(getApiErrorMessage(error, 'Não foi possível exportar o PDF agora.'))
    } finally {
      setIsExporting(false)
    }
  }

  function clearDrillDown() {
    setSearchParams({})
  }

  return (
    <CrudListPage
      title="Relatório de vendas"
      subtitle="Consulte as vendas com foco analítico e operacional."
      breadcrumbs={[{ label: 'Relatórios', to: '/relatorios/vendas' }, { label: 'Vendas' }]}
      toolbar={
        <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap', alignItems: 'center', width: { xs: '100%', sm: 'auto' } }}>
          {drillDownOrigin && (
            <Chip
              label={`Canal: ${CHANNEL_LABELS[drillDownOrigin] ?? drillDownOrigin}`}
              onDelete={clearDrillDown}
              sx={{
                ...SOFT_PANEL_SX,
                color: 'var(--pt-text)',
              }}
            />
          )}
          <Button
            variant="outlined"
            startIcon={<PictureAsPdfOutlinedIcon />}
            onClick={() => void handleExportPdf()}
            disabled={!activeTenantUuid || isExporting}
            sx={{ minHeight: 44, width: { xs: '100%', sm: 'auto' } }}
          >
            {isExporting ? 'Exportando PDF...' : 'Exportar PDF'}
          </Button>
        </Box>
      }
      error={exportError}
      onRetry={() => undefined}
      isLoading={!activeTenantUuid}
      isEmpty={false}
    >
      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', lg: 'repeat(4,1fr)' },
          gap: 1.5,
          mb: 2,
        }}
      >
        <MetricCard
          icon={ReceiptLongOutlinedIcon}
          label="Total no filtro"
          value={summary ? String(summary.total) : null}
          tone="primary"
          isLoading={isLoadingSummary}
          index={0}
        />
        <MetricCard
          icon={TaskAltOutlinedIcon}
          label="Concluídas"
          value={summary ? formatPercentage(summary.completed_percentage) : null}
          tone="accent"
          isLoading={isLoadingSummary}
          index={1}
        />
        <MetricCard
          icon={PaidOutlinedIcon}
          label="Pagos"
          value={summary ? formatPercentage(summary.paid_percentage) : null}
          tone="accent"
          isLoading={isLoadingSummary}
          index={2}
        />
        <MetricCard
          icon={WarningAmberOutlinedIcon}
          label="Atrasados"
          value={summary ? formatPercentage(summary.overdue_percentage) : null}
          tone={summary && summary.overdue_percentage > 0 ? 'warning' : 'primary'}
          isLoading={isLoadingSummary}
          index={3}
        />
      </Box>

      <Box sx={{ overflowX: 'auto' }}>
        <Box sx={{ minWidth: 760 }}>
          <ServerDataGrid
            columns={columns}
            fetchPage={fetchPage}
            rowIdField="uuid"
            exportFileName="relatorio-vendas"
            onFiltersChange={(next) => setFilters(next as SaleReportFilters)}
            emptyState={{
              icon: <AssessmentOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
              title: 'Nenhuma venda para o relatório',
              description: 'Os dados aparecerão aqui conforme as vendas forem registradas.',
            }}
          />
        </Box>
      </Box>
    </CrudListPage>
  )
}
