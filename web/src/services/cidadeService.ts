import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Cidade, CidadeFilters, CidadePayload } from '../types/location'

export function listCidades(filters: CidadeFilters): Promise<PaginatedResult<Cidade>> {
  return listPaginated<Cidade>('/cidades', filters)
}

/**
 * Sem `GET /cidades/{uuid}` na API — busca a lista e resolve por uuid (mesma
 * estratégia de Categoria/Tipo de produto). Diferente daqueles, o volume real
 * de cidades pode passar de 100 (limite de `per_page` do backend) num estado
 * grande — nesse caso a edição de uma cidade fora das primeiras 100 falha
 * com "não encontrada". Registrado como limitação conhecida, não um bug
 * silencioso: a API não expõe filtro por `uuid` nem endpoint de detalhe.
 */
export async function getCidade(uuid: string): Promise<Cidade> {
  const { items } = await listPaginated<Cidade>('/cidades', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Cidade não encontrada.')
  return found
}

export function createCidade(payload: CidadePayload): Promise<Cidade> {
  return unwrap(apiClient.post<ApiSuccess<Cidade>>('/cidades', payload))
}

export function updateCidade(uuid: string, payload: Partial<CidadePayload>): Promise<Cidade> {
  return unwrap(apiClient.put<ApiSuccess<Cidade>>(`/cidades/${uuid}`, payload))
}

export function deleteCidade(uuid: string): Promise<void> {
  return apiClient.delete(`/cidades/${uuid}`).then(() => undefined)
}
