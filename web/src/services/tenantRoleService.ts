import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { Functionality, TenantRole, TenantRolePayload, TenantRolePermission } from '../types/admin'
import type { PaginatedResult } from '../types/pagination'

export function listTenantRoles(params: object): Promise<PaginatedResult<TenantRole>> {
  return listPaginated<TenantRole>('/tenant-roles', params)
}

export async function getTenantRole(uuid: string): Promise<TenantRole> {
  const { items } = await listPaginated<TenantRole>('/tenant-roles', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Perfil da empresa não encontrado.')
  return found
}

export function getTenantRolePermissions(uuid: string): Promise<TenantRolePermission[]> {
  return unwrap(apiClient.get<ApiSuccess<TenantRolePermission[]>>(`/tenant-roles/${uuid}/permissions`))
}

/**
 * Funcionalidades permitidas pelo plano da empresa ativa — usa o endpoint
 * tenant-scoped em vez de `/functionalities` (exige permissão global
 * `functionalities:read`, exclusiva de admin da plataforma; o dono da
 * empresa nunca tem essa permissão, então montar a matriz de permissões
 * com o service global sempre dava 403 pra ele).
 */
export function listAvailableFunctionalities(): Promise<Functionality[]> {
  return unwrap(apiClient.get<ApiSuccess<Functionality[]>>('/tenant-roles/functionalities'))
}

export function createTenantRole(payload: TenantRolePayload): Promise<TenantRole> {
  return unwrap(apiClient.post<ApiSuccess<TenantRole>>('/tenant-roles', payload))
}

export function updateTenantRole(uuid: string, payload: Partial<TenantRolePayload>): Promise<TenantRole> {
  return unwrap(apiClient.put<ApiSuccess<TenantRole>>(`/tenant-roles/${uuid}`, payload))
}

export function deleteTenantRole(uuid: string): Promise<void> {
  return apiClient.delete(`/tenant-roles/${uuid}`).then(() => undefined)
}

export function syncTenantRolePermissions(uuid: string, permissions: TenantRolePermission[]): Promise<void> {
  return apiClient.post(`/tenant-roles/${uuid}/permissions/sync`, { permissions }).then(() => undefined)
}
