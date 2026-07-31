import type { PlanPricingBillingPeriod } from '../types/subscription'

export type BillingPeriod = PlanPricingBillingPeriod['billing_period']

/**
 * Só rótulo de exibição. Meses/desconto/valores são sempre os reais, vindos
 * de `GET /subscription/plan-pricing` (`PlanPricingBillingPeriod`) — não
 * espelhar preço/desconto aqui de novo. Já houve divergência real entre
 * este arquivo e `plan_prices` no banco antes desta mudança (preço
 * hardcoded no frontend, corrigido substituindo por dado real do backend).
 */
export const BILLING_PERIOD_LABELS: Record<BillingPeriod, string> = {
  monthly: 'Mensal',
  quarterly: 'Trimestral',
  yearly: 'Anual',
}
