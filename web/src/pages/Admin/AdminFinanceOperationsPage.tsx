import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import CorporateFareOutlinedIcon from '@mui/icons-material/CorporateFareOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import RequestQuoteOutlinedIcon from '@mui/icons-material/RequestQuoteOutlined'
import RuleOutlinedIcon from '@mui/icons-material/RuleOutlined'
import { Box, MenuItem, Tab, Tabs, TextField } from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { MetricCard } from '../../components/dashboard/MetricCard'
import { PeriodFilter } from '../../components/analytics/PeriodFilter'
import * as financeService from '../../services/financeService'
import * as tenantAdminService from '../../services/tenantAdminService'
import { getApiErrorMessage } from '../../types/api'
import type { Tenant } from '../../types/admin'
import type { FinanceAdjustment, FinanceDashboard, FinanceReceivable, FinanceSettlement } from '../../types/finance'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'
import { presetRange } from '../../utils/period'

type FinanceAdminTab = 'receivables' | 'settlements' | 'adjustments'

export function AdminFinanceOperationsPage() {
  const defaultRange = presetRange('last_30')
  const [tab, setTab] = useState<FinanceAdminTab>('receivables')
  const [from, setFrom] = useState(defaultRange.from)
  const [to, setTo] = useState(defaultRange.to)
  const [tenantUuid, setTenantUuid] = useState('')
  const [status, setStatus] = useState('')
  const [dashboard, setDashboard] = useState<FinanceDashboard | null>(null)
  const [dashboardError, setDashboardError] = useState<string | null>(null)
  const [isLoadingDashboard, setIsLoadingDashboard] = useState(true)
  const [tenants, setTenants] = useState<Tenant[]>([])

  useEffect(() => {
    tenantAdminService.listTenants({ per_page: 100 }).then((result) => setTenants(result.items)).catch(() => undefined)
  }, [])

  const loadDashboard = useCallback(() => {
    setIsLoadingDashboard(true)
    setDashboardError(null)
    financeService
      .getAdminFinanceDashboard({ tenant_uuid: tenantUuid || undefined })
      .then(setDashboard)
      .catch((error: unknown) => {
        setDashboardError(getApiErrorMessage(error, 'Não foi possível carregar o painel financeiro administrativo agora.'))
      })
      .finally(() => setIsLoadingDashboard(false))
  }, [tenantUuid])

  useEffect(() => {
    loadDashboard()
  }, [loadDashboard])

  const receivableColumns = useMemo<ServerGridColumn<FinanceReceivable>[]>(() => [
    { field: 'tenant', headerName: 'Empresa', width: 220, filterType: 'none', sortable: false, cellRenderer: (row) => row.tenant?.name ?? '—' },
    { field: 'sale', headerName: 'Venda', width: 130, filterType: 'none', sortable: false, cellRenderer: (row) => row.sale?.codigo ?? '—' },
    { field: 'status', headerName: 'Status', width: 160, filterType: 'none', sortable: false },
    { field: 'net_amount', headerName: 'Líquido', width: 130, filterType: 'none', cellRenderer: (row) => formatCurrency(row.net_amount), exportValue: (row) => formatCurrency(row.net_amount) },
    { field: 'available_at', headerName: 'Disponível em', width: 165, filterType: 'none', cellRenderer: (row) => (row.available_at ? formatDateTimeBR(row.available_at) : '—'), exportValue: (row) => (row.available_at ? formatDateTimeBR(row.available_at) : '') },
    { field: 'settlement', headerName: 'Repasse', width: 150, filterType: 'none', sortable: false, cellRenderer: (row) => row.settlement?.code ?? '—' },
  ], [])

  const settlementColumns = useMemo<ServerGridColumn<FinanceSettlement>[]>(() => [
    { field: 'tenant', headerName: 'Empresa', width: 220, filterType: 'none', sortable: false, cellRenderer: (row) => row.tenant?.name ?? '—' },
    { field: 'code', headerName: 'Código', width: 180, filterType: 'none' },
    { field: 'status', headerName: 'Status', width: 160, filterType: 'none', sortable: false },
    { field: 'net_amount', headerName: 'Líquido', width: 130, filterType: 'none', cellRenderer: (row) => formatCurrency(row.net_amount), exportValue: (row) => formatCurrency(row.net_amount) },
    { field: 'receivables_count', headerName: 'Recebíveis', width: 120, filterType: 'none', cellRenderer: (row) => String(row.receivables_count) },
    { field: 'scheduled_for', headerName: 'Agendado para', width: 165, filterType: 'none', cellRenderer: (row) => (row.scheduled_for ? formatDateTimeBR(row.scheduled_for) : '—'), exportValue: (row) => (row.scheduled_for ? formatDateTimeBR(row.scheduled_for) : '') },
  ], [])

  const adjustmentColumns = useMemo<ServerGridColumn<FinanceAdjustment>[]>(() => [
    { field: 'tenant', headerName: 'Empresa', width: 220, filterType: 'none', sortable: false, cellRenderer: (row) => row.tenant?.name ?? '—' },
    { field: 'type', headerName: 'Tipo', width: 200, filterType: 'none', sortable: false },
    { field: 'status', headerName: 'Status', width: 170, filterType: 'none', sortable: false },
    { field: 'amount', headerName: 'Valor', width: 120, filterType: 'none', cellRenderer: (row) => formatCurrency(row.amount), exportValue: (row) => formatCurrency(row.amount) },
    { field: 'reason', headerName: 'Motivo', width: 320, filterType: 'none', sortable: false },
    { field: 'created_at', headerName: 'Criado em', width: 165, filterType: 'none', cellRenderer: (row) => formatDateTimeBR(row.created_at), exportValue: (row) => formatDateTimeBR(row.created_at) },
  ], [])

  const fetchReceivables = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<FinanceReceivable>> => {
      const result = await financeService.listAdminFinanceReceivables({
        tenant_uuid: tenantUuid || undefined,
        from,
        to,
        status: status || undefined,
        page,
        per_page: perPage,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [tenantUuid, from, to, status],
  )

  const fetchSettlements = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<FinanceSettlement>> => {
      const result = await financeService.listAdminFinanceSettlements({
        tenant_uuid: tenantUuid || undefined,
        from,
        to,
        status: status || undefined,
        page,
        per_page: perPage,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [tenantUuid, from, to, status],
  )

  const fetchAdjustments = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<FinanceAdjustment>> => {
      const result = await financeService.listAdminFinanceAdjustments({
        tenant_uuid: tenantUuid || undefined,
        from,
        to,
        status: status || undefined,
        page,
        per_page: perPage,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [tenantUuid, from, to, status],
  )

  return (
    <Box sx={{ maxWidth: 1680, mx: 'auto' }}>
      <CrudListPage
        title="Financeiro admin"
        subtitle="Acompanhe a operação financeira cross-tenant do backoffice da plataforma."
        toolbar={
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5, width: '100%' }}>
            <PeriodFilter from={from} to={to} onChange={(nextFrom, nextTo) => { setFrom(nextFrom); setTo(nextTo) }} defaultPreset="last_30" />
            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0,1fr)', md: 'repeat(2, minmax(0,260px))' }, gap: 1.5 }}>
              <TextField select label="Empresa" size="small" value={tenantUuid} onChange={(event) => setTenantUuid(event.target.value)}>
                <MenuItem value="">Todas as empresas</MenuItem>
                {tenants.map((tenant) => (
                  <MenuItem key={tenant.uuid} value={tenant.uuid}>{tenant.name}</MenuItem>
                ))}
              </TextField>
              <TextField label="Status" size="small" value={status} onChange={(event) => setStatus(event.target.value)} placeholder="scheduled, released, pending_review..." />
            </Box>
          </Box>
        }
        error={dashboardError}
        onRetry={loadDashboard}
        isLoading={false}
        isEmpty={false}
      >
        <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 220px), 1fr))', gap: 1.5, mb: 2 }}>
          <MetricCard icon={CorporateFareOutlinedIcon} label="Liberado" value={isLoadingDashboard ? null : formatCurrency(dashboard?.balances.released_amount ?? 0)} tone="primary" isLoading={isLoadingDashboard} index={0} />
          <MetricCard icon={ApartmentOutlinedIcon} label="Em custódia" value={isLoadingDashboard ? null : formatCurrency(dashboard?.balances.in_custody_amount ?? 0)} tone="warning" isLoading={isLoadingDashboard} index={1} />
          <MetricCard icon={RuleOutlinedIcon} label="Exceções abertas" value={isLoadingDashboard ? null : formatCurrency(dashboard?.balances.open_adjustments_amount ?? 0)} caption={dashboard ? `${dashboard.queues.open_adjustments_count} item(ns)` : null} tone="accent" isLoading={isLoadingDashboard} index={2} />
          <MetricCard icon={RequestQuoteOutlinedIcon} label="Disponível agora" value={isLoadingDashboard ? null : formatCurrency(dashboard?.balances.available_now_amount ?? 0)} tone="info" isLoading={isLoadingDashboard} index={3} />
        </Box>

        <Box sx={{ borderBottom: '1px solid var(--pt-divider)', mb: 2 }}>
          <Tabs value={tab} onChange={(_, value: FinanceAdminTab) => setTab(value)} variant="scrollable" allowScrollButtonsMobile>
            <Tab value="receivables" label="Recebíveis" />
            <Tab value="settlements" label="Repasses" />
            <Tab value="adjustments" label="Exceções" />
          </Tabs>
        </Box>

        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 1180 }}>
            {tab === 'receivables' && (
              <ServerDataGrid columns={receivableColumns} fetchPage={fetchReceivables} rowIdField="uuid" exportFileName="admin-financeiro-recebiveis" emptyState={{ icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />, title: 'Nenhum recebível encontrado', description: 'Ajuste os filtros para localizar recebíveis entre as empresas.' }} />
            )}
            {tab === 'settlements' && (
              <ServerDataGrid columns={settlementColumns} fetchPage={fetchSettlements} rowIdField="uuid" exportFileName="admin-financeiro-repasses" emptyState={{ icon: <RequestQuoteOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />, title: 'Nenhum repasse encontrado', description: 'Quando houver repasses gerados, eles aparecerão aqui.' }} />
            )}
            {tab === 'adjustments' && (
              <ServerDataGrid columns={adjustmentColumns} fetchPage={fetchAdjustments} rowIdField="uuid" exportFileName="admin-financeiro-excecoes" emptyState={{ icon: <RuleOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />, title: 'Nenhuma exceção encontrada', description: 'Quando houver ajustes financeiros cross-tenant, eles aparecerão aqui.' }} />
            )}
          </Box>
        </Box>
      </CrudListPage>
    </Box>
  )
}
