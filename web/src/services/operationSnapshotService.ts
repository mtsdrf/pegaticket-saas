import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { OperationSnapshot } from '../types/operationSnapshot'

export function getOperationSnapshot(): Promise<OperationSnapshot> {
  return unwrap(apiClient.get<ApiSuccess<OperationSnapshot>>('/reports/operation-snapshot'))
}
