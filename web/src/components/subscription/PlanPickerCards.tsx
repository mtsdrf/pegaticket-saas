import CheckRoundedIcon from '@mui/icons-material/CheckRounded'
import { Box, ButtonBase, Stack, Typography } from '@mui/material'
import { findPlanSalesInfo } from '../../data/plans'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { PlanPricing } from '../../types/subscription'
import { formatCurrency } from '../../utils/format'

interface PlanPickerCardsProps {
  /** Preço real por plano, sempre vindo do backend (`available-plans`/`plan-pricing`). */
  plans: PlanPricing[]
  onSelect: (plan: PlanPricing) => void
}

/**
 * Lista de planos para escolha (cartão por plano com preço "a partir de" +
 * principais recursos). Compartilhada entre a troca de plano de uma
 * assinatura já ativa (`ChangePlanDialog`) e a escolha do plano na primeira
 * contratação (`SubscriptionPage`) — mesmo visual nos dois fluxos, extraído
 * para não duplicar a lista de planos.
 */
export function PlanPickerCards({ plans, onSelect }: PlanPickerCardsProps) {
  return (
    <Stack spacing={1.5}>
      {plans.map((plan) => {
        const salesInfo = findPlanSalesInfo(plan.plan.slug, plan.plan.name)
        const cheapest = plan.billing_periods.reduce<PlanPricing['billing_periods'][number] | null>(
          (best, item) =>
            best === null || Number(item.monthly_equivalent) < Number(best.monthly_equivalent) ? item : best,
          null,
        )

        return (
          <ButtonBase
            key={plan.plan.uuid}
            onClick={() => onSelect(plan)}
            sx={{
              display: 'block',
              textAlign: 'left',
              width: '100%',
              p: 2,
              ...ELEVATED_SURFACE_SX,
              '&:focus-visible': { outline: 'var(--mk-focus-ring)', outlineOffset: 2 },
            }}
          >
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'flex-start', gap: 1 }}>
              <Box>
                <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 15.5, color: 'var(--mk-text)' }}>
                  {plan.plan.name}
                </Typography>
                {plan.plan.description && (
                  <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mt: 0.25 }}>
                    {plan.plan.description}
                  </Typography>
                )}
              </Box>
              {cheapest && (
                <Box sx={{ textAlign: 'right', flexShrink: 0 }}>
                  <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 800, fontSize: 17, color: 'var(--mk-text)' }}>
                    {formatCurrency(cheapest.monthly_equivalent)}
                  </Typography>
                  <Typography sx={{ fontSize: 11.5, color: 'var(--mk-muted)' }}>a partir de /mês</Typography>
                </Box>
              )}
            </Stack>

            {salesInfo && salesInfo.featureHighlights.length > 0 && (
              <Stack spacing={0.5} sx={{ mt: 1.25 }}>
                {salesInfo.featureHighlights.slice(0, 4).map((feature) => (
                  <Stack key={feature} direction="row" spacing={0.75} sx={{ alignItems: 'flex-start' }}>
                    <CheckRoundedIcon sx={{ fontSize: 15, color: 'var(--mk-success)', mt: 0.25 }} />
                    <Typography sx={{ fontSize: 13, color: 'var(--mk-text)' }}>{feature}</Typography>
                  </Stack>
                ))}
              </Stack>
            )}
          </ButtonBase>
        )
      })}
    </Stack>
  )
}
