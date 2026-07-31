import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { AdminUser, AdminUserPayload } from '../types/admin'
import type { PaginatedResult } from '../types/pagination'

export function listUsers(params: object): Promise<PaginatedResult<AdminUser>> {
  return listPaginated<AdminUser>('/users', params)
}

export function getUser(uuid: string): Promise<AdminUser> {
  return unwrap(apiClient.get<ApiSuccess<AdminUser>>(`/users/${uuid}`))
}

export function createUser(payload: AdminUserPayload): Promise<AdminUser> {
  return unwrap(apiClient.post<ApiSuccess<AdminUser>>('/users', payload))
}

export function updateUser(uuid: string, payload: Partial<AdminUserPayload>): Promise<AdminUser> {
  return unwrap(apiClient.put<ApiSuccess<AdminUser>>(`/users/${uuid}`, payload))
}

export function deleteUser(uuid: string): Promise<void> {
  return apiClient.delete(`/users/${uuid}`).then(() => undefined)
}
