import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import LoginOutlinedIcon from '@mui/icons-material/LoginOutlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import ReplayOutlinedIcon from '@mui/icons-material/ReplayOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
import { Box } from '@mui/material'
import { useEffect } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { MetricCard } from '../../../components/dashboard/MetricCard'
import { RankingListCard } from '../../../components/dashboard/RankingListCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { formatPercentage } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

export function AccessTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const insights = useAnalyticsData(() => analyticsService.getCheckinInsights({ from, to }), `${from}|${to}`)

  useEffect(() => {
    if (insights.planLocked) onPlanLocked()
  }, [insights.planLocked, onPlanLocked])

  if (insights.error) {
    return <AnalyticsErrorAlert message={insights.error} onRetry={insights.reload} />
  }

  const totals = insights.data?.totals ?? null
  const bySession = insights.data?.by_session ?? null
  const byTicketType = insights.data?.by_ticket_type ?? null

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', md: 'repeat(5,1fr)' },
          gap: 2,
        }}
      >
        <MetricCard
          icon={QrCodeScannerOutlinedIcon}
          label="Leituras"
          value={totals ? String(totals.total_reads) : null}
          caption="Todas as tentativas de acesso registradas no período."
          tone="primary"
          isLoading={insights.isLoading}
          index={0}
        />
        <MetricCard
          icon={LoginOutlinedIcon}
          label="Entradas liberadas"
          value={totals ? String(totals.granted_reads) : null}
          caption={totals ? `${totals.unique_granted_tickets} ingressos distintos efetivamente usados` : null}
          tone="accent"
          isLoading={insights.isLoading}
          index={1}
        />
        <MetricCard
          icon={WarningAmberOutlinedIcon}
          label="Ocorrências de atenção"
          value={totals ? String(totals.warning_reads) : null}
          caption="Casos como ingresso já utilizado ou reentrada ainda indisponível."
          tone="warning"
          isLoading={insights.isLoading}
          index={2}
        />
        <MetricCard
          icon={ReplayOutlinedIcon}
          label="Reentradas autorizadas"
          value={totals ? String(totals.reentries) : null}
          caption="Mede quantas exceções operacionais foram liberadas na portaria."
          tone="info"
          isLoading={insights.isLoading}
          index={3}
        />
        <MetricCard
          icon={ConfirmationNumberOutlinedIcon}
          label="Taxa de presença"
          value={totals ? formatPercentage(totals.attendance_rate) : null}
          caption="Ingressos distintos que entraram em relação ao total emitido."
          tone="primary"
          isLoading={insights.isLoading}
          index={4}
        />
      </Box>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', lg: 'minmax(0, 1fr) minmax(0, 1fr)' }, gap: 2.5 }}>
        <RankingListCard
          title="Acesso por sessão"
          subtitle="Sessões com mais leituras e melhor conversão operacional no período."
          isLoading={insights.isLoading}
          items={
            bySession?.map((session) => ({
              title: session.session_name,
              value: formatPercentage(session.attendance_rate),
              meta: `${session.event_name} · ${session.granted_reads} liberados · ${session.warning_reads} atenção · ${session.blocked_reads} bloqueados`,
            })) ?? null
          }
          emptyTitle="Nenhuma leitura por sessão no período"
          emptyDescription="Assim que a portaria registrar acessos, o ranking por sessão aparece aqui."
        />

        <RankingListCard
          title="Acesso por tipo de ingresso"
          subtitle="Mostra quais ingressos estão puxando a entrada e onde há mais exceções."
          isLoading={insights.isLoading}
          items={
            byTicketType?.map((ticketType) => ({
              title: ticketType.ticket_type_name,
              value: formatPercentage(ticketType.attendance_rate),
              meta: `${ticketType.event_name} · ${ticketType.granted_reads} liberados · ${ticketType.warning_reads} atenção · ${ticketType.blocked_reads} bloqueados`,
            })) ?? null
          }
          emptyTitle="Nenhuma leitura por tipo de ingresso"
          emptyDescription="O ranking por tipo aparece conforme os ingressos começam a ser utilizados."
        />
      </Box>
    </Box>
  )
}
