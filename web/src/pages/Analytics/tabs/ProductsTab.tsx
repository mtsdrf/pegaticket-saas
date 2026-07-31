import { Box, Tooltip } from '@mui/material'
import { useEffect } from 'react'
import { AbcTableCard } from '../../../components/analytics/AbcTableCard'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { RankingListCard } from '../../../components/dashboard/RankingListCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { FORM_GRID_2_SX } from '../../../styles/layoutStandards'
import { formatCurrency, formatQuantity } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

const TOP_LIMIT = 10

export function ProductsTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const topProducts = useAnalyticsData(
    () => analyticsService.getTopProducts({ from, to, limit: TOP_LIMIT }),
    `${from}|${to}`,
  )
  const abc = useAnalyticsData(
    () => analyticsService.getAbcAnalysis({ from, to, dimension: 'products' }),
    `${from}|${to}`,
  )
  // Foto operacional atual, não filtrada por período.
  const stalled = useAnalyticsData(() => analyticsService.getStalledProducts(), 'stalled-products')

  const sources = [topProducts, abc, stalled]

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

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: 'repeat(2, minmax(0, 1fr))' }, gap: 2.5, alignItems: 'start' }}>
        <RankingListCard
          title="Itens sem giro recente"
          subtitle={
            stalled.data
              ? `Sem venda recente e com disponibilidade parada — ${formatCurrency(stalled.data.total_value_tied_up)} imobilizados. * valor estimado pelo preço de venda.`
              : 'Itens com baixa rotação recente e capital imobilizado.'
          }
          isLoading={stalled.isLoading}
          items={
            stalled.data?.items.map((item) => ({
              title: item.product_name,
              value: formatCurrency(item.value_tied_up),
              meta: `${formatQuantity(item.quantity_on_hand)} em disponibilidade`,
              badge: item.cost_is_estimated ? (
                <Tooltip title="Valor estimado pelo preço de venda, sem custo de compra cadastrado.">
                  <Box component="span" sx={{ color: 'var(--pt-warning)', fontWeight: 700, cursor: 'help' }} aria-label="valor estimado">
                    *
                  </Box>
                </Tooltip>
              ) : undefined,
            })) ?? null
          }
          emptyTitle="Nenhum item parado"
          emptyDescription="Os itens com disponibilidade tiveram movimentação recente."
        />
      </Box>
    </Box>
  )
}
