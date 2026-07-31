import { Box } from '@mui/material'
import { useEffect, useMemo } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { ChartCard } from '../../../components/analytics/ChartCard'
import { SalesByHourHeatmapCard } from '../../../components/analytics/SalesByHourHeatmapCard'
import { SeasonalityLinesChart } from '../../../components/analytics/SeasonalityLinesChart'
import { SeasonalityMatrixCard } from '../../../components/dashboard/SeasonalityMatrixCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { FORM_GRID_2_SX } from '../../../styles/layoutStandards'
import type { SeasonalityYearRow } from '../../../types/report'
import type { AnalyticsTabProps } from './types'

type SeasonalityTabProps = Pick<AnalyticsTabProps, 'onPlanLocked'>

/** Sazonalidade usa o histórico completo — não recebe o filtro de período. */
export function SeasonalityTab({ onPlanLocked }: SeasonalityTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getSalesHistory(),
    'sales-history',
  )
  const hourly = useAnalyticsData(() => analyticsService.getSalesByHour(), 'sales-by-hour')

  const isPlanLocked = planLocked || hourly.planLocked
  useEffect(() => {
    if (isPlanLocked) onPlanLocked()
  }, [isPlanLocked, onPlanLocked])

  const matrixRows = useMemo<SeasonalityYearRow[] | null>(() => {
    if (!data) return null
    return data.map((row) => ({
      year: row.year,
      months: row.months.map((month) => ({
        month: month.month,
        count: month.count,
        total_amount: String(month.total_amount),
      })),
    }))
  }, [data])

  const combinedError = error ?? hourly.error
  if (combinedError) {
    return (
      <AnalyticsErrorAlert
        message={combinedError}
        onRetry={() => {
          reload()
          hourly.reload()
        }}
      />
    )
  }

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', xl: 'repeat(2, minmax(0, 1fr))' }, gap: 2.5, alignItems: 'start' }}>
        <ChartCard
          title="Comparativo entre anos"
          subtitle="Faturamento mês a mês, ano mais recente em destaque."
          isLoading={isLoading}
          isEmpty={!data || data.length === 0}
          emptyTitle="Sem histórico suficiente ainda"
          emptyDescription="Assim que os pedidos forem acumulando ao longo dos meses, o comparativo aparece aqui."
          minHeight={320}
        >
          <SeasonalityLinesChart rows={data ?? []} />
        </ChartCard>

        <SalesByHourHeatmapCard data={hourly.data} isLoading={hourly.isLoading} />
      </Box>

      <SeasonalityMatrixCard rows={matrixRows} isLoading={isLoading} />
    </Box>
  )
}
