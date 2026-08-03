/** Espelha `ReconciliationRequest`/`ReconciliationEntryResource` do backend (roadmap A3.12). */
export type ReconciliationStatus = 'pending' | 'paid' | 'authorized' | 'in_analysis' | 'failed' | 'canceled' | 'refunded' | 'divergent'

export interface ReconciliationFilters {
  from?: string
  to?: string
  status?: ReconciliationStatus | ''
  method?: string
  page?: number
  per_page?: number
}

export interface ReconciliationRefund {
  uuid: string
  amount: number
  type: string
  status: string
  protocol: string | null
  reason: string | null
}

export interface ReconciliationWebhookEvent {
  provider: string
  external_id: string
  processed_at: string | null
}

export interface ReconciliationEntry {
  uuid: string
  provider: string | null
  provider_charge_id: string | null
  method: string | null
  amount: number
  status: ReconciliationStatus
  paid_at: string | null
  created_at: string
  /** `when($this->payable !== null)` no Resource — ausente quando a venda de origem foi removida. */
  sale?: { uuid: string; codigo: string }
  refunds: ReconciliationRefund[]
  /** `when($this->matched_webhook_event !== null)` — casamento best-effort por `provider`+`provider_charge_id`. */
  webhook_event?: ReconciliationWebhookEvent
}

export interface ReconciliationSummaryByStatus {
  status: string
  count: number
  amount: number
}

export interface ReconciliationSummary {
  by_status: ReconciliationSummaryByStatus[]
  total_refunded_amount: number
}
