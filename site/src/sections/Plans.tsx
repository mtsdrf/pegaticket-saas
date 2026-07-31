import { Box, Button, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { BillingPeriodTabs } from '../components/BillingPeriodTabs'
import { PlanCard } from '../components/PlanCard'
import { RevealSection } from '../components/RevealSection'
import { PLANS } from '../data/plans'
import { trackEvent } from '../utils/analytics'
import type { BillingPeriod } from '../utils/pricing'

export function Plans() {
  const [period, setPeriod] = useState<BillingPeriod>('monthly')

  useEffect(() => {
    trackEvent({ name: 'pricing_view' })
  }, [])

  function handlePeriodChange(next: BillingPeriod) {
    setPeriod(next)
    trackEvent({ name: 'billing_period_change', period: next })
  }

  return (
    <Box component="section" id="planos" sx={{ py: { xs: 6, md: 10 } }}>
      <Box sx={{ maxWidth: 1200, mx: 'auto', px: { xs: 2.5, sm: 4 } }}>
        <RevealSection>
          <Typography component="h2" sx={{ fontSize: { xs: 26, md: 32 }, fontWeight: 700, color: 'var(--pt-text)', mb: 1 }}>
            Um plano para operar sem bloqueios
          </Typography>
          <Typography sx={{ fontSize: 16, color: 'var(--pt-muted)', maxWidth: 620, mb: 3.5 }}>
            Toda a plataforma atual fica liberada no mesmo pacote, com cobrança mensal, trimestral ou anual.
          </Typography>
        </RevealSection>

        <Box sx={{ display: 'flex', justifyContent: 'center', mb: 4, maxWidth: '100%' }}>
          <BillingPeriodTabs period={period} onChange={handlePeriodChange} />
        </Box>

        <Box
          sx={{
            display: 'grid',
            gridTemplateColumns: { xs: '1fr', md: 'repeat(3, minmax(0,1fr))' },
            gap: 2.5,
            alignItems: 'stretch',
          }}
        >
          {PLANS.map((plan) => (
            <RevealSection key={plan.slug}>
              <PlanCard plan={plan} period={period} />
            </RevealSection>
          ))}
        </Box>

        <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', textAlign: 'center', mt: 3 }}>
          Preços em reais, cobrados por empresa. Todo plano inclui 14 dias de teste gratuito antes da primeira cobrança.
        </Typography>

        <Box sx={{ textAlign: 'center', mt: 2.5 }}>
          <Button component="a" href="/precos" variant="text" color="primary">
            Ver comparativo completo e simular o plano ideal →
          </Button>
        </Box>
      </Box>
    </Box>
  )
}
