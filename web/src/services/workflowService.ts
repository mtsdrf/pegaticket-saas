import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { WorkflowTransitionLog } from '../types/workflow'

export function getOrderWorkflowTimeline(orderUuid: string): Promise<WorkflowTransitionLog[]> {
  return unwrap(apiClient.get<ApiSuccess<WorkflowTransitionLog[]>>(`/sales/${orderUuid}/workflow-transitions`))
}

export function getStorefrontOrderWorkflowTimeline(orderUuid: string): Promise<WorkflowTransitionLog[]> {
  return unwrap(apiClient.get<ApiSuccess<WorkflowTransitionLog[]>>(`/storefront-sales/${orderUuid}/workflow-transitions`))
}
