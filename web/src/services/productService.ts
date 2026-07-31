import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Product, ProductFilters, ProductPayload } from '../types/product'

export function listProducts(filters: ProductFilters): Promise<PaginatedResult<Product>> {
  return listPaginated<Product>('/products', filters)
}

export function getProduct(uuid: string): Promise<Product> {
  return unwrap(apiClient.get<ApiSuccess<Product>>(`/products/${uuid}`))
}

/** `undefined`/`null` ficam de fora do FormData; booleano vira "1"/"0" (regra `boolean` do Laravel aceita ambos). */
function buildFormData(payload: Record<string, unknown>, imageFile?: File | null): FormData {
  const formData = new FormData()

  function appendValue(key: string, value: unknown) {
    if (value === undefined || value === null) return

    if (Array.isArray(value)) {
      value.forEach((item, index) => appendValue(`${key}[${index}]`, item))
      return
    }

    if (value instanceof Blob) {
      formData.append(key, value)
      return
    }

    if (typeof value === 'object') {
      Object.entries(value).forEach(([childKey, childValue]) => appendValue(`${key}[${childKey}]`, childValue))
      return
    }

    formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }

  Object.entries(payload).forEach(([key, value]) => appendValue(key, value))

  if (imageFile) formData.append('image', imageFile)

  return formData
}

export function createProduct(payload: ProductPayload, imageFile?: File | null): Promise<Product> {
  const formData = buildFormData(payload as unknown as Record<string, unknown>, imageFile)
  return unwrap(apiClient.post<ApiSuccess<Product>>('/products', formData))
}

/**
 * Sem imagem nova, envia PUT normal (JSON). Com imagem nova, o Laravel
 * precisa de multipart — PHP não faz parse de multipart em PUT nativo,
 * então usa o spoofing padrão do framework: POST + campo `_method=PUT`.
 */
export function updateProduct(
  uuid: string,
  payload: Partial<ProductPayload>,
  imageFile?: File | null,
): Promise<Product> {
  if (!imageFile) {
    return unwrap(apiClient.put<ApiSuccess<Product>>(`/products/${uuid}`, payload))
  }

  const formData = buildFormData(payload as unknown as Record<string, unknown>, imageFile)
  formData.append('_method', 'PUT')
  return unwrap(apiClient.post<ApiSuccess<Product>>(`/products/${uuid}`, formData))
}

export function deleteProduct(uuid: string): Promise<void> {
  return apiClient.delete(`/products/${uuid}`).then(() => undefined)
}

/** Sem `isAvailable`, o backend inverte o estado atual — usado pelo toggle rápido de bloqueio na listagem. */
export function toggleProductAvailability(uuid: string, isAvailable?: boolean): Promise<Product> {
  return unwrap(
    apiClient.patch<ApiSuccess<Product>>(
      `/products/${uuid}/toggle-availability`,
      isAvailable === undefined ? {} : { is_available: isAvailable },
    ),
  )
}

/** Sem `clientUuid`, sempre resolve pro preço de tabela (`product.price`). */
export function getSuggestedPrice(productUuid: string, clientUuid?: string): Promise<number> {
  return unwrap(
    apiClient.get<ApiSuccess<{ price: string }>>(`/products/${productUuid}/suggested-price`, {
      params: clientUuid ? { client_uuid: clientUuid } : undefined,
    }),
  ).then((result) => Number(result.price))
}

/** Catálogo de produtos em PDF (com imagem embutida), gerado no servidor a partir dos mesmos filtros de `listProducts`. */
export function exportProductsPdf(filters: ProductFilters): Promise<Blob> {
  return apiClient
    .post<Blob>('/products/pdf', filters, { responseType: 'blob' })
    .then((response) => response.data)
}
