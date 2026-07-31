import { Box, Table, TableBody, TableCell, TableHead, TableRow, Typography } from '@mui/material'
import { PLANS } from '../data/plans'
import { BILLING_PERIODS, calculatePlanPrice, formatBRL, type BillingPeriod } from '../utils/pricing'

const PERIODS: BillingPeriod[] = ['monthly', 'quarterly', 'yearly']

/**
 * Simulador comparativo: mostra os 3 períodos de cobrança lado a lado para
 * os 3 planos, sem exigir troca do toggle acima — mesma fórmula única de
 * `utils/pricing.ts` (nenhum valor recalculado ou hardcoded aqui).
 */
export function PlanPriceMatrix() {
  return (
    <Box
      sx={{
        overflowX: 'auto',
        borderRadius: 'var(--mk-radius-lg)',
        border: '1px solid var(--mk-border)',
        backgroundColor: 'var(--mk-surface)',
      }}
    >
      <Table sx={{ minWidth: 620 }}>
        <TableHead>
          <TableRow>
            <TableCell sx={{ fontWeight: 700, color: 'var(--mk-text)' }}>Plano</TableCell>
            {PERIODS.map((periodKey) => (
              <TableCell key={periodKey} align="right" sx={{ fontWeight: 700, color: 'var(--mk-text)' }}>
                {BILLING_PERIODS[periodKey].label}
              </TableCell>
            ))}
          </TableRow>
        </TableHead>
        <TableBody>
          {PLANS.map((plan) => (
            <TableRow key={plan.slug}>
              <TableCell sx={{ fontWeight: 600, color: 'var(--mk-text)' }}>{plan.name}</TableCell>
              {PERIODS.map((periodKey) => {
                const price = calculatePlanPrice(plan.monthlyBase, periodKey)
                return (
                  <TableCell key={periodKey} align="right">
                    <Typography sx={{ fontSize: 14, fontWeight: 600, color: 'var(--mk-text)' }}>
                      {formatBRL(price.chargedTotal)}
                    </Typography>
                    <Typography sx={{ fontSize: 12, color: 'var(--mk-muted)' }}>
                      {formatBRL(price.monthlyEquivalent)}/mês
                      {periodKey !== 'monthly' ? ` · economia de ${formatBRL(price.savings)}` : ''}
                    </Typography>
                  </TableCell>
                )
              })}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </Box>
  )
}
