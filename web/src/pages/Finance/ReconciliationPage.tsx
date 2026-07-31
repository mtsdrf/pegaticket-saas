import AccountBalanceOutlinedIcon from '@mui/icons-material/AccountBalanceOutlined'
import ReplayOutlinedIcon from '@mui/icons-material/ReplayOutlined'
import ReportProblemOutlinedIcon from '@mui/icons-material/ReportProblemOutlined'
import RuleOutlinedIcon from '@mui/icons-material/RuleOutlined'
import { Box, Chip, MenuItem, TextField, Tooltip, Typography } from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { MetricCard } from '../../components/dashboard/MetricCard'
import { PeriodFilter } from '../../components/analytics/PeriodFilter'
import { useAuth } from '../../hooks/useAuth'
import * as financeService from '../../services/financeService'
import { getApiErrorMessage } from '../../types/api'
import type { ReconciliationEntry, ReconciliationStatus, ReconciliationSummary } from '../../types/finance'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'
import { presetRange } from '../../utils/period'

const STATUS_OPTIONS: { value: ReconciliationStatus | ''; label: string }[] = [
  { value: '', label: 'Todos os status' },
  { value: 'pending', label: 'Pendente' },
  { value: 'paid', label: 'Pago' },
  { value: 'failed', label: 'Falhou' },
  { value: 'refunded', label: 'Estornado' },
  { value: 'divergent', label: 'Divergente' },
]

const STATUS_LABELS: Record<string, string> = {
  pending: 'Pendente',
  paid: 'Pago',
  failed: 'Falhou',
  refunded: 'Estornado',
  divergent: 'Divergente',
}

const STATUS_TONE: Record<string, { bg: string; color: string }> = {
  pending: { bg: 'color-mix(in srgb, var(--pt-warning) 14%, transparent)', color: 'var(--pt-warning)' },
  paid: { bg: 'color-mix(in srgb, var(--pt-success) 14%, transparent)', color: 'var(--pt-success)' },
  failed: { bg: 'color-mix(in srgb, var(--pt-danger) 14%, transparent)', color: 'var(--pt-danger)' },
  refunded: { bg: 'color-mix(in srgb, var(--pt-info) 14%, transparent)', color: 'var(--pt-info)' },
  divergent: { bg: 'color-mix(in srgb, var(--pt-danger) 14%, transparent)', color: 'var(--pt-danger)' },
}

function StatusChip({ status }: { status: string }) {
  const tone = STATUS_TONE[status] ?? { bg: 'var(--pt-surface-soft)', color: 'var(--pt-muted)' }
  return (
    <Chip
      size="small"
      label={STATUS_LABELS[status] ?? status}
      sx={{ fontWeight: 600, bgcolor: tone.bg, color: tone.color }}
    />
  )
}

/**
 * Conciliação financeira (roadmap A3.12) — cruza `payments`/`refunds`/
 * `webhook_events` do tenant. Sem PSP real plugado ainda (webhook sempre
 * 501), a coluna "Webhook" tende a mostrar "sem confirmação" na prática —
 * estrutura já pronta pra quando um provedor real processar webhooks.
 */
export function ReconciliationPage() {
  const { activeTenantUuid } = useAuth()
  const defaultRange = presetRange('last_30')
  const [from, setFrom] = useState(defaultRange.from)
  const [to, setTo] = useState(defaultRange.to)
  const [status, setStatus] = useState<ReconciliationStatus | ''>('')
  const [method, setMethod] = useState('')
  const [summary, setSummary] = useState<ReconciliationSummary | null>(null)
  const [isLoadingSummary, setIsLoadingSummary] = useState(true)
  const [summaryError, setSummaryError] = useState<string | null>(null)

  const loadSummary = useCallback(() => {
    if (!activeTenantUuid) return
    setIsLoadingSummary(true)
    setSummaryError(null)
    financeService
      .getReconciliationSummary({ from, to })
      .then(setSummary)
      .catch((error: unknown) => {
        setSummaryError(getApiErrorMessage(error, 'Não foi possível carregar o resumo da conciliação agora.'))
      })
      .finally(() => setIsLoadingSummary(false))
  }, [activeTenantUuid, from, to])

  useEffect(() => {
    loadSummary()
  }, [loadSummary])

  const fetchPage = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<ReconciliationEntry>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await financeService.listReconciliation({
        from,
        to,
        status: status || undefined,
        method: method || undefined,
        page,
        per_page: perPage,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, from, to, status, method],
  )

  const paidAmount = summary?.by_status.find((row) => row.status === 'paid')?.amount ?? 0
  const pendingAmount = summary?.by_status.find((row) => row.status === 'pending')?.amount ?? 0
  const divergentCount = summary?.by_status.find((row) => row.status === 'divergent')?.count ?? 0

  const columns = useMemo<ServerGridColumn<ReconciliationEntry>[]>(
    () => [
      {
        field: 'order',
        headerName: 'Pedido',
        width: 130,
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => row.order?.codigo ?? '—',
      },
      {
        field: 'provider',
        headerName: 'Provedor',
        width: 120,
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => row.provider ?? '—',
      },
      {
        field: 'method',
        headerName: 'Método',
        width: 110,
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => row.method ?? '—',
      },
      {
        field: 'amount',
        headerName: 'Valor',
        width: 130,
        filterType: 'none',
        cellRenderer: (row) => formatCurrency(row.amount),
        exportValue: (row) => formatCurrency(row.amount),
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 130,
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => <StatusChip status={row.status} />,
        exportValue: (row) => STATUS_LABELS[row.status] ?? row.status,
      },
      {
        field: 'refunds',
        headerName: 'Estorno',
        width: 140,
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) =>
          row.refunds.length > 0 ? (
            <Tooltip title={row.refunds.map((refund) => `${formatCurrency(refund.amount)} (${refund.status})`).join(' • ')}>
              <Chip
                size="small"
                icon={<ReplayOutlinedIcon fontSize="small" />}
                label={`${row.refunds.length} estorno${row.refunds.length === 1 ? '' : 's'}`}
                sx={{
                  fontWeight: 600,
                  bgcolor: 'color-mix(in srgb, var(--pt-info) 14%, transparent)',
                  color: 'var(--pt-info)',
                  '& .MuiChip-icon': { color: 'inherit' },
                }}
              />
            </Tooltip>
          ) : (
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>—</Typography>
          ),
        exportValue: (row) => (row.refunds.length > 0 ? row.refunds.length : 0),
      },
      {
        field: 'webhook_event',
        headerName: 'Webhook',
        width: 200,
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) =>
          row.webhook_event ? (
            <Chip
              size="small"
              label="Confirmado"
              sx={{ fontWeight: 600, bgcolor: 'color-mix(in srgb, var(--pt-success) 14%, transparent)', color: 'var(--pt-success)' }}
            />
          ) : (
            <Chip
              size="small"
              icon={<ReportProblemOutlinedIcon fontSize="small" />}
              label="Sem confirmação de webhook"
              sx={{
                fontWeight: 600,
                bgcolor: 'color-mix(in srgb, var(--pt-warning) 14%, transparent)',
                color: 'var(--pt-warning)',
                '& .MuiChip-icon': { color: 'inherit' },
              }}
            />
          ),
        exportValue: (row) => (row.webhook_event ? 'Confirmado' : 'Sem confirmação de webhook'),
      },
      {
        field: 'paid_at',
        headerName: 'Pago em',
        width: 150,
        filterType: 'none',
        cellRenderer: (row) => (row.paid_at ? formatDateTimeBR(row.paid_at) : '—'),
        exportValue: (row) => (row.paid_at ? formatDateTimeBR(row.paid_at) : ''),
      },
    ],
    [],
  )

  return (
    <Box sx={{ maxWidth: 1600, mx: 'auto' }}>
      <CrudListPage
        title="Conciliação financeira"
        subtitle="Cruze pagamentos, estornos e confirmações de webhook para fechar o caixa com segurança."
        breadcrumbs={[{ label: 'Financeiro', to: '/financeiro/conciliacao' }, { label: 'Conciliação' }]}
        toolbar={
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5, width: '100%' }}>
            <PeriodFilter from={from} to={to} onChange={(nextFrom, nextTo) => { setFrom(nextFrom); setTo(nextTo) }} defaultPreset="last_30" />
            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0,1fr)', sm: 'repeat(2, minmax(0,220px))' }, gap: 1.5 }}>
              <TextField
                select
                label="Status"
                size="small"
                value={status}
                onChange={(event) => setStatus(event.target.value as ReconciliationStatus | '')}
              >
                {STATUS_OPTIONS.map((option) => (
                  <MenuItem key={option.value || 'all'} value={option.value}>
                    {option.label}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label="Método"
                size="small"
                value={method}
                onChange={(event) => setMethod(event.target.value)}
                placeholder="ex.: pix, cartao"
              />
            </Box>
          </Box>
        }
        error={summaryError}
        onRetry={loadSummary}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 240px), 1fr))',
            gap: 1.5,
            mb: 2,
          }}
        >
          <MetricCard
            icon={AccountBalanceOutlinedIcon}
            label="Recebido no período"
            value={isLoadingSummary ? null : formatCurrency(paidAmount)}
            caption={summary ? `${summary.by_status.find((row) => row.status === 'paid')?.count ?? 0} pagamento(s)` : null}
            tone="primary"
            isLoading={isLoadingSummary}
            index={0}
          />
          <MetricCard
            icon={RuleOutlinedIcon}
            label="Pendente de confirmação"
            value={isLoadingSummary ? null : formatCurrency(pendingAmount)}
            caption={summary ? `${summary.by_status.find((row) => row.status === 'pending')?.count ?? 0} pagamento(s)` : null}
            tone="warning"
            isLoading={isLoadingSummary}
            index={1}
          />
          <MetricCard
            icon={ReplayOutlinedIcon}
            label="Total estornado"
            value={isLoadingSummary ? null : formatCurrency(summary?.total_refunded_amount ?? 0)}
            tone="info"
            isLoading={isLoadingSummary}
            index={2}
          />
          <MetricCard
            icon={ReportProblemOutlinedIcon}
            label="Divergências"
            value={isLoadingSummary ? null : String(divergentCount)}
            caption={divergentCount > 0 ? 'Requer atenção' : 'Nenhuma pendência'}
            tone={divergentCount > 0 ? 'warning' : 'accent'}
            isLoading={isLoadingSummary}
            index={3}
          />
        </Box>

        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 1080 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="conciliacao-financeira"
              emptyState={{
                icon: <AccountBalanceOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                title: 'Nenhum pagamento no período',
                description: 'Ajuste o período ou os filtros para ver os pagamentos conciliados.',
              }}
            />
          </Box>
        </Box>
      </CrudListPage>
    </Box>
  )
}
