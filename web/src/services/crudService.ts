import { apiClient } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult, PaginationMeta } from '../types/pagination'

/**
 * `unwrap()` descarta `meta` — listagem paginada precisa de
 * `meta.pagination`, então todo `index` paginado passa por aqui em vez
 * do helper padrão. Reaproveitado por qualquer service de listagem.
 */
export async function listPaginated<T>(
  url: string,
  params: object,
): Promise<PaginatedResult<T>> {
  const response = await apiClient.get<ApiSuccess<T[]>>(url, { params })
  const meta = response.data.meta as { pagination?: PaginationMeta }
  const perPage = (params as { per_page?: number }).per_page

  return {
    items: response.data.data,
    pagination: meta.pagination ?? {
      current_page: 1,
      per_page: perPage ?? response.data.data.length,
      total: response.data.data.length,
      last_page: 1,
    },
  }
}
