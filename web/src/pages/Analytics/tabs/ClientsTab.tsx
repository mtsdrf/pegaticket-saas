import { Box } from '@mui/material'
import { useEffect } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { RfmChip } from '../../../components/analytics/RfmChip'
import { RankingListCard } from '../../../components/dashboard/RankingListCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { formatCurrency } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

const TOP_LIMIT = 10

export function ClientsTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const topClients = useAnalyticsData(
    () => analyticsService.getTopClients({ from, to, limit: TOP_LIMIT }),
    `${from}|${to}`,
  )

  const planLocked = topClients.planLocked
  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  const error = topClients.error
  if (error) {
    return <AnalyticsErrorAlert message={error} onRetry={() => topClients.reload()} />
  }

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
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
    </Box>
  )
}
