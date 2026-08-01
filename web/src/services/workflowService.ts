import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { WorkflowTransitionLog } from '../types/workflow'

export function getSaleWorkflowTimeline(saleUuid: string): Promise<WorkflowTransitionLog[]> {
  return unwrap(apiClient.get<ApiSuccess<WorkflowTransitionLog[]>>(`/sales/${saleUuid}/workflow-transitions`))
}

export function getStorefrontSaleWorkflowTimeline(saleUuid: string): Promise<WorkflowTransitionLog[]> {
  return unwrap(apiClient.get<ApiSuccess<WorkflowTransitionLog[]>>(`/storefront-sales/${saleUuid}/workflow-transitions`))
}
