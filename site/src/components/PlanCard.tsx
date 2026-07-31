import CheckRoundedIcon from '@mui/icons-material/CheckRounded'
import { Box, Button, Chip, Stack, Typography } from '@mui/material'
import { APP_URL } from '../constants/app'
import type { PlanInfo } from '../data/plans'
import { trackEvent } from '../utils/analytics'
import { BILLING_PERIODS, calculatePlanPrice, formatBRL, type BillingPeriod } from '../utils/pricing'

interface PlanCardProps {
  plan: PlanInfo
  period: BillingPeriod
}

/**
 * Card de plano reutilizado pela seção de planos da home (`sections/Plans.tsx`)
 * e pela página dedicada de preços (`pages/PrecosPage.tsx`) — mesma fórmula de
 * preço (`utils/pricing.ts`), sem duplicar cálculo nem conteúdo.
 */
export function PlanCard({ plan, period }: PlanCardProps) {
  const price = calculatePlanPrice(plan.monthlyBase, period)
  const months = BILLING_PERIODS[period].months

  return (
    <Box
      sx={{
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
        borderRadius: 'var(--pt-radius-lg)',
        border: plan.highlighted ? '2px solid var(--pt-primary)' : '1px solid var(--pt-border)',
        backgroundColor: 'var(--pt-surface)',
        boxShadow: plan.highlighted ? 'var(--pt-shadow-lg)' : 'var(--pt-shadow-sm)',
        p: 3,
        position: 'relative',
      }}
    >
      {plan.highlighted ? (
        <Chip
          label="Mais escolhido"
          size="small"
          sx={{
            position: 'absolute',
            top: -14,
            left: 24,
            fontWeight: 700,
            backgroundColor: 'var(--pt-primary)',
            color: '#FFFFFF',
          }}
        />
      ) : null}

      <Typography sx={{ fontSize: 20, fontWeight: 700, color: 'var(--pt-text)' }}>{plan.name}</Typography>
      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2 }}>{plan.tagline}</Typography>

      <Stack direction="row" spacing={0.75} sx={{ mb: 0.5, alignItems: 'baseline' }}>
        <Typography sx={{ fontSize: 32, fontWeight: 800, color: 'var(--pt-text)' }}>
          {formatBRL(price.monthlyEquivalent)}
        </Typography>
        <Typography sx={{ fontSize: 14, color: 'var(--pt-muted)' }}>/mês</Typography>
      </Stack>

      {period === 'monthly' ? (
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mb: 2.5 }}>Cobrado mensalmente</Typography>
      ) : (
        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mb: 2.5 }}>
          {formatBRL(price.chargedTotal)} cobrados a cada {months} meses — economia de {formatBRL(price.savings)}{' '}
          frente ao mensal
        </Typography>
      )}

      <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 2, lineHeight: 1.5 }}>
        {plan.audience}
      </Typography>

      <Stack spacing={1} sx={{ mb: 3, flexGrow: 1 }}>
        {plan.featureHighlights.map((feature) => (
          <Stack key={feature} direction="row" spacing={1} sx={{ alignItems: 'flex-start' }}>
            <CheckRoundedIcon sx={{ fontSize: 18, color: 'var(--pt-success)', mt: 0.2 }} />
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-text)' }}>{feature}</Typography>
          </Stack>
        ))}
      </Stack>

      <Button
        fullWidth
        size="large"
        variant={plan.highlighted ? 'contained' : 'outlined'}
        color="primary"
        href={APP_URL}
        onClick={() => trackEvent({ name: 'plan_select', plan: plan.slug, period })}
      >
        Assinar {plan.name}
      </Button>
    </Box>
  )
}
