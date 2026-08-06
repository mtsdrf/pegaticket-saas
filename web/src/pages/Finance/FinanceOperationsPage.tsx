import AccountBalanceWalletOutlinedIcon from '@mui/icons-material/AccountBalanceWalletOutlined'
import PendingActionsOutlinedIcon from '@mui/icons-material/PendingActionsOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import RequestQuoteOutlinedIcon from '@mui/icons-material/RequestQuoteOutlined'
import RuleOutlinedIcon from '@mui/icons-material/RuleOutlined'
import { Box, Tab, Tabs } from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import { StatusChip, type StatusChipTone } from '../../components/crud/StatusChip'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { MetricCard } from '../../components/dashboard/MetricCard'
import { PeriodFilter } from '../../components/analytics/PeriodFilter'
import * as financeService from '../../services/financeService'
import { getApiErrorMessage } from '../../types/api'
import type { FinanceAdjustment, FinanceDashboard, FinanceReceivable, FinanceSettlement } from '../../types/finance'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'
import { presetRange } from '../../utils/period'

type FinanceTab = 'receivables' | 'settlements' | 'adjustments'

const FINANCE_STATUS_LABELS: Record<string, string> = {
  scheduled: 'Agendado',
  awaiting_release: 'Em custódia',
  release_requested: 'Liberação solicitada',
  released: 'Liberado',
  pending_recovery: 'Pendente de recuperação',
  pending_review: 'Pendente de revisão',
  applied: 'Aplicado',
  recovered: 'Recuperado',
  written_off: 'Absorvido',
}

const FINANCE_STATUS_TONES: Record<string, StatusChipTone> = {
  scheduled: 'neutral',
  awaiting_release: 'warning',
  release_requested: 'info',
  released: 'success',
  pending_recovery: 'warning',
  pending_review: 'info',
  applied: 'success',
  recovered: 'success',
  written_off: 'danger',
}

export function FinanceOperationsPage() {
  const defaultRange = presetRange('last_30')
  const [tab, setTab] = useState<FinanceTab>('receivables')
  const [from, setFrom] = useState(defaultRange.from)
  const [to, setTo] = useState(defaultRange.to)
  const [dashboard, setDashboard] = useState<FinanceDashboard | null>(null)
  const [isLoadingDashboard, setIsLoadingDashboard] = useState(true)
  const [dashboardError, setDashboardError] = useState<string | null>(null)

  const loadDashboard = useCallback(() => {
    setIsLoadingDashboard(true)
    setDashboardError(null)
    financeService
      .getFinanceDashboard()
      .then(setDashboard)
      .catch((error: unknown) => {
        setDashboardError(getApiErrorMessage(error, 'Não foi possível carregar a operação financeira agora.'))
      })
      .finally(() => setIsLoadingDashboard(false))
  }, [])

  useEffect(() => {
    loadDashboard()
  }, [loadDashboard])

  const receivableColumns = useMemo<ServerGridColumn<FinanceReceivable>[]>(() => [
    {
      field: 'sale',
      headerName: 'Venda',
      width: 130,
      filterType: 'text',
      sortable: false,
      cellRenderer: (row) => row.sale?.codigo ?? '—',
    },
    {
      field: 'event',
      headerName: 'Evento',
      width: 220,
      filterType: 'text',
      sortable: false,
      cellRenderer: (row) => row.event?.name ?? '—',
    },
    {
      field: 'status',
      headerName: 'Status',
      width: 160,
      filterType: 'text',
      cellRenderer: (row) => <StatusChip status={row.status} label={FINANCE_STATUS_LABELS[row.status] ?? row.status} tone={FINANCE_STATUS_TONES[row.status] ?? 'neutral'} />,
      exportValue: (row) => FINANCE_STATUS_LABELS[row.status] ?? row.status,
    },
    {
      field: 'net_amount',
      headerName: 'Líquido',
      width: 130,
      filterType: 'number',
      cellRenderer: (row) => formatCurrency(row.net_amount),
      exportValue: (row) => formatCurrency(row.net_amount),
    },
    {
      field: 'available_at',
      headerName: 'Disponível em',
      width: 165,
      filterType: 'text',
      cellRenderer: (row) => (row.available_at ? formatDateTimeBR(row.available_at) : '—'),
      exportValue: (row) => (row.available_at ? formatDateTimeBR(row.available_at) : ''),
    },
    {
      field: 'settlement',
      headerName: 'Repasse',
      width: 160,
      filterType: 'text',
      sortable: false,
      cellRenderer: (row) => row.settlement?.code ?? '—',
    },
    {
      field: 'open_adjustments_amount',
      headerName: 'Exceções abertas',
      width: 150,
      filterType: 'number',
      cellRenderer: (row) => formatCurrency(row.open_adjustments_amount),
      exportValue: (row) => formatCurrency(row.open_adjustments_amount),
    },
  ], [])

  const settlementColumns = useMemo<ServerGridColumn<FinanceSettlement>[]>(() => [
    { field: 'code', headerName: 'Código', width: 180, filterType: 'text' },
    {
      field: 'status',
      headerName: 'Status',
      width: 160,
      filterType: 'text',
      cellRenderer: (row) => <StatusChip status={row.status} label={FINANCE_STATUS_LABELS[row.status] ?? row.status} tone={FINANCE_STATUS_TONES[row.status] ?? 'neutral'} />,
      exportValue: (row) => FINANCE_STATUS_LABELS[row.status] ?? row.status,
    },
    {
      field: 'net_amount',
      headerName: 'Líquido',
      width: 130,
      filterType: 'number',
      cellRenderer: (row) => formatCurrency(row.net_amount),
      exportValue: (row) => formatCurrency(row.net_amount),
    },
    {
      field: 'receivables_count',
      headerName: 'Recebíveis',
      width: 120,
      filterType: 'number',
      cellRenderer: (row) => String(row.receivables_count),
    },
    {
      field: 'scheduled_for',
      headerName: 'Agendado para',
      width: 165,
      filterType: 'text',
      cellRenderer: (row) => (row.scheduled_for ? formatDateTimeBR(row.scheduled_for) : '—'),
      exportValue: (row) => (row.scheduled_for ? formatDateTimeBR(row.scheduled_for) : ''),
    },
    {
      field: 'released_at',
      headerName: 'Liberado em',
      width: 165,
      filterType: 'text',
      cellRenderer: (row) => (row.released_at ? formatDateTimeBR(row.released_at) : '—'),
      exportValue: (row) => (row.released_at ? formatDateTimeBR(row.released_at) : ''),
    },
    {
      field: 'open_adjustments_amount',
      headerName: 'Exceções abertas',
      width: 150,
      filterType: 'number',
      cellRenderer: (row) => formatCurrency(row.open_adjustments_amount),
      exportValue: (row) => formatCurrency(row.open_adjustments_amount),
    },
  ], [])

  const adjustmentColumns = useMemo<ServerGridColumn<FinanceAdjustment>[]>(() => [
    { field: 'type', headerName: 'Tipo', width: 200, filterType: 'text', sortable: false },
    {
      field: 'status',
      headerName: 'Status',
      width: 170,
      filterType: 'text',
      cellRenderer: (row) => <StatusChip status={row.status} label={FINANCE_STATUS_LABELS[row.status] ?? row.status} tone={FINANCE_STATUS_TONES[row.status] ?? 'neutral'} />,
      exportValue: (row) => FINANCE_STATUS_LABELS[row.status] ?? row.status,
    },
    {
      field: 'amount',
      headerName: 'Valor',
      width: 120,
      filterType: 'number',
      cellRenderer: (row) => formatCurrency(row.amount),
      exportValue: (row) => formatCurrency(row.amount),
    },
    { field: 'reason', headerName: 'Motivo', width: 320, filterType: 'text', sortable: false },
    {
      field: 'sale',
      headerName: 'Venda',
      width: 120,
      filterType: 'text',
      sortable: false,
      cellRenderer: (row) => row.sale?.codigo ?? '—',
    },
    {
      field: 'settlement',
      headerName: 'Repasse',
      width: 150,
      filterType: 'text',
      sortable: false,
      cellRenderer: (row) => row.settlement?.code ?? '—',
    },
    {
      field: 'created_at',
      headerName: 'Criado em',
      width: 165,
      filterType: 'text',
      cellRenderer: (row) => formatDateTimeBR(row.created_at),
      exportValue: (row) => formatDateTimeBR(row.created_at),
    },
  ], [])

  const fetchReceivables = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<FinanceReceivable>> => {
      const result = await financeService.listFinanceReceivables({
        from,
        to,
        page,
        per_page: perPage,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [from, to],
  )

  const fetchSettlements = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<FinanceSettlement>> => {
      const result = await financeService.listFinanceSettlements({
        from,
        to,
        page,
        per_page: perPage,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [from, to],
  )

  const fetchAdjustments = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<FinanceAdjustment>> => {
      const result = await financeService.listFinanceAdjustments({
        from,
        to,
        page,
        per_page: perPage,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [from, to],
  )

  return (
    <Box>
      <CrudListPage
        title="Operação financeira"
        subtitle="Acompanhe recebíveis, repasses e exceções do financeiro da empresa em um só lugar."
        breadcrumbs={[{ label: 'Financeiro', to: '/financeiro/operacao' }, { label: 'Operação' }]}
        toolbar={
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5, width: '100%' }}>
            <PeriodFilter from={from} to={to} onChange={(nextFrom, nextTo) => { setFrom(nextFrom); setTo(nextTo) }} defaultPreset="last_30" />
          </Box>
        }
        error={dashboardError}
        onRetry={loadDashboard}
        isLoading={false}
        isEmpty={false}
      >
        <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 220px), 1fr))', gap: 1.5, mb: 2 }}>
          <MetricCard icon={AccountBalanceWalletOutlinedIcon} label="Disponível agora" value={isLoadingDashboard ? null : formatCurrency(dashboard?.balances.available_now_amount ?? 0)} tone="primary" isLoading={isLoadingDashboard} index={0} />
          <MetricCard icon={PendingActionsOutlinedIcon} label="Em custódia" value={isLoadingDashboard ? null : formatCurrency(dashboard?.balances.in_custody_amount ?? 0)} tone="warning" isLoading={isLoadingDashboard} index={1} />
          <MetricCard icon={RequestQuoteOutlinedIcon} label="Futuro" value={isLoadingDashboard ? null : formatCurrency(dashboard?.balances.future_amount ?? 0)} tone="info" isLoading={isLoadingDashboard} index={2} />
          <MetricCard icon={RuleOutlinedIcon} label="Exceções abertas" value={isLoadingDashboard ? null : formatCurrency(dashboard?.balances.open_adjustments_amount ?? 0)} caption={dashboard ? `${dashboard.queues.open_adjustments_count} item(ns)` : null} tone="accent" isLoading={isLoadingDashboard} index={3} />
        </Box>

        <Box sx={{ borderBottom: '1px solid var(--pt-divider)', mb: 2 }}>
          <Tabs value={tab} onChange={(_, value: FinanceTab) => setTab(value)} variant="scrollable" allowScrollButtonsMobile>
            <Tab value="receivables" label="Recebíveis" />
            <Tab value="settlements" label="Repasses" />
            <Tab value="adjustments" label="Exceções" />
          </Tabs>
        </Box>

        {tab === 'receivables' && (
          <ServerDataGrid
            columns={receivableColumns}
            fetchPage={fetchReceivables}
            rowIdField="uuid"
            exportFileName="financeiro-recebiveis"
            emptyState={{
              icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
              title: 'Nenhum recebível encontrado',
              description: 'Ajuste os filtros para localizar os recebíveis desta empresa.',
            }}
          />
        )}
        {tab === 'settlements' && (
          <ServerDataGrid
            columns={settlementColumns}
            fetchPage={fetchSettlements}
            rowIdField="uuid"
            exportFileName="financeiro-repasses"
            emptyState={{
              icon: <RequestQuoteOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
              title: 'Nenhum repasse encontrado',
              description: 'Quando houver lotes de repasse gerados, eles aparecerão aqui.',
            }}
          />
        )}
        {tab === 'adjustments' && (
          <ServerDataGrid
            columns={adjustmentColumns}
            fetchPage={fetchAdjustments}
            rowIdField="uuid"
            exportFileName="financeiro-excecoes"
            emptyState={{
              icon: <RuleOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
              title: 'Nenhuma exceção encontrada',
              description: 'Quando surgirem ajustes ou exceções financeiras, eles aparecerão aqui.',
            }}
          />
        )}
      </CrudListPage>
    </Box>
  )
}
