import DownloadOutlinedIcon from '@mui/icons-material/DownloadOutlined'
import {
  Alert,
  Box,
  Button,
  Divider,
  Paper,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { PeriodFilter } from '../../components/analytics/PeriodFilter'
import * as accountingService from '../../services/accountingService'
import { UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type {
  AccountingCashFlowReport,
  AccountingDreReport,
  AccountingReportKind,
  AccountingSalesReport,
} from '../../types/accounting'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency, formatDateBR } from '../../utils/format'
import { presetRange } from '../../utils/period'

const INITIAL = presetRange('last_12_months')

function ReportCard({
  title,
  subtitle,
  onExport,
  isExporting,
  children,
}: {
  title: string
  subtitle: string
  onExport: () => void
  isExporting: boolean
  children: React.ReactNode
}) {
  return (
    <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 2, sm: 2.5 } }}>
      <Stack direction="row" sx={{ alignItems: 'flex-start', justifyContent: 'space-between', gap: 1.5, mb: 1.5 }}>
        <Box sx={{ minWidth: 0 }}>
          <Typography sx={{ fontSize: 16, fontWeight: 700 }}>{title}</Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>{subtitle}</Typography>
        </Box>
        <Button
          onClick={onExport}
          size="small"
          variant="outlined"
          startIcon={<DownloadOutlinedIcon fontSize="small" />}
          disabled={isExporting}
          sx={{ minHeight: UI_SIZE.compactControl, whiteSpace: 'nowrap' }}
        >
          {isExporting ? 'Gerando…' : 'CSV'}
        </Button>
      </Stack>
      {children}
    </Paper>
  )
}

function Metric({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <Box>
      <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>{label}</Typography>
      <Typography sx={{ fontSize: strong ? 20 : 17, fontWeight: 700 }}>{value}</Typography>
    </Box>
  )
}

export function AccountingCompanyReportsPage() {
  const { tenantUuid } = useParams<{ tenantUuid: string }>()
  const [from, setFrom] = useState(INITIAL.from)
  const [to, setTo] = useState(INITIAL.to)

  const [sales, setSales] = useState<AccountingSalesReport | null>(null)
  const [cashFlow, setCashFlow] = useState<AccountingCashFlowReport | null>(null)
  const [dre, setDre] = useState<AccountingDreReport | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [exporting, setExporting] = useState<AccountingReportKind | null>(null)
  const [exportError, setExportError] = useState<string | null>(null)

  const load = useCallback(async () => {
    if (!tenantUuid) return
    setIsLoading(true)
    setError(null)
    const period = { from, to }
    try {
      const [salesData, cashData, dreData] = await Promise.all([
        accountingService.getSalesReport(tenantUuid, period),
        accountingService.getCashFlowReport(tenantUuid, period),
        accountingService.getDreReport(tenantUuid, period),
      ])
      setSales(salesData)
      setCashFlow(cashData)
      setDre(dreData)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Não foi possível carregar os relatórios deste período.'))
    } finally {
      setIsLoading(false)
    }
  }, [tenantUuid, from, to])

  useEffect(() => {
    void load()
  }, [load])

  const handleExport = useCallback(
    async (kind: AccountingReportKind) => {
      if (!tenantUuid) return
      setExportError(null)
      setExporting(kind)
      try {
        await accountingService.downloadReportCsv(tenantUuid, kind, { from, to })
      } catch (err) {
        setExportError(getApiErrorMessage(err, 'Não foi possível exportar o CSV agora.'))
      } finally {
        setExporting(null)
      }
    },
    [tenantUuid, from, to],
  )

  return (
    <Box>
      <PeriodFilter from={from} to={to} onChange={(nextFrom, nextTo) => { setFrom(nextFrom); setTo(nextTo) }} />

      {exportError && (
        <Alert severity="error" variant="outlined" sx={{ mb: 2 }} onClose={() => setExportError(null)}>
          {exportError}
        </Alert>
      )}

      {isLoading && (
        <Stack spacing={2}>
          <Skeleton variant="rounded" height={140} />
          <Skeleton variant="rounded" height={140} />
          <Skeleton variant="rounded" height={140} />
        </Stack>
      )}

      {!isLoading && error && (
        <Alert severity="error" variant="outlined" action={<Button size="small" onClick={() => void load()}>Tentar de novo</Button>}>
          {error}
        </Alert>
      )}

      {!isLoading && !error && (
        <Stack spacing={2.5}>
          <ReportCard
            title="Vendas"
            subtitle="Pedidos do período (exclui cancelados)."
            onExport={() => void handleExport('sales')}
            isExporting={exporting === 'sales'}
          >
            {sales && (
              <>
                <Stack direction="row" spacing={4} sx={{ mb: sales.items.length ? 2 : 0 }}>
                  <Metric label="Pedidos" value={String(sales.total_orders)} />
                  <Metric label="Receita" value={formatCurrency(sales.total_revenue)} strong />
                </Stack>
                {sales.items.length === 0 ? (
                  <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>Nenhuma venda no período.</Typography>
                ) : (
                  <TableContainer sx={{ overflowX: 'auto' }}>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>Cliente</TableCell>
                          <TableCell>Data</TableCell>
                          <TableCell align="right">Valor</TableCell>
                          <TableCell>Pago</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {sales.items.slice(0, 50).map((item) => (
                          <TableRow key={item.order_uuid}>
                            <TableCell>{item.client_name ?? '—'}</TableCell>
                            <TableCell>{item.created_at ? formatDateBR(item.created_at) : '—'}</TableCell>
                            <TableCell align="right">{formatCurrency(item.total_amount)}</TableCell>
                            <TableCell>{item.is_paid ? 'Sim' : 'Não'}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                    {sales.items.length > 50 && (
                      <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mt: 1 }}>
                        Mostrando 50 de {sales.items.length}. Exporte o CSV para a lista completa.
                      </Typography>
                    )}
                  </TableContainer>
                )}
              </>
            )}
          </ReportCard>

          <ReportCard
            title="Livro-caixa"
            subtitle="Entradas: pedidos pagos no período (por data de pagamento)."
            onExport={() => void handleExport('cash-flow')}
            isExporting={exporting === 'cash-flow'}
          >
            {cashFlow && (
              <>
                <Box sx={{ mb: cashFlow.entries.length ? 2 : 0 }}>
                  <Metric label="Total recebido" value={formatCurrency(cashFlow.total_in)} strong />
                </Box>
                {cashFlow.entries.length === 0 ? (
                  <Typography sx={{ fontSize: 13.5, color: 'var(--mk-muted)' }}>Nenhuma entrada no período.</Typography>
                ) : (
                  <TableContainer sx={{ overflowX: 'auto' }}>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>Data</TableCell>
                          <TableCell>Cliente</TableCell>
                          <TableCell align="right">Valor</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {cashFlow.entries.slice(0, 50).map((entry) => (
                          <TableRow key={entry.order_uuid}>
                            <TableCell>{entry.date ? formatDateBR(entry.date) : '—'}</TableCell>
                            <TableCell>{entry.client_name ?? '—'}</TableCell>
                            <TableCell align="right">{formatCurrency(entry.amount)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                    {cashFlow.entries.length > 50 && (
                      <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mt: 1 }}>
                        Mostrando 50 de {cashFlow.entries.length}. Exporte o CSV para a lista completa.
                      </Typography>
                    )}
                  </TableContainer>
                )}
              </>
            )}
          </ReportCard>

          <ReportCard
            title="DRE simplificado"
            subtitle="Receita menos custo de produto (quando o custo está cadastrado)."
            onExport={() => void handleExport('dre')}
            isExporting={exporting === 'dre'}
          >
            {dre && (
              <Stack
                direction={{ xs: 'column', sm: 'row' }}
                spacing={{ xs: 1.5, sm: 4 }}
                divider={<Divider orientation="vertical" flexItem />}
              >
                <Metric label="Receita" value={formatCurrency(dre.revenue)} />
                <Metric label="Custo de produto" value={formatCurrency(dre.product_cost)} />
                <Metric label="Lucro bruto" value={formatCurrency(dre.gross_profit)} strong />
              </Stack>
            )}
          </ReportCard>
        </Stack>
      )}
    </Box>
  )
}
