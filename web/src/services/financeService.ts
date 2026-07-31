import { apiClient } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { ReconciliationEntry, ReconciliationFilters, ReconciliationSummary } from '../types/finance'

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
