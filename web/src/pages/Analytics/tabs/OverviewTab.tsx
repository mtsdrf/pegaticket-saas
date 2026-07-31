import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import GroupRemoveOutlinedIcon from '@mui/icons-material/GroupRemoveOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import PercentOutlinedIcon from '@mui/icons-material/PercentOutlined'
import PieChartOutlineOutlinedIcon from '@mui/icons-material/PieChartOutlineOutlined'
import { Box } from '@mui/material'
import { useEffect } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { MetricCard } from '../../../components/dashboard/MetricCard'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { formatCurrency, formatPercentage } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

/** Cobertura de custo abaixo disto: a margem é uma aproximação frágil, sinalizar. */
const LOW_COVERAGE_THRESHOLD = 50
/** Amostra de OTIF abaixo disto: percentual estatisticamente pouco confiável. */
const SMALL_OTIF_SAMPLE = 5
/** Concentração de receita acima disto: carteira dependente de poucos clientes. */
const HIGH_CONCENTRATION_THRESHOLD = 50

export function OverviewTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const periodKey = `${from}|${to}`

  const margin = useAnalyticsData(() => analyticsService.getMarginSummary({ from, to }), periodKey)
  const concentration = useAnalyticsData(() => analyticsService.getRevenueConcentration({ from, to }), periodKey)
  const otif = useAnalyticsData(() => analyticsService.getDeliveryOtif({ from, to }), periodKey)
  const coupon = useAnalyticsData(() => analyticsService.getCouponRoi({ from, to }), periodKey)
  const churn = useAnalyticsData(() => analyticsService.getChurnClients(), 'churn-clients')

  const sources = [margin, concentration, otif, coupon, churn]

  const planLocked = sources.some((source) => source.planLocked)
  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  const error = sources.map((source) => source.error).find(Boolean) ?? null
  if (error) {
    return <AnalyticsErrorAlert message={error} onRetry={() => sources.forEach((source) => source.reload())} />
  }

  const marginData = margin.data
  const concentrationData = concentration.data
  const otifData = otif.data
  const couponData = coupon.data
  const churnData = churn.data

  const lowCoverage = marginData !== null && marginData.coverage_percentage < LOW_COVERAGE_THRESHOLD
  const smallOtifSample = otifData !== null && otifData.eligible_orders < SMALL_OTIF_SAMPLE
  const highConcentration = concentrationData !== null && concentrationData.concentration_percentage >= HIGH_CONCENTRATION_THRESHOLD

  return (
    <Box
      sx={{
        display: 'grid',
        // Contagem fixa (5 indicadores): colunas explícitas por breakpoint
        // que dividem exatamente a contagem (ver design-system.md), nunca
        // deixando card órfão esticado.
        gridTemplateColumns: { xs: 'repeat(1,1fr)', sm: 'repeat(2,1fr)', md: 'repeat(3,1fr)' },
        gap: 2,
      }}
    >
      <MetricCard
        icon={PercentOutlinedIcon}
        label="Margem bruta (aprox.)"
        value={marginData ? formatPercentage(marginData.gross_margin_percentage) : null}
        caption={
          marginData
            ? `Baseado em ${formatPercentage(marginData.coverage_percentage)} da receita com custo cadastrado${lowCoverage ? ' — cobertura baixa, use com cautela' : ''}`
            : null
        }
        tone={lowCoverage ? 'warning' : 'accent'}
        isLoading={margin.isLoading}
        index={0}
      />
      <MetricCard
        icon={PieChartOutlineOutlinedIcon}
        label="Concentração de receita"
        value={concentrationData ? formatPercentage(concentrationData.concentration_percentage) : null}
        caption={
          concentrationData
            ? 'Top 10 clientes no período — quanto maior, mais risco de carteira concentrada'
            : null
        }
        tone={highConcentration ? 'warning' : 'info'}
        isLoading={concentration.isLoading}
        index={1}
      />
      <MetricCard
        icon={LocalShippingOutlinedIcon}
        label="OTIF de entrega"
        value={otifData ? formatPercentage(otifData.otif_percentage) : null}
        caption={
          otifData
            ? smallOtifSample
              ? `Apenas ${otifData.eligible_orders} pedido${otifData.eligible_orders === 1 ? '' : 's'} elegível — amostra pequena, percentual pouco confiável`
              : `${otifData.eligible_orders} pedidos elegíveis no período`
            : null
        }
        tone={smallOtifSample ? 'warning' : 'accent'}
        isLoading={otif.isLoading}
        index={2}
      />
      <MetricCard
        icon={ConfirmationNumberOutlinedIcon}
        label="Desconto em cupons"
        value={couponData ? formatCurrency(couponData.total_discount_amount) : null}
        caption={
          couponData
            ? `Ticket com cupom ${formatCurrency(couponData.orders_with_coupon.average_ticket)} vs sem cupom ${formatCurrency(couponData.orders_without_coupon.average_ticket)}`
            : null
        }
        tone="info"
        isLoading={coupon.isLoading}
        index={3}
      />
      <MetricCard
        icon={GroupRemoveOutlinedIcon}
        label="Clientes inativos"
        value={churnData ? String(churnData.churned_clients_count) : null}
        caption={churnData ? `${formatCurrency(churnData.estimated_monthly_revenue_at_risk)}/mês em risco` : null}
        tone={churnData && churnData.churned_clients_count > 0 ? 'warning' : 'primary'}
        isLoading={churn.isLoading}
        index={4}
      />
    </Box>
  )
}
