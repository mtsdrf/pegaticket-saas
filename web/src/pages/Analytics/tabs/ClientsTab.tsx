import { Box } from '@mui/material'
import { useEffect } from 'react'
import { AbcTableCard } from '../../../components/analytics/AbcTableCard'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { RfmChip } from '../../../components/analytics/RfmChip'
import { RankingListCard } from '../../../components/dashboard/RankingListCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { FORM_GRID_2_SX } from '../../../styles/layoutStandards'
import { formatCurrency } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

const TOP_LIMIT = 10

export function ClientsTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const topClients = useAnalyticsData(
    () => analyticsService.getTopClients({ from, to, limit: TOP_LIMIT }),
    `${from}|${to}`,
  )
  const paymentDelays = useAnalyticsData(
    () => analyticsService.getPaymentDelays({ from, to, limit: TOP_LIMIT }),
    `${from}|${to}`,
  )
  const abc = useAnalyticsData(
    () => analyticsService.getAbcAnalysis({ from, to, dimension: 'clients' }),
    `${from}|${to}`,
  )

  const planLocked = topClients.planLocked || paymentDelays.planLocked || abc.planLocked
  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  const error = topClients.error ?? paymentDelays.error ?? abc.error
  if (error) {
    return (
      <AnalyticsErrorAlert
        message={error}
        onRetry={() => {
          topClients.reload()
          paymentDelays.reload()
          abc.reload()
        }}
      />
    )
  }

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
      <Box sx={{ ...FORM_GRID_2_SX, gap: 2.5, alignItems: 'start' }}>
        <RankingListCard
          title="Melhores clientes"
          subtitle={`Top ${TOP_LIMIT} clientes por valor comprado, com o perfil RFM de cada um.`}
          isLoading={topClients.isLoading}
          items={
            topClients.data?.map((client) => ({
              title: client.name,
              badge: client.rfm ? <RfmChip segment={client.rfm} /> : undefined,
              value: formatCurrency(client.total_amount),
              meta: `${client.order_count} venda${client.order_count === 1 ? '' : 's'}`,
            })) ?? null
          }
          emptyTitle="Nenhum cliente com compra no período"
          emptyDescription="Ajuste o período acima para ver o ranking de clientes."
        />

        <RankingListCard
          title="Atrasos de pagamento"
          subtitle="Clientes com maior média de dias para pagar."
          isLoading={paymentDelays.isLoading}
          items={
            paymentDelays.data?.map((client) => ({
              title: client.name,
              value: `${client.avg_days_to_pay} dia${client.avg_days_to_pay === 1 ? '' : 's'}`,
              meta: `${client.paid_orders_count} venda${client.paid_orders_count === 1 ? '' : 's'} paga${client.paid_orders_count === 1 ? '' : 's'}`,
            })) ?? null
          }
          emptyTitle="Nenhum atraso de pagamento no período"
          emptyDescription="Quando houver pagamentos com atraso, a média por cliente aparece aqui."
        />
      </Box>

      <AbcTableCard
        title="Curva ABC de clientes"
        subtitle="Classe A concentra a maior parte da receita; C é a cauda longa."
        isLoading={abc.isLoading}
        items={abc.data}
        emptyTitle="Sem dados para a curva ABC"
        emptyDescription="A análise ABC aparece assim que houver vendas no período selecionado."
      />
    </Box>
  )
}
