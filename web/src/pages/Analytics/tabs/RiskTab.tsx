import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import PercentOutlinedIcon from '@mui/icons-material/PercentOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
import {
  Box,
  Button,
  Chip,
  Paper,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useEffect } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { MetricCard } from '../../../components/dashboard/MetricCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { UI_RADIUS } from '../../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import { buildCsvContent, downloadTextFile } from '../../../utils/gridExport'
import { formatCurrency, formatPercentage } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

const HEURISTIC_LABELS: Record<string, string> = {
  compras_repetidas: 'Compras repetidas',
  velocity_pagamento_recusado: 'Velocity de pagamento recusado',
  multiplos_cartoes: 'Múltiplos cartões',
  cartao_compartilhado: 'Cartão compartilhado',
  velocity_ip: 'Velocity de IP',
  abuso_reembolso: 'Abuso de reembolso',
  outro: 'Outro',
}

/**
 * Relatório de antifraude (roadmap Fase A3) — consome `sales.risk_flagged`/
 * `risk_reason` já persistidos por RiskEngineService (motor não é alterado
 * aqui, só lido). Contagem por heurística, taxa sobre vendas pagas e lista
 * de vendas flagadas para revisão manual — NUNCA bloqueio automático.
 */
export function RiskTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getRiskReport({ from, to, limit: 50 }),
    `${from}|${to}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  const flaggedSales = data?.flagged_sales ?? []
  const byHeuristic = data?.by_heuristic ?? []

  function handleExportCsv() {
    if (!data) return
    const headers = ['Venda', 'Cliente', 'Valor', 'Paga', 'Data', 'Heurística', 'Motivo']
    const rows = flaggedSales.map((item) => [
      item.sale_uuid,
      item.client_name ?? '',
      formatCurrency(item.total_amount),
      item.is_paid ? 'Sim' : 'Não',
      item.created_at,
      HEURISTIC_LABELS[item.heuristic ?? ''] ?? (item.heuristic ?? ''),
      item.risk_reason ?? '',
    ])
    downloadTextFile(buildCsvContent(headers, rows), 'relatorio-antifraude.csv', 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Antifraude
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Vendas sinalizadas pelo motor de risco no período — só alerta informativo, revise manualmente antes de agir.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={flaggedSales.length === 0}
          sx={{ minHeight: 40 }}
        >
          Exportar CSV
        </Button>
      </Box>

      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', lg: 'repeat(4,1fr)' },
          gap: 1.5,
          mb: 2.5,
        }}
      >
        <MetricCard
          icon={WarningAmberOutlinedIcon}
          label="Vendas flagadas"
          value={data ? String(data.totals.flagged_count) : null}
          tone="warning"
          isLoading={isLoading}
          index={0}
        />
        <MetricCard
          icon={PaidOutlinedIcon}
          label="Valor flagado"
          value={data ? formatCurrency(data.totals.flagged_amount) : null}
          tone="warning"
          isLoading={isLoading}
          index={1}
        />
        <MetricCard
          icon={PercentOutlinedIcon}
          label="Taxa sobre vendas pagas"
          value={data ? formatPercentage(data.totals.flagged_rate_percentage) : null}
          caption="Vendas flagadas / vendas pagas no período"
          tone={data && data.totals.flagged_rate_percentage >= 5 ? 'warning' : 'primary'}
          isLoading={isLoading}
          index={2}
        />
        <MetricCard
          icon={PaidOutlinedIcon}
          label="Vendas pagas no período"
          value={data ? String(data.totals.paid_sales_count) : null}
          tone="primary"
          isLoading={isLoading}
          index={3}
        />
      </Box>

      <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--pt-text)', mb: 1 }}>
        Por heurística
      </Typography>

      {isLoading ? (
        <Skeleton variant="rounded" height={40} sx={{ mb: 2.5, borderRadius: UI_RADIUS.md }} />
      ) : byHeuristic.length === 0 ? (
        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2.5 }}>
          Nenhuma venda flagada no período.
        </Typography>
      ) : (
        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mb: 2.5 }}>
          {byHeuristic.map((item) => (
            <Chip
              key={item.heuristic}
              label={`${HEURISTIC_LABELS[item.heuristic] ?? item.heuristic}: ${item.count}`}
              size="small"
              sx={{
                fontWeight: 600,
                color: 'var(--pt-warning)',
                bgcolor: 'color-mix(in srgb, var(--pt-warning) 14%, transparent)',
                border: '1px solid color-mix(in srgb, var(--pt-warning) 35%, transparent)',
              }}
            />
          ))}
        </Box>
      )}

      <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--pt-text)', mb: 1 }}>
        Vendas flagadas para revisão
      </Typography>

      {isLoading ? (
        <Stack spacing={1}>
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
          ))}
        </Stack>
      ) : flaggedSales.length === 0 ? (
        <Box
          sx={{
            minHeight: 160,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            textAlign: 'center',
            gap: 0.5,
            color: 'var(--pt-muted)',
          }}
        >
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>
            Nenhuma venda flagada no período
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>Ajuste o período acima para revisar alertas de fraude.</Typography>
        </Box>
      ) : (
        <Box sx={{ overflowX: 'auto' }}>
          <Table size="small" sx={{ minWidth: 720, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Cliente</TableCell>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Heurística</TableCell>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Data</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Valor</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {flaggedSales.map((item) => (
                <TableRow key={item.sale_uuid} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>
                    {item.client_name ?? 'Sem cliente'}
                  </TableCell>
                  <TableCell sx={{ color: 'var(--pt-text)' }}>
                    {HEURISTIC_LABELS[item.heuristic ?? ''] ?? (item.heuristic ?? '—')}
                  </TableCell>
                  <TableCell sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.created_at}
                  </TableCell>
                  <TableCell align="right" sx={{ fontWeight: 600, color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatCurrency(item.total_amount)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Box>
      )}
    </Paper>
  )
}
