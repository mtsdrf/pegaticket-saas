/** Espelha App\Enums\Subscription\SubscriptionStatus (backend). */
export type SubscriptionStatus =
  | 'pending'
  | 'trialing'
  | 'active'
  | 'past_due'
  | 'suspended'
  | 'canceled'
  | 'cancel_scheduled'

export interface SubscriptionPlan {
  uuid: string
  name: string
  slug: string
}

export interface SubscriptionInvoice {
  uuid: string
  competence_period: string
  due_date: string | null
  amount_gross: number
  discount_amount: number
  amount_net: number
  status: string
  created_at: string | null
}

export interface SubscriptionInvoicePayment {
  uuid: string
  provider: string
  provider_charge_id: string | null
  method: string
  amount: string
  status: 'pending' | 'paid' | 'divergent' | 'failed' | 'requested' | string
  paid_at: string | null
  created_at: string
  metadata: {
    qr_code?: string | null
    qr_code_base64?: string | null
    ticket_url?: string | null
  } | null
}

export interface Subscription {
  uuid: string
  status: SubscriptionStatus
  billing_period: string
  /** Só presente quando o backend faz `whenLoaded('plan')` — o endpoint atual sempre carrega. */
  plan?: SubscriptionPlan
  trial_ends_at: string | null
  /** `status === 'trialing'`, espelha `SubscriptionResource::is_trial`. */
  is_trial: boolean
  /** Dias restantes de teste (arredondado para cima), `null` fora do trial ou já vencido. */
  trial_days_remaining: number | null
  current_period_start: string | null
  current_period_end: string | null
  next_charge_at: string | null
  cancel_at: string | null
  canceled_at: string | null
  /**
   * Fase B, item 1 — só presente quando a última cobrança do ciclo (via
   * Mercado Pago Preapproval) falhou: fim da tolerância de 7 dias antes da
   * suspensão total (`EnforceSubscriptionGracePeriodCommand`). `null` fora
   * dessa janela (inclusive em `active`/`trialing` normais).
   */
  grace_period_ends_at: string | null
  auto_renew: boolean
  accepted_terms_version: string | null
  accepted_at: string | null
  /** Base para a janela de arrependimento de 7 dias (backend usa `subscriptions.created_at`). */
  created_at: string | null
  invoices?: SubscriptionInvoice[]
}

export interface CreateSubscriptionPayload {
  billing_period: 'monthly' | 'quarterly' | 'yearly'
  accepted_terms: true
  /** Plano escolhido na primeira contratação; sem envio, contrata o plano padrão do cadastro do tenant. */
  plan_id?: string
  /**
   * Token gerado pelo MP.js no navegador. Obrigatório de fato quando o plano
   * é pago — sem ele, o backend responde 422 `SUBSCRIPTION_CARD_TOKEN_REQUIRED`
   * (o frontend usa essa resposta para então pedir o cartão, em vez de decidir
   * de antemão se o plano é pago).
   */
  card_token?: string
}

export interface CancelSubscriptionPayload {
  /** `true` = cancelamento imediato; `false`/ausente = ao fim do ciclo vigente. */
  immediately?: boolean
  reason?: string
}

export interface WithdrawalResult {
  protocol: string
}

export interface UpdatePaymentMethodPayload {
  /** Token já gerado pelo MP.js no navegador — nunca dado de cartão cru. */
  card_token: string
}

export interface ChangePlanPayload {
  plan_id: string
  billing_period: 'monthly' | 'quarterly' | 'yearly'
  /** Token do MP.js — obrigatório de fato quando o novo plano é pago (mesma regra de `CreateSubscriptionPayload`). */
  card_token?: string
}

/** Item do histórico completo de assinaturas (`GET /subscription/history`) — todas ao longo do tempo, não só a atual. */
export interface SubscriptionHistoryItem {
  uuid: string
  status: SubscriptionStatus
  billing_period: string
  plan: SubscriptionPlan | null
  trial_ends_at: string | null
  current_period_start: string | null
  current_period_end: string | null
  cancel_at: string | null
  canceled_at: string | null
  auto_renew: boolean
  created_at: string | null
}

/**
 * Espelha `RefundResource` (`GET /subscription/refunds`) — histórico
 * unificado de estornos do tenant: pedido pago cancelado, arrependimento de
 * assinatura (CDC art. 49) e contestação/chargeback, todos na mesma tabela
 * `refunds` no backend. `reason` já vem como texto descritivo da origem
 * (ex. "Cancelamento de pedido pago", "Arrependimento (7 dias)"), não há
 * campo de categoria separado.
 */
export interface RefundItem {
  uuid: string
  reason: string | null
  amount: number
  /** `total` | `partial` — valor integral ou parcial do pagamento original. */
  type: string
  protocol: string
  provider_refund_id: string | null
  /** `requested` | `processing` | `done` | `failed`, ou status bruto repassado pelo provedor de pagamento. */
  status: string
  created_at: string | null
}

/** Espelha `PlanPricingResource` (`GET /subscription/plan-pricing`). Um item por período com preço ativo em `plan_prices`. */
export interface PlanPricingBillingPeriod {
  billing_period: 'monthly' | 'quarterly' | 'yearly'
  months: number
  /** Desconto aplicado sobre `list_amount` para chegar em `total_amount` (já congelado em `plan_prices.discount_percent`). */
  discount_percent: number
  /** Preço de tabela do período, antes do desconto (decimal string, ex. "299.70"). */
  list_amount: string
  /** Valor total realmente cobrado no período (decimal string). */
  total_amount: string
  /** `total_amount / months`, para comparação visual entre períodos (decimal string). */
  monthly_equivalent: string
}

export interface PlanPricingFunctionality {
  uuid: string
  name: string
  slug: string
  description: string | null
}

/** Preço real (não espelhado no frontend) do plano ATUAL do tenant, por período de cobrança. */
export interface PlanPricing {
  plan: {
    uuid: string
    name: string
    slug: string
    description: string | null
  }
  billing_periods: PlanPricingBillingPeriod[]
  functionalities: PlanPricingFunctionality[]
}
