import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { ClientCategory, ClientCategoryFilters, ClientCategoryPayload } from '../types/clientCategory'

export function listClientCategories(filters: ClientCategoryFilters): Promise<PaginatedResult<ClientCategory>> {
  return listPaginated<ClientCategory>('/client-categories', filters)
}

export async function getClientCategory(uuid: string): Promise<ClientCategory> {
  const { items } = await listPaginated<ClientCategory>('/client-categories', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Categoria de cliente não encontrada.')
  return found
}

export function createClientCategory(payload: ClientCategoryPayload): Promise<ClientCategory> {
  return unwrap(apiClient.post<ApiSuccess<ClientCategory>>('/client-categories', payload))
}

export function updateClientCategory(uuid: string, payload: Partial<ClientCategoryPayload>): Promise<ClientCategory> {
  return unwrap(apiClient.put<ApiSuccess<ClientCategory>>(`/client-categories/${uuid}`, payload))
}

export function deleteClientCategory(uuid: string): Promise<void> {
  return apiClient.delete(`/client-categories/${uuid}`).then(() => undefined)
}
