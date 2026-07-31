import { Box, Typography } from '@mui/material'
import { useEffect, useState } from 'react'
import { BillingPeriodTabs } from '../components/BillingPeriodTabs'
import { PlanCard } from '../components/PlanCard'
import { PlanModuleComparison } from '../components/PlanModuleComparison'
import { PlanPriceMatrix } from '../components/PlanPriceMatrix'
import { RevealSection } from '../components/RevealSection'
import { PLANS } from '../data/plans'
import { Faq } from '../sections/Faq'
import { FinalCta } from '../sections/FinalCta'
import { Footer } from '../sections/Footer'
import { Header } from '../sections/Header'
import { trackEvent } from '../utils/analytics'
import type { BillingPeriod } from '../utils/pricing'

export function PrecosPage() {
  const [period, setPeriod] = useState<BillingPeriod>('monthly')

  useEffect(() => {
    trackEvent({ name: 'pricing_view' })
  }, [])

  function handlePeriodChange(next: BillingPeriod) {
    setPeriod(next)
    trackEvent({ name: 'billing_period_change', period: next })
  }

  return (
    <Box component="main" id="conteudo">
      <Header />

      <Box component="section" sx={{ py: { xs: 6, md: 9 } }}>
        <Box sx={{ maxWidth: 1200, mx: 'auto', px: { xs: 2.5, sm: 4 } }}>
          <RevealSection>
            <Typography
              component="h1"
              sx={{ fontSize: { xs: 28, md: 38 }, fontWeight: 700, color: 'var(--pt-text)', mb: 1.5 }}
            >
              Preco transparente para a plataforma completa
            </Typography>
            <Typography sx={{ fontSize: 16, color: 'var(--pt-muted)', maxWidth: 640, mb: 4 }}>
              Simule o valor mensal, trimestral e anual do plano unico e veja tudo o que ja fica liberado sem surpresas.
              Todo plano inclui 14 dias de teste gratuito antes da primeira cobrança.
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
              mb: { xs: 6, md: 8 },
            }}
          >
            {PLANS.map((plan) => (
              <RevealSection key={plan.slug}>
                <PlanCard plan={plan} period={period} />
              </RevealSection>
            ))}
          </Box>

          <RevealSection>
            <Box sx={{ mb: { xs: 6, md: 8 } }}>
              <Typography component="h2" sx={{ fontSize: { xs: 22, md: 26 }, fontWeight: 700, color: 'var(--pt-text)', mb: 1 }}>
                Compare o custo em cada periodo
              </Typography>
              <Typography sx={{ fontSize: 14.5, color: 'var(--pt-muted)', mb: 2.5, maxWidth: 640 }}>
                Valor total cobrado e equivalente mensal do plano unico no mensal, trimestral e anual, lado a lado.
              </Typography>
              <PlanPriceMatrix />
            </Box>
          </RevealSection>

          <RevealSection>
            <Box>
              <Typography component="h2" sx={{ fontSize: { xs: 22, md: 26 }, fontWeight: 700, color: 'var(--pt-text)', mb: 1 }}>
                O que ja esta liberado
              </Typography>
              <Typography sx={{ fontSize: 14.5, color: 'var(--pt-muted)', mb: 2.5, maxWidth: 640 }}>
                Visao resumida dos modulos incluidos no pacote atual da plataforma.
              </Typography>
              <PlanModuleComparison />
            </Box>
          </RevealSection>

          <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', textAlign: 'center', mt: 4 }}>
            Preços em reais, cobrados por empresa. Todo plano inclui 14 dias de teste gratuito antes da primeira
            cobrança.
          </Typography>
        </Box>
      </Box>

      <Faq />
      <FinalCta />
      <Footer />
    </Box>
  )
}
