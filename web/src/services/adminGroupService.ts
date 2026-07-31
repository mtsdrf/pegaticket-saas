import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { AdminGroup, AdminGroupPayload } from '../types/admin'
import type { PaginatedResult } from '../types/pagination'

export function listGroups(params: object): Promise<PaginatedResult<AdminGroup>> {
  return listPaginated<AdminGroup>('/groups', params)
}

export function getGroup(uuid: string): Promise<AdminGroup> {
  return unwrap(apiClient.get<ApiSuccess<AdminGroup>>(`/groups/${uuid}`))
}

export function createGroup(payload: AdminGroupPayload): Promise<AdminGroup> {
  return unwrap(apiClient.post<ApiSuccess<AdminGroup>>('/groups', payload))
}

export function updateGroup(uuid: string, payload: Partial<AdminGroupPayload>): Promise<AdminGroup> {
  return unwrap(apiClient.put<ApiSuccess<AdminGroup>>(`/groups/${uuid}`, payload))
}

export function deleteGroup(uuid: string): Promise<void> {
  return apiClient.delete(`/groups/${uuid}`).then(() => undefined)
}

export function syncGroupUsers(uuid: string, user_uuids: string[]): Promise<AdminGroup> {
  return unwrap(apiClient.post<ApiSuccess<AdminGroup>>(`/groups/${uuid}/users/sync`, { user_uuids }))
}
