import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import PercentOutlinedIcon from '@mui/icons-material/PercentOutlined'
import ReplayOutlinedIcon from '@mui/icons-material/ReplayOutlined'
import RequestQuoteOutlinedIcon from '@mui/icons-material/RequestQuoteOutlined'
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
import { formatCurrency, formatPercentage } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

/**
 * Relatório de reembolsos/chargebacks dedicado (roadmap Fase A2) — volume,
 * valor, motivo (texto livre, agregado por melhor esforço) e taxa de
 * reembolso sobre vendas pagas no período.
 */
export function RefundsTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getRefundsReport({ from, to }),
    `${from}|${to}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  const reasons = data?.top_reasons ?? []

  function handleExportCsv() {
    if (!data) return
    const headers = ['Motivo', 'Quantidade', 'Valor total']
    const rows = reasons.map((item) => [item.reason, String(item.count), formatCurrency(item.total_amount)])
    downloadTextFile(buildCsvContent(headers, rows), 'relatorio-reembolsos.csv', 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Reembolsos e chargebacks
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Volume, valor e motivo dos estornos registrados no período selecionado.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={reasons.length === 0}
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
          icon={ReplayOutlinedIcon}
          label="Reembolsos no período"
          value={data ? String(data.totals.refunds_count) : null}
          tone="primary"
          isLoading={isLoading}
          index={0}
        />
        <MetricCard
          icon={RequestQuoteOutlinedIcon}
          label="Valor total reembolsado"
          value={data ? formatCurrency(data.totals.total_refunded_amount) : null}
          tone="warning"
          isLoading={isLoading}
          index={1}
        />
        <MetricCard
          icon={PercentOutlinedIcon}
          label="Taxa de reembolso (pedidos)"
          value={data ? formatPercentage(data.totals.refund_rate_percentage) : null}
          caption="Sobre vendas pagas no período"
          tone={data && data.totals.refund_rate_percentage >= 10 ? 'warning' : 'primary'}
          isLoading={isLoading}
          index={2}
        />
        <MetricCard
          icon={PercentOutlinedIcon}
          label="Taxa de reembolso (valor)"
          value={data ? formatPercentage(data.totals.refund_amount_rate_percentage) : null}
          caption="Sobre receita paga no período"
          tone={data && data.totals.refund_amount_rate_percentage >= 10 ? 'warning' : 'primary'}
          isLoading={isLoading}
          index={3}
        />
      </Box>

      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' },
          gap: 1.5,
          mb: 2.5,
        }}
      >
        <Paper variant="outlined" sx={{ p: 2, borderRadius: 'var(--pt-radius-md, 12px)' }}>
          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', fontWeight: 600, mb: 0.5 }}>
            Estorno total
          </Typography>
          <Typography sx={{ fontSize: 20, fontWeight: 700, color: 'var(--pt-text)' }}>
            {data ? data.by_type.total.count : '—'}
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            {data ? formatCurrency(data.by_type.total.total_amount) : ''}
          </Typography>
        </Paper>
        <Paper variant="outlined" sx={{ p: 2, borderRadius: 'var(--pt-radius-md, 12px)' }}>
          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', fontWeight: 600, mb: 0.5 }}>
            Estorno parcial
          </Typography>
          <Typography sx={{ fontSize: 20, fontWeight: 700, color: 'var(--pt-text)' }}>
            {data ? data.by_type.parcial.count : '—'}
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            {data ? formatCurrency(data.by_type.parcial.total_amount) : ''}
          </Typography>
        </Paper>
      </Box>

      <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--pt-text)', mb: 1 }}>
        Principais motivos
      </Typography>

      {isLoading ? (
        <Stack spacing={1}>
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
          ))}
        </Stack>
      ) : reasons.length === 0 ? (
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
            Nenhum reembolso no período
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>Ajuste o período acima para ver os motivos de estorno.</Typography>
        </Box>
      ) : (
        <Box sx={{ overflowX: 'auto' }}>
          <Table size="small" sx={{ minWidth: 520, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Motivo</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Ocorrências</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Valor total</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {reasons.map((item, index) => (
                <TableRow key={`${item.reason}-${index}`} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{item.reason}</TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.count}
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
