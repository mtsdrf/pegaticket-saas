import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { Functionality, FunctionalityPayload } from '../types/admin'
import type { PaginatedResult } from '../types/pagination'

export function listFunctionalities(params: object): Promise<PaginatedResult<Functionality>> {
  return listPaginated<Functionality>('/functionalities', params)
}

export async function getFunctionality(uuid: string): Promise<Functionality> {
  const { items } = await listPaginated<Functionality>('/functionalities', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Funcionalidade não encontrada.')
  return found
}

export function createFunctionality(payload: FunctionalityPayload): Promise<Functionality> {
  return unwrap(apiClient.post<ApiSuccess<Functionality>>('/functionalities', payload))
}

export function updateFunctionality(uuid: string, payload: Partial<FunctionalityPayload>): Promise<Functionality> {
  return unwrap(apiClient.put<ApiSuccess<Functionality>>(`/functionalities/${uuid}`, payload))
}

export function deleteFunctionality(uuid: string): Promise<void> {
  return apiClient.delete(`/functionalities/${uuid}`).then(() => undefined)
}
