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
  sale?: { uuid: string; codigo: string }
  refunds: ReconciliationRefund[]
  webhook_event?: ReconciliationWebhookEvent
}

export interface ReconciliationSummaryByStatus {
  status: string
  count: number
  amount: number
}

export interface ReconciliationIntegritySummary {
  receivables_without_settlement: number
  settlement_net_mismatches: number
  released_settlements_missing_ledger: number
  open_adjustments: number
}

export interface ReconciliationSummary {
  by_status: ReconciliationSummaryByStatus[]
  total_refunded_amount: number
  open_adjustments_amount: number
  integrity: ReconciliationIntegritySummary
}

export interface FinanceTenantRef {
  uuid: string
  name: string
}

export interface FinanceSaleRef {
  uuid: string
  codigo: string
}

export interface FinanceEventRef {
  uuid: string
  name: string
}

export interface FinanceSettlementRef {
  uuid: string
  code: string
  status: string
  scheduled_for: string | null
  released_at: string | null
}

export interface FinanceAdjustmentSettlementRef extends FinanceSettlementRef {
  net_amount?: number
}

export interface FinanceReceivable {
  uuid: string
  status: string
  currency: string
  gross_amount: number
  platform_fee_amount: number
  processor_fee_amount: number
  net_amount: number
  available_at: string | null
  event_ends_at: string | null
  provider: string | null
  provider_charge_id: string | null
  provider_split_id: string | null
  tenant?: FinanceTenantRef
  sale?: FinanceSaleRef
  event?: FinanceEventRef
  settlement?: FinanceSettlementRef
  open_adjustments_amount: number
  created_at: string
}

export interface FinanceSettlement {
  uuid: string
  code: string
  status: string
  scheduled_for: string | null
  released_at: string | null
  gross_amount: number
  platform_fee_amount: number
  processor_fee_amount: number
  net_amount: number
  receivables_count: number
  open_adjustments_amount: number
  tenant?: FinanceTenantRef
  metadata?: Record<string, unknown> | null
  created_at: string
}

export interface FinanceAdjustment {
  uuid: string
  type: string
  amount: number
  reason: string
  status: string
  resolution_type: string | null
  resolution_notes: string | null
  resolved_at: string | null
  created_at: string
  metadata?: Record<string, unknown> | null
  tenant?: FinanceTenantRef
  sale?: FinanceSaleRef
  receivable?: {
    uuid: string
    status: string
    net_amount: number
    provider_split_id: string | null
  }
  settlement?: FinanceAdjustmentSettlementRef
}

export interface FinanceDashboardBalances {
  available_now_amount: number
  future_amount: number
  in_custody_amount: number
  released_amount: number
  open_adjustments_amount: number
}

export interface FinanceDashboardQueues {
  pending_receivables_count: number
  released_settlements_count?: number
  open_adjustments_count: number
  scheduled_settlements_count?: number
}

export interface FinanceDashboardStatusBreakdown {
  status: string
  count: number
  amount: number
}

export interface FinanceDashboardTenantBreakdown {
  tenant: FinanceTenantRef
  receivables_count: number
  net_amount: number
}

export interface FinanceDashboard {
  balances: FinanceDashboardBalances
  queues: FinanceDashboardQueues
  upcoming_settlement: (FinanceSettlementRef & { net_amount: number; tenant?: FinanceTenantRef | null }) | null
  receivables_by_status?: FinanceDashboardStatusBreakdown[]
  top_tenants_by_receivables?: FinanceDashboardTenantBreakdown[]
  generated_at: string
}

export interface FinanceReceivableFilters {
  event_uuid?: string
  settlement_uuid?: string
  status?: string
  from?: string
  to?: string
  page?: number
  per_page?: number
}

export interface FinanceSettlementFilters {
  status?: string
  from?: string
  to?: string
  page?: number
  per_page?: number
}

export interface FinanceAdjustmentFilters {
  status?: string
  type?: string
  from?: string
  to?: string
  page?: number
  per_page?: number
}

export interface AdminFinanceFilters {
  tenant_uuid?: string
}

export interface AdminFinanceReceivableFilters extends FinanceReceivableFilters, AdminFinanceFilters {}
export interface AdminFinanceSettlementFilters extends FinanceSettlementFilters, AdminFinanceFilters {}
export interface AdminFinanceAdjustmentFilters extends FinanceAdjustmentFilters, AdminFinanceFilters {}
