import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { ProductPromotion, ProductPromotionPayload } from '../types/productPromotion'

export function listPromotions(): Promise<ProductPromotion[]> {
  return unwrap(apiClient.get<ApiSuccess<ProductPromotion[]>>('/product-promotions'))
}

/** Upsert — se o produto já tem promoção, o backend atualiza a existente em vez de criar outra. */
export function upsertPromotion(payload: ProductPromotionPayload): Promise<ProductPromotion> {
  return unwrap(apiClient.post<ApiSuccess<ProductPromotion>>('/product-promotions', payload))
}

export function deletePromotion(uuid: string): Promise<void> {
  return apiClient.delete(`/product-promotions/${uuid}`).then(() => undefined)
}
