import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Endereco, EnderecoFilters, EnderecoPayload } from '../types/location'

export function listEnderecos(filters: EnderecoFilters): Promise<PaginatedResult<Endereco>> {
  return listPaginated<Endereco>('/enderecos', filters)
}

/** Sem `GET /enderecos/{uuid}` na API — mesma estratégia de Categoria/Tipo de produto (tenant-scoped, volume tende a ficar bem abaixo de 100 por tenant). */
export async function getEndereco(uuid: string): Promise<Endereco> {
  const { items } = await listPaginated<Endereco>('/enderecos', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Endereço não encontrado.')
  return found
}

export function createEndereco(payload: EnderecoPayload): Promise<Endereco> {
  return unwrap(apiClient.post<ApiSuccess<Endereco>>('/enderecos', payload))
}

export function updateEndereco(uuid: string, payload: Partial<EnderecoPayload>): Promise<Endereco> {
  return unwrap(apiClient.put<ApiSuccess<Endereco>>(`/enderecos/${uuid}`, payload))
}

export function deleteEndereco(uuid: string): Promise<void> {
  return apiClient.delete(`/enderecos/${uuid}`).then(() => undefined)
}
