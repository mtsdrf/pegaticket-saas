import { Box } from '@mui/material'
import { useEffect } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { RankingListCard } from '../../../components/dashboard/RankingListCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { FORM_GRID_2_SX } from '../../../styles/layoutStandards'
import type { LocationSales } from '../../../types/analytics'
import { formatCurrency } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

function toRankingItems(items: LocationSales[] | undefined) {
  if (!items) return null
  return items.map((location) => ({
    title: location.name || '(sem registro)',
    value: formatCurrency(location.total_amount),
    meta: `${location.count} venda${location.count === 1 ? '' : 's'}`,
  }))
}

export function LocationsTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getSalesByLocation({ from, to }),
    `${from}|${to}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  return (
    <Box sx={{ ...FORM_GRID_2_SX, gap: 2.5, alignItems: 'start' }}>
      <RankingListCard
        title="Vendas por cidade"
        subtitle="Onde a operação mais fatura no período."
        isLoading={isLoading}
        items={toRankingItems(data?.cities)}
        emptyTitle="Nenhuma venda com cidade no período"
        emptyDescription="Vendas com endereço preenchido aparecem agrupadas por cidade aqui."
      />

      <RankingListCard
        title="Vendas por bairro"
        subtitle="Bairros com maior faturamento no período."
        isLoading={isLoading}
        items={toRankingItems(data?.neighborhoods)}
        emptyTitle="Nenhuma venda com bairro no período"
        emptyDescription="Vendas com endereço preenchido aparecem agrupadas por bairro aqui."
      />
    </Box>
  )
}
