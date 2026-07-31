import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { RouteCandidatesResponse, RouteType } from '../types/route'

export function getRouteCandidates(type: RouteType, date: string): Promise<RouteCandidatesResponse> {
  return unwrap(
    apiClient.get<ApiSuccess<RouteCandidatesResponse>>('/routes/candidates', { params: { type, date } }),
  )
}
