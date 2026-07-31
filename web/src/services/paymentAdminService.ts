import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaymentIssue, PaymentIssueReprocessResult, PaymentIssueType } from '../types/paymentAdmin'
import type { PaginatedResult } from '../types/pagination'

/**
 * Painel cross-tenant do staff interno (`payment_admin:read|update`, ver
 * `PaymentIssueController`). Não tem `sort_by`/`sort_dir` — o backend mescla
 * 4 fontes em memória e ordena sempre por `occurred_at desc` (ver
 * `PaymentIssueService::list`), só `type` e paginação são aceitos.
 */
export function listPaymentIssues(params: { type?: PaymentIssueType; page?: number; per_page?: number }): Promise<PaginatedResult<PaymentIssue>> {
  return listPaginated<PaymentIssue>('/payments/issues', params)
}

export function reprocessPaymentIssue(type: PaymentIssueType, reference: string): Promise<PaymentIssueReprocessResult> {
  return unwrap(apiClient.post<ApiSuccess<PaymentIssueReprocessResult>>(`/payments/issues/${reference}/reprocess`, { type }))
}
