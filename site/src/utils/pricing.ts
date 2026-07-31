export type BillingPeriod = 'monthly' | 'quarterly' | 'yearly'

/**
 * Mesma regra de `api/database/seeders/PlanPricesSeeder.php` (fonte única
 * de preço/desconto, confirmada com o usuário): preço de tabela do período
 * é `base × meses`, e o desconto é aplicado sobre esse total.
 *   mensal:     base × 1,  desconto 0%
 *   trimestral: base × 3,  desconto 10%
 *   anual:      base × 12, desconto 20%
 */
export const BILLING_PERIODS: Record<BillingPeriod, { months: number; discountPercent: number; label: string }> = {
  monthly: { months: 1, discountPercent: 0, label: 'Mensal' },
  quarterly: { months: 3, discountPercent: 10, label: 'Trimestral' },
  yearly: { months: 12, discountPercent: 20, label: 'Anual' },
}

export interface PlanPriceBreakdown {
  listTotal: number
  discountPercent: number
  chargedTotal: number
  monthlyEquivalent: number
  savings: number
}

export function calculatePlanPrice(monthlyBase: number, period: BillingPeriod): PlanPriceBreakdown {
  const { months, discountPercent } = BILLING_PERIODS[period]
  const listTotal = round2(monthlyBase * months)
  const chargedTotal = round2(listTotal * (1 - discountPercent / 100))
  const monthlyEquivalent = round2(chargedTotal / months)
  const savings = round2(listTotal - chargedTotal)

  return { listTotal, discountPercent, chargedTotal, monthlyEquivalent, savings }
}

function round2(value: number): number {
  return Math.round(value * 100) / 100
}

export function formatBRL(value: number): string {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
