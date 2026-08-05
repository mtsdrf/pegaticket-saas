import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import LocalOfferOutlinedIcon from '@mui/icons-material/LocalOfferOutlined'
import ReportProblemOutlinedIcon from '@mui/icons-material/ReportProblemOutlined'
import SellOutlinedIcon from '@mui/icons-material/SellOutlined'
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
  Tooltip,
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

const LIMIT = 20

/**
 * Relatório de cupons consolidado (roadmap Fase A2) — ranking de uso,
 * taxa de conversão, valor descontado e sinal de abuso (heurística
 * própria de concentração, ver AnalyticsService::couponsReport).
 */
export function CouponsTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getCouponsReport({ from, to, limit: LIMIT }),
    `${from}|${to}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  const items = data?.items ?? []

  function handleExportCsv() {
    const headers = ['Cupom', 'Tipo', 'Usos', 'Clientes distintos', 'Conversão (%)', 'Desconto total', 'Receita gerada', 'Sinal de abuso']
    const rows = items.map((item) => [
      item.coupon_code,
      item.coupon_type,
      String(item.usage_count),
      String(item.distinct_customers_count),
      String(item.conversion_rate_percentage),
      formatCurrency(item.total_discount_amount),
      formatCurrency(item.revenue_generated),
      item.abuse_signal ? 'Sim' : 'Não',
    ])
    downloadTextFile(buildCsvContent(headers, rows), 'relatorio-cupons.csv', 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Cupons
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Ranking de uso, conversão e valor descontado no período selecionado.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={items.length === 0}
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
          icon={LocalOfferOutlinedIcon}
          label="Cupons usados"
          value={data ? String(data.totals.coupons_used_count) : null}
          tone="primary"
          isLoading={isLoading}
          index={0}
        />
        <MetricCard
          icon={SellOutlinedIcon}
          label="Usos no período"
          value={data ? String(data.totals.total_redemptions) : null}
          tone="accent"
          isLoading={isLoading}
          index={1}
        />
        <MetricCard
          icon={SellOutlinedIcon}
          label="Desconto total concedido"
          value={data ? formatCurrency(data.totals.total_discount_amount) : null}
          tone="primary"
          isLoading={isLoading}
          index={2}
        />
        <MetricCard
          icon={ReportProblemOutlinedIcon}
          label="Cupons com sinal de abuso"
          value={data ? String(data.totals.coupons_with_abuse_signal_count) : null}
          tone={data && data.totals.coupons_with_abuse_signal_count > 0 ? 'warning' : 'primary'}
          isLoading={isLoading}
          index={3}
        />
      </Box>

      {isLoading ? (
        <Stack spacing={1}>
          {Array.from({ length: 5 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
          ))}
        </Stack>
      ) : items.length === 0 ? (
        <Box
          sx={{
            minHeight: 200,
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
            Nenhum cupom usado no período
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>Ajuste o período acima para ver o ranking de cupons.</Typography>
        </Box>
      ) : (
        <Box sx={{ overflowX: 'auto' }}>
          <Table size="small" sx={{ minWidth: 760, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Cupom</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Usos</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Clientes distintos</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Conversão</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Desconto total</TableCell>
                <TableCell align="center" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Abuso</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {items.map((item) => (
                <TableRow key={item.coupon_uuid} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{item.coupon_code}</TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.usage_count}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.distinct_customers_count}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatPercentage(item.conversion_rate_percentage)}
                  </TableCell>
                  <TableCell align="right" sx={{ fontWeight: 600, color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatCurrency(item.total_discount_amount)}
                  </TableCell>
                  <TableCell align="center">
                    {item.abuse_signal ? (
                      <Tooltip title={`Média de ${item.avg_uses_per_customer} usos por cliente`}>
                        <Chip
                          label="Revisar"
                          size="small"
                          icon={<ReportProblemOutlinedIcon sx={{ fontSize: 14 }} />}
                          sx={{
                            height: 22,
                            fontSize: 11.5,
                            fontWeight: 600,
                            color: 'var(--pt-warning)',
                            bgcolor: 'color-mix(in srgb, var(--pt-warning) 14%, transparent)',
                            border: '1px solid color-mix(in srgb, var(--pt-warning) 35%, transparent)',
                          }}
                        />
                      </Tooltip>
                    ) : (
                      <span style={{ color: 'var(--pt-muted)' }}>—</span>
                    )}
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
