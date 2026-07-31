import { Box, Table, TableBody, TableCell, TableHead, TableRow, Typography } from '@mui/material'
import { PLANS } from '../data/plans'
import { BILLING_PERIODS, calculatePlanPrice, formatBRL, type BillingPeriod } from '../utils/pricing'

const PERIODS: BillingPeriod[] = ['monthly', 'quarterly', 'yearly']

/**
 * Simulador do plano unico: mostra os 3 periodos de cobranca lado a lado,
 * sem exigir troca do toggle acima — mesma formula unica de
 * `utils/pricing.ts` (nenhum valor recalculado ou hardcoded aqui).
 */
export function PlanPriceMatrix() {
  return (
    <Box
      sx={{
        overflowX: 'auto',
        borderRadius: 'var(--pt-radius-lg)',
        border: '1px solid var(--pt-border)',
        backgroundColor: 'var(--pt-surface)',
      }}
    >
      <Table sx={{ minWidth: 620 }}>
        <TableHead>
          <TableRow>
            <TableCell sx={{ fontWeight: 700, color: 'var(--pt-text)' }}>Plano</TableCell>
            {PERIODS.map((periodKey) => (
              <TableCell key={periodKey} align="right" sx={{ fontWeight: 700, color: 'var(--pt-text)' }}>
                {BILLING_PERIODS[periodKey].label}
              </TableCell>
            ))}
          </TableRow>
        </TableHead>
        <TableBody>
          {PLANS.map((plan) => (
            <TableRow key={plan.slug}>
              <TableCell sx={{ fontWeight: 600, color: 'var(--pt-text)' }}>{plan.name}</TableCell>
              {PERIODS.map((periodKey) => {
                const price = calculatePlanPrice(plan.monthlyBase, periodKey)
                return (
                  <TableCell key={periodKey} align="right">
                    <Typography sx={{ fontSize: 14, fontWeight: 600, color: 'var(--pt-text)' }}>
                      {formatBRL(price.chargedTotal)}
                    </Typography>
                    <Typography sx={{ fontSize: 12, color: 'var(--pt-muted)' }}>
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
