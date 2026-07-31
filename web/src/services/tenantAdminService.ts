import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { Tenant, TenantPayload } from '../types/admin'
import type { PaginatedResult } from '../types/pagination'

export function listTenants(params: object): Promise<PaginatedResult<Tenant>> {
  return listPaginated<Tenant>('/tenants', params)
}

export async function getTenant(uuid: string): Promise<Tenant> {
  const { items } = await listPaginated<Tenant>('/tenants', { per_page: 200 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Empresa não encontrada.')
  return found
}

/** `undefined`/`null` ficam de fora do FormData; booleano vira "1"/"0" (regra `boolean` do Laravel aceita ambos). */
function buildFormData(payload: Record<string, unknown>, logoFile?: File | null): FormData {
  const formData = new FormData()

  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null) return
    formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  if (logoFile) formData.append('logo', logoFile)

  return formData
}

export function createTenant(payload: TenantPayload, logoFile?: File | null): Promise<Tenant> {
  const formData = buildFormData(payload as unknown as Record<string, unknown>, logoFile)
  return unwrap(apiClient.post<ApiSuccess<Tenant>>('/tenants', formData))
}

/**
 * Sem logo novo, envia PUT normal (JSON). Com logo novo, o Laravel precisa
 * de multipart — mesmo spoofing de `eventService.updateEvent` (PHP não
 * faz parse de multipart em PUT nativo): POST + campo `_method=PUT`.
 */
export function updateTenant(uuid: string, payload: Partial<TenantPayload>, logoFile?: File | null): Promise<Tenant> {
  if (!logoFile) {
    return unwrap(apiClient.put<ApiSuccess<Tenant>>(`/tenants/${uuid}`, payload))
  }

  const formData = buildFormData(payload as unknown as Record<string, unknown>, logoFile)
  formData.append('_method', 'PUT')
  return unwrap(apiClient.post<ApiSuccess<Tenant>>(`/tenants/${uuid}`, formData))
}

export function deleteTenant(uuid: string): Promise<void> {
  return apiClient.delete(`/tenants/${uuid}`).then(() => undefined)
}
