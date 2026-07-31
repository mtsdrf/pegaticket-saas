import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { TenantFeatureOverride, TenantFeatureOverrideInput } from '../types/admin'

export function getTenantFeatureOverrides(tenantUuid: string): Promise<TenantFeatureOverride[]> {
  return unwrap(apiClient.get<ApiSuccess<TenantFeatureOverride[]>>(`/tenants/${tenantUuid}/feature-overrides`))
}

export function syncTenantFeatureOverrides(tenantUuid: string, overrides: TenantFeatureOverrideInput[]): Promise<void> {
  return apiClient.post(`/tenants/${tenantUuid}/feature-overrides/sync`, { overrides }).then(() => undefined)
}
