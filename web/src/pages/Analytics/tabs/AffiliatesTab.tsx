import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import GroupsOutlinedIcon from '@mui/icons-material/GroupsOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import TrendingUpOutlinedIcon from '@mui/icons-material/TrendingUpOutlined'
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

const LIMIT = 20

/**
 * Relatório de afiliados consolidado (roadmap Fase A2) — ranking por
 * comissão gerada/vendas atribuídas, ROI (comissão paga vs. receita
 * atribuída). Filtro obrigatório de período já vem do PeriodFilter da
 * página (regra transversal de filtro ativo).
 */
export function AffiliatesTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getAffiliatesReport({ from, to, limit: LIMIT }),
    `${from}|${to}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  const items = data?.items ?? []

  function handleExportCsv() {
    const headers = ['Afiliado', 'Código', 'Status', 'Vendas atribuídas', 'Receita atribuída', 'Comissão gerada', 'Comissão paga', 'ROI (%)']
    const rows = items.map((item) => [
      item.affiliate_name,
      item.tracking_code,
      item.affiliate_status,
      String(item.attributed_sales_count),
      formatCurrency(item.attributed_revenue),
      formatCurrency(item.commission_amount),
      formatCurrency(item.commission_paid_amount),
      item.roi_percentage === null ? '' : String(item.roi_percentage),
    ])
    downloadTextFile(buildCsvContent(headers, rows), 'relatorio-afiliados.csv', 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Afiliados
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Ranking de comissão gerada, vendas atribuídas e ROI no período selecionado.
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
          icon={GroupsOutlinedIcon}
          label="Afiliados ativos no período"
          value={data ? String(data.totals.affiliates_count) : null}
          tone="primary"
          isLoading={isLoading}
          index={0}
        />
        <MetricCard
          icon={TrendingUpOutlinedIcon}
          label="Receita atribuída"
          value={data ? formatCurrency(data.totals.attributed_revenue) : null}
          tone="accent"
          isLoading={isLoading}
          index={1}
        />
        <MetricCard
          icon={PaidOutlinedIcon}
          label="Comissão paga"
          value={data ? formatCurrency(data.totals.commission_paid_amount) : null}
          caption={data ? `de ${formatCurrency(data.totals.commission_amount)} gerada` : null}
          tone="primary"
          isLoading={isLoading}
          index={2}
        />
        <MetricCard
          icon={TrendingUpOutlinedIcon}
          label="ROI consolidado"
          value={data ? (data.totals.roi_percentage === null ? '—' : formatPercentage(data.totals.roi_percentage)) : null}
          caption="Receita atribuída vs. comissão paga"
          tone={data && data.totals.roi_percentage !== null && data.totals.roi_percentage >= 0 ? 'accent' : 'warning'}
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
            Nenhuma comissão de afiliado no período
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>Ajuste o período acima ou cadastre afiliados com vendas atribuídas.</Typography>
        </Box>
      ) : (
        <Box sx={{ overflowX: 'auto' }}>
          <Table size="small" sx={{ minWidth: 720, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Afiliado</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Vendas</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Receita atribuída</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Comissão paga</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>ROI</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {items.map((item) => (
                <TableRow key={item.affiliate_uuid} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                      <span>{item.affiliate_name}</span>
                      <Chip label={item.tracking_code} size="small" sx={{ height: 20, fontSize: 11, fontWeight: 600 }} />
                    </Stack>
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.attributed_sales_count}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatCurrency(item.attributed_revenue)}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatCurrency(item.commission_paid_amount)}
                  </TableCell>
                  <TableCell
                    align="right"
                    sx={{
                      fontWeight: 600,
                      fontVariantNumeric: 'tabular-nums',
                      color: item.roi_percentage === null ? 'var(--pt-muted)' : item.roi_percentage >= 0 ? 'var(--pt-success)' : 'var(--pt-danger)',
                    }}
                  >
                    {item.roi_percentage === null ? '—' : formatPercentage(item.roi_percentage)}
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
