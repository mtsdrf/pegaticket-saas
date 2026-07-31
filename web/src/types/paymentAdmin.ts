/** Espelha `PaymentIssueEntry::toArray()` (backend) — item normalizado de 4
 * fontes heterogêneas (Payment divergente, idempotência ambígua, fatura
 * contestada, webhook não processado), nunca um Model Eloquent. */
export type PaymentIssueType =
  | 'payment_divergent'
  | 'idempotency_ambiguous'
  | 'invoice_disputed'
  | 'webhook_failed'

export interface PaymentIssueTenant {
  uuid: string
  name: string
}

export interface PaymentIssue {
  issue_type: PaymentIssueType
  reference: string
  tenant: PaymentIssueTenant | null
  amount: string | null
  status: string
  occurred_at: string
  reprocessable: boolean
  detail: Record<string, unknown>
}

export interface PaymentIssueReprocessResult {
  reference: string
  status: string
}
