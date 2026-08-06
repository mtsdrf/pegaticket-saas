import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import SwapHorizOutlinedIcon from '@mui/icons-material/SwapHorizOutlined'
import SyncAltOutlinedIcon from '@mui/icons-material/SyncAltOutlined'
import { Box, Button, Paper, Skeleton, Typography } from '@mui/material'
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
 * Relatório de revenda/transferência (roadmap Fase A3) — consome
 * `TicketResaleListing` (revenda oficial) e `TicketTransferred` (transferência
 * simples, aproximada por exclusão — ver docblock de
 * `AnalyticsService::resaleReport()`).
 */
export function ResaleTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getResaleReport({ from, to }),
    `${from}|${to}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  function handleExportCsv() {
    if (!data) return
    const headers = ['Indicador', 'Valor']
    const rows = [
      ['Anúncios de revenda criados', String(data.resale.listed_count)],
      ['Revendas concluídas', String(data.resale.sold_count)],
      ['Revendas canceladas', String(data.resale.cancelled_count)],
      ['Valor total transacionado', formatCurrency(data.resale.total_transacted_amount)],
      ['Repasse total aos vendedores', formatCurrency(data.resale.total_payout_amount)],
      ['Repasse pendente (qtd)', String(data.payout_by_status.pendente_liberacao.count)],
      ['Repasse pendente (valor)', formatCurrency(data.payout_by_status.pendente_liberacao.total_amount)],
      ['Repasse liberado (qtd)', String(data.payout_by_status.liberado.count)],
      ['Repasse liberado (valor)', formatCurrency(data.payout_by_status.liberado.total_amount)],
      ['Transferências totais', String(data.transfers.total_count)],
      ['Transferências por revenda', String(data.transfers.resale_count)],
      ['Transferências simples (sem revenda)', String(data.transfers.simple_count)],
    ]
    downloadTextFile(buildCsvContent(headers, rows), 'relatorio-revenda-transferencia.csv', 'text/csv;charset=utf-8')
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Revenda e transferência
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Volume/valor de revenda oficial, status de repasse ao vendedor e transferências simples no período.
          </Typography>
        </Box>

        <Button
          variant="outlined"
          size="small"
          startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
          onClick={handleExportCsv}
          disabled={!data}
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
          icon={SwapHorizOutlinedIcon}
          label="Revendas concluídas"
          value={data ? String(data.resale.sold_count) : null}
          caption={data ? `${data.resale.listed_count} anúncios criados` : undefined}
          tone="primary"
          isLoading={isLoading}
          index={0}
        />
        <MetricCard
          icon={PaidOutlinedIcon}
          label="Valor transacionado"
          value={data ? formatCurrency(data.resale.total_transacted_amount) : null}
          tone="primary"
          isLoading={isLoading}
          index={1}
        />
        <MetricCard
          icon={PaidOutlinedIcon}
          label="Repasse pendente"
          value={data ? formatCurrency(data.payout_by_status.pendente_liberacao.total_amount) : null}
          caption={data ? `${data.payout_by_status.pendente_liberacao.count} revenda(s)` : undefined}
          tone="warning"
          isLoading={isLoading}
          index={2}
        />
        <MetricCard
          icon={SyncAltOutlinedIcon}
          label="Transferências simples"
          value={data ? String(data.transfers.simple_count) : null}
          caption={data ? `${data.transfers.total_count} transferências no total` : undefined}
          tone="primary"
          isLoading={isLoading}
          index={3}
        />
      </Box>

      {isLoading ? (
        <Skeleton variant="rounded" height={90} sx={{ borderRadius: UI_RADIUS.md }} />
      ) : (
        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' },
            gap: 1.5,
          }}
        >
          <Paper variant="outlined" sx={{ p: 2, borderRadius: 'var(--pt-radius-md, 12px)' }}>
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', fontWeight: 600, mb: 0.5 }}>
              Repasse liberado
            </Typography>
            <Typography sx={{ fontSize: 20, fontWeight: 700, color: 'var(--pt-text)' }}>
              {data?.payout_by_status.liberado.count ?? '—'}
            </Typography>
            <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
              {data ? formatCurrency(data.payout_by_status.liberado.total_amount) : ''}
            </Typography>
          </Paper>
          <Paper variant="outlined" sx={{ p: 2, borderRadius: 'var(--pt-radius-md, 12px)' }}>
            <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', fontWeight: 600, mb: 0.5 }}>
              Revendas canceladas
            </Typography>
            <Typography sx={{ fontSize: 20, fontWeight: 700, color: 'var(--pt-text)' }}>
              {data?.resale.cancelled_count ?? '—'}
            </Typography>
          </Paper>
        </Box>
      )}
    </Paper>
  )
}
