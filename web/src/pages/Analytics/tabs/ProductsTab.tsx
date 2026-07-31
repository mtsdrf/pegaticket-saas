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
  // Estoque não é filtrado por período — é uma foto do saldo atual.
  const stalled = useAnalyticsData(() => analyticsService.getStalledProducts(), 'stalled-products')
  const ruptures = useAnalyticsData(() => analyticsService.getStockRuptures(), 'stock-ruptures')

  const sources = [topProducts, abc, stalled, ruptures]

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
          title="Produtos parados"
          subtitle={
            stalled.data
              ? `Sem venda recente e com estoque — ${formatCurrency(stalled.data.total_value_tied_up)} imobilizados. * valor estimado pelo preço de venda.`
              : 'Produtos com estoque parado e o capital imobilizado neles.'
          }
          isLoading={stalled.isLoading}
          items={
            stalled.data?.items.map((item) => ({
              title: item.product_name,
              value: formatCurrency(item.value_tied_up),
              meta: `${formatQuantity(item.quantity_on_hand)} em estoque`,
              badge: item.cost_is_estimated ? (
                <Tooltip title="Valor estimado pelo preço de venda, sem custo de compra cadastrado.">
                  <Box component="span" sx={{ color: 'var(--mk-warning)', fontWeight: 700, cursor: 'help' }} aria-label="valor estimado">
                    *
                  </Box>
                </Tooltip>
              ) : undefined,
            })) ?? null
          }
          emptyTitle="Nenhum produto parado"
          emptyDescription="Todos os produtos com estoque tiveram venda recente."
        />

        <RankingListCard
          title="Ruptura de estoque"
          subtitle="Produtos que vendem mas estão com saldo zerado — risco de perder venda."
          isLoading={ruptures.isLoading}
          items={
            ruptures.data?.items.map((item) => ({
              title: item.product_name,
              value: `${formatQuantity(item.units_sold_last_90_days)} un.`,
              meta: 'Vendidas nos últimos 90 dias • estoque zerado',
            })) ?? null
          }
          emptyTitle="Nenhuma ruptura de estoque"
          emptyDescription="Os produtos que vendem têm saldo disponível em estoque."
        />
      </Box>
    </Box>
  )
}
