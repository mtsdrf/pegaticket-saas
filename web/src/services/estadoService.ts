import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Estado, EstadoFilters, EstadoPayload } from '../types/location'

export function listEstados(filters: EstadoFilters): Promise<PaginatedResult<Estado>> {
  return listPaginated<Estado>('/estados', filters)
}

/** Sem `GET /estados/{uuid}` na API — mesma estratégia usada em Categoria/Tipo de produto (busca a lista e resolve por uuid). */
export async function getEstado(uuid: string): Promise<Estado> {
  const { items } = await listPaginated<Estado>('/estados', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Estado não encontrado.')
  return found
}

export function createEstado(payload: EstadoPayload): Promise<Estado> {
  return unwrap(apiClient.post<ApiSuccess<Estado>>('/estados', payload))
}

export function updateEstado(uuid: string, payload: Partial<EstadoPayload>): Promise<Estado> {
  return unwrap(apiClient.put<ApiSuccess<Estado>>(`/estados/${uuid}`, payload))
}

export function deleteEstado(uuid: string): Promise<void> {
  return apiClient.delete(`/estados/${uuid}`).then(() => undefined)
}
