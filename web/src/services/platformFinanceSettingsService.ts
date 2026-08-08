import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { PlatformFinanceSettings, PlatformFinanceSettingsPayload } from '../types/platformFinanceSettings'

/** Rota global (sem tenant), permissão `payment_admin`. */
export function getPlatformFinanceSettings(): Promise<PlatformFinanceSettings> {
  return unwrap(apiClient.get<ApiSuccess<PlatformFinanceSettings>>('/finance/platform-settings'))
}

export function updatePlatformFinanceSettings(
  payload: PlatformFinanceSettingsPayload,
): Promise<PlatformFinanceSettings> {
  return unwrap(apiClient.put<ApiSuccess<PlatformFinanceSettings>>('/finance/platform-settings', payload))
}
