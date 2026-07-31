import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { CreatePrivacyRequestPayload, PrivacyRequest, UpdatePrivacyRequestPayload } from '../types/privacy'

export function listPrivacyRequests(): Promise<PrivacyRequest[]> {
  return unwrap(apiClient.get<ApiSuccess<PrivacyRequest[]>>('/tenant-profile/privacy-requests'))
}

export function createPrivacyRequest(payload: CreatePrivacyRequestPayload): Promise<PrivacyRequest> {
  return unwrap(apiClient.post<ApiSuccess<PrivacyRequest>>('/tenant-profile/privacy-requests', payload))
}

export function updatePrivacyRequest(uuid: string, payload: UpdatePrivacyRequestPayload): Promise<PrivacyRequest> {
  return unwrap(apiClient.put<ApiSuccess<PrivacyRequest>>(`/tenant-profile/privacy-requests/${uuid}`, payload))
}
