import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import HourglassEmptyOutlinedIcon from '@mui/icons-material/HourglassEmptyOutlined'
import BlockOutlinedIcon from '@mui/icons-material/BlockOutlined'
import { Box, Button, Paper, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { MetricCard } from '../../../components/dashboard/MetricCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import { buildCsvContent, downloadTextFile } from '../../../utils/gridExport'
import { formatCurrency, formatPercentage } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

/**
 * Relatório de pagamentos básico (roadmap Fase A1) — aprovação/recusa por
 * período. SEM roteamento entre gateways (só PagBank existe no produto).
 */
export function PaymentsTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getPaymentsSummary({ from, to }),
    `${from}|${to}`,
  )
  const [isExportingXlsx, setIsExportingXlsx] = useState(false)

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  function handleExportCsv() {
    if (!data) return
    const headers = ['Status', 'Quantidade', 'Valor total']
    const rows = [
      ['Confirmadas', String(data.confirmed.count), formatCurrency(data.confirmed.total_amount)],
      ['Recusadas', String(data.rejected.count), formatCurrency(data.rejected.total_amount)],
      ['Aguardando aprovação', String(data.pending_approval.count), formatCurrency(data.pending_approval.total_amount)],
      ['Taxa de aprovação', formatPercentage(data.approval_rate_percentage), ''],
      ['Taxa de recusa', formatPercentage(data.rejection_rate_percentage), ''],
    ]
    downloadTextFile(buildCsvContent(headers, rows), 'relatorio-pagamentos.csv', 'text/csv;charset=utf-8')
  }

  async function handleExportXlsx() {
    setIsExportingXlsx(true)
    try {
      await analyticsService.exportPaymentsXlsx({ from, to })
    } catch {
      // best-effort — sem toast dedicado nesta fase, mesmo espírito do CSV acima
    } finally {
      setIsExportingXlsx(false)
    }
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Pagamentos
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Aprovação e recusa de vendas no período selecionado.
          </Typography>
        </Box>

        <Box sx={{ display: 'flex', gap: 1 }}>
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
          <Button
            variant="outlined"
            size="small"
            startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
            onClick={() => void handleExportXlsx()}
            disabled={!data || isExportingXlsx}
            sx={{ minHeight: 40 }}
          >
            {isExportingXlsx ? 'Exportando…' : 'Exportar XLSX'}
          </Button>
        </Box>
      </Box>

      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', lg: 'repeat(5,1fr)' },
          gap: 1.5,
        }}
      >
        <MetricCard
          icon={PaidOutlinedIcon}
          label="Confirmadas"
          value={data ? String(data.confirmed.count) : null}
          caption={data ? formatCurrency(data.confirmed.total_amount) : null}
          tone="accent"
          isLoading={isLoading}
          index={0}
        />
        <MetricCard
          icon={BlockOutlinedIcon}
          label="Recusadas"
          value={data ? String(data.rejected.count) : null}
          caption={data ? formatCurrency(data.rejected.total_amount) : null}
          tone={data && data.rejected.count > 0 ? 'warning' : 'primary'}
          isLoading={isLoading}
          index={1}
        />
        <MetricCard
          icon={HourglassEmptyOutlinedIcon}
          label="Aguardando aprovação"
          value={data ? String(data.pending_approval.count) : null}
          caption={data ? formatCurrency(data.pending_approval.total_amount) : null}
          tone="primary"
          isLoading={isLoading}
          index={2}
        />
        <MetricCard
          icon={PaidOutlinedIcon}
          label="Taxa de aprovação"
          value={data ? formatPercentage(data.approval_rate_percentage) : null}
          caption="Sobre vendas decididas"
          tone="accent"
          isLoading={isLoading}
          index={3}
        />
        <MetricCard
          icon={BlockOutlinedIcon}
          label="Taxa de recusa"
          value={data ? formatPercentage(data.rejection_rate_percentage) : null}
          caption="Sobre vendas decididas"
          tone={data && data.rejection_rate_percentage >= 20 ? 'warning' : 'primary'}
          isLoading={isLoading}
          index={4}
        />
      </Box>
    </Paper>
  )
}
