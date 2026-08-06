import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import PointOfSaleOutlinedIcon from '@mui/icons-material/PointOfSaleOutlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import {
  Box,
  Button,
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
import { formatCurrency } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

/**
 * Relatório de bilheteria por operador/terminal (roadmap Fase A3) —
 * `ticket_checkins` (operator_id/gate_name), vendas presenciais por
 * operador (`sales.created_by`) e sessões de caixa (`cash_sessions`).
 */
export function OperatorsTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getOperatorReport({ from, to }),
    `${from}|${to}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  const byOperator = data?.checkins_by_operator ?? []
  const byGate = data?.checkins_by_gate ?? []
  const salesByOperator = data?.sales_by_operator ?? []

  const totalCheckins = byOperator.reduce((sum, item) => sum + item.total_reads, 0)
  const totalGranted = byOperator.reduce((sum, item) => sum + item.granted_reads, 0)
  const totalSalesCount = salesByOperator.reduce((sum, item) => sum + item.sales_count, 0)
  const totalSalesAmount = salesByOperator.reduce((sum, item) => sum + item.total_amount, 0)

  function handleExportCsv() {
    if (!data) return
    const headers = ['Operador', 'Check-ins', 'Liberados', 'Alertas', 'Bloqueados', 'Vendas', 'Valor vendido']
    const salesByOperatorId = new Map(salesByOperator.map((item) => [item.operator_id, item]))
    const rows = byOperator.map((item) => {
      const sales = salesByOperatorId.get(item.operator_id)
      return [
        item.operator_name,
        String(item.total_reads),
        String(item.granted_reads),
        String(item.warning_reads),
        String(item.blocked_reads),
        String(sales?.sales_count ?? 0),
        formatCurrency(sales?.total_amount ?? 0),
      ]
    })
    downloadTextFile(buildCsvContent(headers, rows), 'relatorio-bilheteria-operador.csv', 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Bilheteria por operador
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Check-ins por operador/portaria e vendas presenciais por operador no período.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={byOperator.length === 0}
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
          icon={QrCodeScannerOutlinedIcon}
          label="Check-ins no período"
          value={data ? String(totalCheckins) : null}
          tone="primary"
          isLoading={isLoading}
          index={0}
        />
        <MetricCard
          icon={QrCodeScannerOutlinedIcon}
          label="Check-ins liberados"
          value={data ? String(totalGranted) : null}
          tone="primary"
          isLoading={isLoading}
          index={1}
        />
        <MetricCard
          icon={PointOfSaleOutlinedIcon}
          label="Vendas presenciais"
          value={data ? String(totalSalesCount) : null}
          tone="primary"
          isLoading={isLoading}
          index={2}
        />
        <MetricCard
          icon={PointOfSaleOutlinedIcon}
          label="Valor vendido no balcão"
          value={data ? formatCurrency(totalSalesAmount) : null}
          tone="primary"
          isLoading={isLoading}
          index={3}
        />
      </Box>

      <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--pt-text)', mb: 1 }}>
        Por operador
      </Typography>

      {isLoading ? (
        <Stack spacing={1} sx={{ mb: 2.5 }}>
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
          ))}
        </Stack>
      ) : byOperator.length === 0 ? (
        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2.5 }}>
          Nenhum check-in registrado no período.
        </Typography>
      ) : (
        <Box sx={{ overflowX: 'auto', mb: 2.5 }}>
          <Table size="small" sx={{ minWidth: 620, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Operador</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Check-ins</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Liberados</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Alertas</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Bloqueados</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {byOperator.map((item) => (
                <TableRow key={`${item.operator_id}-${item.operator_name}`} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{item.operator_name}</TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.total_reads}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.granted_reads}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.warning_reads}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.blocked_reads}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Box>
      )}

      <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--pt-text)', mb: 1 }}>
        Por portaria
      </Typography>

      {isLoading ? (
        <Skeleton variant="rounded" height={80} sx={{ borderRadius: UI_RADIUS.md }} />
      ) : byGate.length === 0 ? (
        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
          Nenhum check-in registrado no período.
        </Typography>
      ) : (
        <Box sx={{ overflowX: 'auto' }}>
          <Table size="small" sx={{ minWidth: 520, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Portaria</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Check-ins</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Liberados</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {byGate.map((item) => (
                <TableRow key={item.gate_name} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{item.gate_name}</TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.total_reads}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.granted_reads}
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
