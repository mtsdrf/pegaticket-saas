import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded'
import RadioButtonUncheckedRoundedIcon from '@mui/icons-material/RadioButtonUncheckedRounded'
import { Box, ButtonBase, Chip, Stack, Typography } from '@mui/material'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { formatCurrency } from '../../utils/format'
import { BILLING_PERIOD_LABELS, type BillingPeriod } from '../../utils/subscriptionPricing'
import type { PlanPricingBillingPeriod } from '../../types/subscription'

const PERIOD_ORDER: BillingPeriod[] = ['monthly', 'quarterly', 'yearly']

interface BillingPeriodOptionCardsProps {
  /** Preço real por período, de `GET /subscription/plan-pricing` — nunca calculado localmente. */
  periods: PlanPricingBillingPeriod[]
  value: BillingPeriod
  onChange: (period: BillingPeriod) => void
}

/**
 * Seleção visual de período de cobrança (Mensal/Trimestral/Anual). Preço,
 * desconto e valor total vêm sempre do backend (`plan_prices`, via
 * `PlanPricingResource`) — o componente só formata e exibe, não recalcula.
 * Cards funcionam como radio group acessível (role="radiogroup"/"radio",
 * navegável por teclado e toque).
 */
export function BillingPeriodOptionCards({ periods, value, onChange }: BillingPeriodOptionCardsProps) {
  const ordered = PERIOD_ORDER
    .map((period) => periods.find((item) => item.billing_period === period))
    .filter((item): item is PlanPricingBillingPeriod => item !== undefined)

  const bestDiscountPeriod = ordered.reduce<PlanPricingBillingPeriod | null>(
    (best, item) => (best === null || item.discount_percent > best.discount_percent ? item : best),
    null,
  )

  return (
    <Box
      role="radiogroup"
      aria-label="Período de cobrança"
      sx={{
        display: 'grid',
        gridTemplateColumns: { xs: '1fr', sm: 'repeat(3, minmax(0, 1fr))' },
        gap: 1.5,
      }}
    >
      {ordered.map((item) => {
        const { billing_period: period, months, discount_percent: discountPercent } = item
        const selected = value === period
        const isBestDiscount = item === bestDiscountPeriod && discountPercent > 0

        return (
          <ButtonBase
            key={period}
            role="radio"
            aria-checked={selected}
            onClick={() => onChange(period)}
            sx={{
              display: 'block',
              textAlign: 'left',
              width: '100%',
              minHeight: 44,
              p: 2,
              border: selected ? '2px solid var(--mk-primary)' : '1px solid var(--mk-border)',
              ...ELEVATED_SURFACE_SX,
              background: selected
                ? 'color-mix(in srgb, var(--mk-primary) 6%, var(--mk-surface))'
                : ELEVATED_SURFACE_SX.background,
              boxShadow: selected ? 'var(--mk-shadow-md)' : 'var(--mk-shadow-sm)',
              position: 'relative',
              transition: 'border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease',
              '&:focus-visible': { outline: 'var(--mk-focus-ring)', outlineOffset: 2 },
            }}
          >
            {isBestDiscount && (
              <Chip
                label="Melhor economia"
                size="small"
                sx={{
                  position: 'absolute',
                  top: -12,
                  right: 12,
                  height: 22,
                  fontSize: 11,
                  fontWeight: 700,
                  backgroundColor: 'var(--mk-accent)',
                  color: '#FFFFFF',
                }}
              />
            )}

            <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1, mb: 1 }}>
              <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center' }}>
                {selected ? (
                  <CheckCircleRoundedIcon sx={{ fontSize: 20, color: 'var(--mk-primary)' }} />
                ) : (
                  <RadioButtonUncheckedRoundedIcon sx={{ fontSize: 20, color: 'var(--mk-muted)' }} />
                )}
                <Typography sx={{ fontSize: 14.5, fontWeight: 700, color: 'var(--mk-text)' }}>
                  {BILLING_PERIOD_LABELS[period]}
                </Typography>
              </Stack>
              {discountPercent > 0 && (
                <Chip
                  label={`-${discountPercent}%`}
                  size="small"
                  sx={{
                    height: 22,
                    fontSize: 11.5,
                    fontWeight: 700,
                    backgroundColor: 'color-mix(in srgb, var(--mk-success) 16%, transparent)',
                    color: 'var(--mk-success)',
                  }}
                />
              )}
            </Stack>

            <Stack direction="row" spacing={0.5} sx={{ alignItems: 'baseline', mb: 0.25 }}>
              <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: 22, fontWeight: 800, color: 'var(--mk-text)' }}>
                {formatCurrency(item.monthly_equivalent)}
              </Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>/mês</Typography>
            </Stack>

            {period === 'monthly' ? (
              <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)' }}>Cobrado mensalmente</Typography>
            ) : (
              <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)' }}>
                {formatCurrency(item.total_amount)} a cada {months} meses
                {discountPercent > 0 &&
                  ` · economia de ${formatCurrency(Number(item.list_amount) - Number(item.total_amount))}`}
              </Typography>
            )}
          </ButtonBase>
        )
      })}
    </Box>
  )
}
