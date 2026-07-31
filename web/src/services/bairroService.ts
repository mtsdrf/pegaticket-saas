import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Bairro, BairroFilters, BairroPayload } from '../types/location'

export function listBairros(filters: BairroFilters): Promise<PaginatedResult<Bairro>> {
  return listPaginated<Bairro>('/bairros', filters)
}

/** Sem `GET /bairros/{uuid}` na API — mesma estratégia e mesma ressalva de volume de `cidadeService.getCidade`. */
export async function getBairro(uuid: string): Promise<Bairro> {
  const { items } = await listPaginated<Bairro>('/bairros', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Bairro não encontrado.')
  return found
}

export function createBairro(payload: BairroPayload): Promise<Bairro> {
  return unwrap(apiClient.post<ApiSuccess<Bairro>>('/bairros', payload))
}

export function updateBairro(uuid: string, payload: Partial<BairroPayload>): Promise<Bairro> {
  return unwrap(apiClient.put<ApiSuccess<Bairro>>(`/bairros/${uuid}`, payload))
}

export function deleteBairro(uuid: string): Promise<void> {
  return apiClient.delete(`/bairros/${uuid}`).then(() => undefined)
}
