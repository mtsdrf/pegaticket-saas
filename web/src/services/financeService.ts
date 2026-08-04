import { apiClient } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type {
  AdminFinanceAdjustmentFilters,
  AdminFinanceReceivableFilters,
  AdminFinanceSettlementFilters,
  FinanceAdjustment,
  FinanceAdjustmentFilters,
  FinanceDashboard,
  FinanceReceivable,
  FinanceReceivableFilters,
  FinanceSettlement,
  FinanceSettlementFilters,
  ReconciliationEntry,
  ReconciliationFilters,
  ReconciliationSummary,
} from '../types/finance'

export function listReconciliation(filters: ReconciliationFilters): Promise<PaginatedResult<ReconciliationEntry>> {
  return listPaginated<ReconciliationEntry>('/finance/reconciliation', filters)
}

export async function getReconciliationSummary(
  filters: Pick<ReconciliationFilters, 'from' | 'to'>,
): Promise<ReconciliationSummary> {
  const response = await apiClient.get<ApiSuccess<ReconciliationSummary>>('/finance/reconciliation/summary', {
    params: filters,
  })
  return response.data.data
}

export async function getFinanceDashboard(params: { event_uuid?: string } = {}): Promise<FinanceDashboard> {
  const response = await apiClient.get<ApiSuccess<FinanceDashboard>>('/finance/dashboard', { params })
  return response.data.data
}

export function listFinanceReceivables(filters: FinanceReceivableFilters): Promise<PaginatedResult<FinanceReceivable>> {
  return listPaginated<FinanceReceivable>('/finance/receivables', filters)
}

export function listFinanceSettlements(filters: FinanceSettlementFilters): Promise<PaginatedResult<FinanceSettlement>> {
  return listPaginated<FinanceSettlement>('/finance/settlements', filters)
}

export function listFinanceAdjustments(filters: FinanceAdjustmentFilters): Promise<PaginatedResult<FinanceAdjustment>> {
  return listPaginated<FinanceAdjustment>('/finance/adjustments', filters)
}

export async function getAdminFinanceDashboard(params: { tenant_uuid?: string } = {}): Promise<FinanceDashboard> {
  const response = await apiClient.get<ApiSuccess<FinanceDashboard>>('/finance/admin/dashboard', { params })
  return response.data.data
}

export function listAdminFinanceReceivables(filters: AdminFinanceReceivableFilters): Promise<PaginatedResult<FinanceReceivable>> {
  return listPaginated<FinanceReceivable>('/finance/admin/receivables', filters)
}

export function listAdminFinanceSettlements(filters: AdminFinanceSettlementFilters): Promise<PaginatedResult<FinanceSettlement>> {
  return listPaginated<FinanceSettlement>('/finance/admin/settlements', filters)
}

export function listAdminFinanceAdjustments(filters: AdminFinanceAdjustmentFilters): Promise<PaginatedResult<FinanceAdjustment>> {
  return listPaginated<FinanceAdjustment>('/finance/admin/adjustments', filters)
}
