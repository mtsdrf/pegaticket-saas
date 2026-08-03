import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { CashSession, CloseCashSessionPayload, OpenCashSessionPayload } from '../types/cashSession'

export function getCurrentCashSession(): Promise<CashSession | null> {
  return unwrap(apiClient.get<ApiSuccess<CashSession | null>>('/cash-sessions/current'))
}

export function openCashSession(payload: OpenCashSessionPayload): Promise<CashSession> {
  return unwrap(apiClient.post<ApiSuccess<CashSession>>('/cash-sessions/open', payload))
}

export function closeCashSession(payload: CloseCashSessionPayload): Promise<CashSession> {
  return unwrap(apiClient.post<ApiSuccess<CashSession>>('/cash-sessions/close', payload))
}
