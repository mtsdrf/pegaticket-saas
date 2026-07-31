import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { ReactivationRule, UpdateReactivationRulePayload } from '../types/reactivationRule'

export function getReactivationRule(): Promise<ReactivationRule> {
  return unwrap(apiClient.get<ApiSuccess<ReactivationRule>>('/reactivation-rule'))
}

export function updateReactivationRule(payload: UpdateReactivationRulePayload): Promise<ReactivationRule> {
  return unwrap(apiClient.put<ApiSuccess<ReactivationRule>>('/reactivation-rule', payload))
}
