/** Espelha a taxa de serviço PegaTicket vigente (tenant-scoped, `GET
 * /tenant-tools/ticket-pricing/rule`) — leve, qualquer usuário com
 * `ticket_types,read` pode consultar. */
export interface TicketFeeRule {
  percentage: number
  minimum_amount: number
  version: number
}

export type TicketFeeSimulationMode = 'price' | 'target_net'
export type TicketFeePayer = 'buyer' | 'producer'

export interface TicketFeeSimulationRequest {
  mode: TicketFeeSimulationMode
  /** Centavos. */
  amount: number
  quantity?: number
  fee_payer: TicketFeePayer
}

export interface TicketFeePaymentMethodEstimate {
  method: 'pix' | 'card'
  installments: number
  estimated_processing_percentage: number | null
  is_estimated: boolean
}

/** Resposta de `POST /tenant-tools/ticket-pricing/simulate` — todos os
 * valores monetários já vêm em REAIS (só `amount` do request é centavos). */
export interface TicketFeeSimulationResult {
  mode: TicketFeeSimulationMode
  fee_payer: TicketFeePayer
  quantity: number
  unit_price: number
  platform_fee_unit: number
  platform_fee_total: number
  buyer_pays_unit: number
  buyer_pays_total: number
  producer_receives_unit: number
  producer_receives_total: number
  effective_fee_percentage: number
  service_fee_rule_version: number
  payment_methods: TicketFeePaymentMethodEstimate[]
}

export interface TicketFeePreview {
  feeAmount: number
  isMinimumApplied: boolean
  effectivePercentage: number
}

/**
 * Espelha localmente a fórmula do backend (`fee = max(round(price *
 * percentage/100, 2), minimum_amount)` quando `price > 0`) — usada para dar
 * feedback instantâneo sem chamar a API a cada tecla. Não duplica lógica de
 * negócio de verdade: é só a mesma fórmula documentada no contrato da API,
 * usada exclusivamente para preview local (o valor cobrado de fato sempre
 * vem do backend na simulação/venda real).
 */
export function computeTicketFeePreview(
  priceReais: number,
  rule: Pick<TicketFeeRule, 'percentage' | 'minimum_amount'>,
): TicketFeePreview {
  if (!Number.isFinite(priceReais) || priceReais <= 0) {
    return { feeAmount: 0, isMinimumApplied: false, effectivePercentage: 0 }
  }

  const percentageFee = Math.round(priceReais * (rule.percentage / 100) * 100) / 100
  const isMinimumApplied = percentageFee < rule.minimum_amount
  const feeAmount = isMinimumApplied ? rule.minimum_amount : percentageFee
  const effectivePercentage = (feeAmount / priceReais) * 100

  return { feeAmount, isMinimumApplied, effectivePercentage }
}
