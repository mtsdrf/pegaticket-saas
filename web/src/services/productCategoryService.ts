import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { ProductCategory, ProductCategoryFilters, ProductCategoryPayload } from '../types/productCategory'

export function listProductCategories(
  filters: ProductCategoryFilters,
): Promise<PaginatedResult<ProductCategory>> {
  return listPaginated<ProductCategory>('/product-categories', filters)
}

/**
 * A API não tem `GET /product-categories/{uuid}` (só index/store/update/
 * destroy) — pra editar, busca a lista (as categorias de um tenant são
 * uma lista curta e enumerável) e resolve o registro pelo uuid no client.
 */
export async function getProductCategory(uuid: string): Promise<ProductCategory> {
  const { items } = await listPaginated<ProductCategory>('/product-categories', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Categoria de produto não encontrada.')
  return found
}

export function createProductCategory(payload: ProductCategoryPayload): Promise<ProductCategory> {
  return unwrap(apiClient.post<ApiSuccess<ProductCategory>>('/product-categories', payload))
}

export function updateProductCategory(
  uuid: string,
  payload: Partial<ProductCategoryPayload>,
): Promise<ProductCategory> {
  return unwrap(apiClient.put<ApiSuccess<ProductCategory>>(`/product-categories/${uuid}`, payload))
}

export function deleteProductCategory(uuid: string): Promise<void> {
  return apiClient.delete(`/product-categories/${uuid}`).then(() => undefined)
}
