import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { TenantUser, TenantUserInvite, TenantUserInvitePayload, TenantUserPayload } from '../types/admin'
import type { PaginatedResult } from '../types/pagination'

export function listTenantUsers(params: object): Promise<PaginatedResult<TenantUser>> {
  return listPaginated<TenantUser>('/tenant-users', params)
}

export async function getTenantUser(uuid: string): Promise<TenantUser> {
  const { items } = await listPaginated<TenantUser>('/tenant-users', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Vínculo da empresa não encontrado.')
  return found
}

export function createTenantUser(payload: TenantUserPayload): Promise<TenantUser> {
  return unwrap(apiClient.post<ApiSuccess<TenantUser>>('/tenant-users', payload))
}

export function updateTenantUser(uuid: string, payload: Partial<TenantUserPayload>): Promise<TenantUser> {
  return unwrap(apiClient.put<ApiSuccess<TenantUser>>(`/tenant-users/${uuid}`, payload))
}

export function deleteTenantUser(uuid: string): Promise<void> {
  return apiClient.delete(`/tenant-users/${uuid}`).then(() => undefined)
}

export function inviteTenantUser(payload: TenantUserInvitePayload): Promise<TenantUserInvite> {
  return unwrap(apiClient.post<ApiSuccess<TenantUserInvite>>('/tenant-users/invite', payload))
}
