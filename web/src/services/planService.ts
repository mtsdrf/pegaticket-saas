import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { Plan, PlanFunctionalityRef, PlanPayload } from '../types/admin'
import type { PaginatedResult } from '../types/pagination'

export function listPlans(params: object): Promise<PaginatedResult<Plan>> {
  return listPaginated<Plan>('/plans', params)
}

export async function getPlan(uuid: string): Promise<Plan> {
  return unwrap(apiClient.get<ApiSuccess<Plan>>(`/plans/${uuid}`))
}

export function createPlan(payload: PlanPayload): Promise<Plan> {
  return unwrap(apiClient.post<ApiSuccess<Plan>>('/plans', payload))
}

export function updatePlan(uuid: string, payload: PlanPayload): Promise<Plan> {
  return unwrap(apiClient.put<ApiSuccess<Plan>>(`/plans/${uuid}`, payload))
}

export function deletePlan(uuid: string): Promise<void> {
  return apiClient.delete(`/plans/${uuid}`).then(() => undefined)
}

export async function getPlanFunctionalities(uuid: string): Promise<PlanFunctionalityRef[]> {
  return unwrap(apiClient.get<ApiSuccess<PlanFunctionalityRef[]>>(`/plans/${uuid}/functionalities`))
}

export function syncPlanFunctionalities(uuid: string, functionalities: string[]): Promise<void> {
  return apiClient.post(`/plans/${uuid}/functionalities/sync`, { functionalities }).then(() => undefined)
}
