import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { ProductType, ProductTypeFilters, ProductTypePayload } from '../types/productType'

export function listProductTypes(filters: ProductTypeFilters): Promise<PaginatedResult<ProductType>> {
  return listPaginated<ProductType>('/product-types', filters)
}

/** Sem `GET /product-types/{uuid}` na API — mesma estratégia de `productCategoryService.getProductCategory`. */
export async function getProductType(uuid: string): Promise<ProductType> {
  const { items } = await listPaginated<ProductType>('/product-types', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Tipo de produto não encontrado.')
  return found
}

export function createProductType(payload: ProductTypePayload): Promise<ProductType> {
  return unwrap(apiClient.post<ApiSuccess<ProductType>>('/product-types', payload))
}

export function updateProductType(uuid: string, payload: Partial<ProductTypePayload>): Promise<ProductType> {
  return unwrap(apiClient.put<ApiSuccess<ProductType>>(`/product-types/${uuid}`, payload))
}

export function deleteProductType(uuid: string): Promise<void> {
  return apiClient.delete(`/product-types/${uuid}`).then(() => undefined)
}
