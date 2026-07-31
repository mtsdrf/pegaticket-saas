import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { TenantSettings, UpdateTenantSettingsPayload } from '../types/tenantSettings'

export function getTenantSettings(): Promise<TenantSettings> {
  return unwrap(apiClient.get<ApiSuccess<TenantSettings>>('/tenant-settings'))
}

export function updateTenantSettings(payload: UpdateTenantSettingsPayload): Promise<TenantSettings> {
  return unwrap(apiClient.put<ApiSuccess<TenantSettings>>('/tenant-settings', payload))
}
