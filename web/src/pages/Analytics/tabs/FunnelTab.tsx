import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import { Box, Button, LinearProgress, Paper, Stack, Typography } from '@mui/material'
import { useEffect } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import { buildCsvContent, downloadTextFile } from '../../../utils/gridExport'
import { formatPercentage } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

/**
 * Funil de conversão anônimo (roadmap A2) — sessões únicas por etapa do
 * storefront (visualização → seleção de ingresso → reserva → checkout →
 * pagamento confirmado). Barras horizontais decrescentes é suficiente
 * nesta fase (Sankey elaborado adiado, ver roadmap 5.4).
 */
export function FunnelTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getFunnelReport({ from, to }),
    `${from}|${to}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  function handleExportCsv() {
    if (!data) return
    const headers = ['Etapa', 'Sessões únicas', 'Conversão vs. etapa anterior', 'Conversão vs. primeira etapa']
    const rows = data.steps.map((step) => [
      step.label,
      String(step.session_count),
      step.conversion_from_previous_percentage === null ? '—' : formatPercentage(step.conversion_from_previous_percentage),
      step.conversion_from_first_percentage === null ? '—' : formatPercentage(step.conversion_from_first_percentage),
    ])
    downloadTextFile(buildCsvContent(headers, rows), 'funil-conversao.csv', 'text/csv;charset=utf-8')
  }

  const maxCount = Math.max(1, ...(data?.steps.map((step) => step.session_count) ?? [1]))

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2.5 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Funil de conversão
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Sessões anônimas por etapa da compra, no período selecionado.
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

      {isLoading && !data && (
        <Stack spacing={2}>
          {[0, 1, 2, 3, 4].map((index) => (
            <LinearProgress key={index} variant="indeterminate" sx={{ borderRadius: 999, height: 10 }} />
          ))}
        </Stack>
      )}

      {data && data.steps.every((step) => step.session_count === 0) && (
        <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
          Nenhuma sessão registrada neste período ainda.
        </Typography>
      )}

      {data && data.steps.some((step) => step.session_count > 0) && (
        <Stack spacing={2.25}>
          {data.steps.map((step) => (
            <Box key={step.step}>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', mb: 0.5, gap: 1 }}>
                <Typography sx={{ fontSize: 13.5, fontWeight: 600, color: 'var(--pt-text)' }}>{step.label}</Typography>
                <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', whiteSpace: 'nowrap' }}>
                  {step.session_count} {step.session_count === 1 ? 'sessão' : 'sessões'}
                  {step.conversion_from_previous_percentage !== null && (
                    <> · {formatPercentage(step.conversion_from_previous_percentage)} vs. etapa anterior</>
                  )}
                </Typography>
              </Box>
              <LinearProgress
                variant="determinate"
                value={(step.session_count / maxCount) * 100}
                sx={{
                  height: 14,
                  borderRadius: 999,
                  backgroundColor: 'var(--pt-surface-muted, rgba(0,0,0,0.06))',
                  '& .MuiLinearProgress-bar': { borderRadius: 999, backgroundColor: 'var(--pt-primary)' },
                }}
              />
            </Box>
          ))}
        </Stack>
      )}
    </Paper>
  )
}
