import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { StoreBusinessHourDay } from '../types/storeBusinessHours'

export function getBusinessHours(): Promise<StoreBusinessHourDay[]> {
  return unwrap(apiClient.get<ApiSuccess<StoreBusinessHourDay[]>>('/store-settings/business-hours'))
}

/** Substitui os 7 dias de uma vez (`replaceForTenant` no backend) — nunca um dia isolado. */
export function updateBusinessHours(days: StoreBusinessHourDay[]): Promise<StoreBusinessHourDay[]> {
  return unwrap(apiClient.put<ApiSuccess<StoreBusinessHourDay[]>>('/store-settings/business-hours', { days }))
}
