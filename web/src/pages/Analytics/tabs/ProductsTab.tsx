import { Box } from '@mui/material'
import { useEffect } from 'react'
import { AbcTableCard } from '../../../components/analytics/AbcTableCard'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { RankingListCard } from '../../../components/dashboard/RankingListCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { formatCurrency } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

const TOP_LIMIT = 10

export function ProductsTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const topProducts = useAnalyticsData(
    () => analyticsService.getTopProducts({ from, to, limit: TOP_LIMIT }),
    `${from}|${to}`,
  )
  const abc = useAnalyticsData(
    () => analyticsService.getAbcAnalysis({ from, to, dimension: 'ticket_types' }),
    `${from}|${to}`,
  )

  const sources = [topProducts, abc]

  const planLocked = sources.some((source) => source.planLocked)
  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  const error = sources.map((source) => source.error).find(Boolean) ?? null
  if (error) {
    return <AnalyticsErrorAlert message={error} onRetry={() => sources.forEach((source) => source.reload())} />
  }

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: 'minmax(0, 5fr) minmax(0, 7fr)' }, gap: 2.5, alignItems: 'start' }}>
        <RankingListCard
          title="Produtos mais vendidos"
          subtitle={`Top ${TOP_LIMIT} produtos por receita no período.`}
          isLoading={topProducts.isLoading}
          items={
            topProducts.data?.map((product) => ({
              title: product.name,
              value: formatCurrency(product.revenue),
              meta: `${product.quantity_sold} un. vendidas`,
            })) ?? null
          }
          emptyTitle="Nenhuma venda de produto no período"
          emptyDescription="Ajuste o período acima para ver o ranking de produtos."
        />

        <AbcTableCard
          title="Curva ABC de produtos"
          subtitle="Classe A concentra a maior parte da receita; C é a cauda longa."
          isLoading={abc.isLoading}
          items={abc.data}
          emptyTitle="Sem dados para a curva ABC"
          emptyDescription="A análise ABC aparece assim que houver vendas no período selecionado."
        />
      </Box>
    </Box>
  )
}
