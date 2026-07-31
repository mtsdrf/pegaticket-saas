import type { ApiSuccess } from '../types/api'
import type { FiscalReadiness } from '../types/fiscalReadiness'
import { apiClient, unwrap } from './apiClient'

export function getFiscalReadiness(): Promise<FiscalReadiness> {
  return unwrap(apiClient.get<ApiSuccess<FiscalReadiness>>('/fiscal-readiness'))
}
