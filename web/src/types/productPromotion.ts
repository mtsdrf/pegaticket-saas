export interface ProductPromotionProductRef {
  uuid: string
  name: string
}

/** `fixed_price` usa `promo_price` (valor "de/por" absoluto); `percentage` usa `discount_percentage` (% sobre `Product.price` vigente). */
export type ProductPromotionDiscountType = 'fixed_price' | 'percentage'

/** `GET/POST /product-promotions` (`ProductPromotionResource`) — upsert 1 por produto, mesmo padrão de `StoreDeliveryFee`. */
export interface ProductPromotion {
  uuid: string
  discount_type: ProductPromotionDiscountType
  promo_price: number | null
  discount_percentage: number | null
  /** Preço final calculado pelo backend (`ProductPromotion::effectivePrice()`) — só vem quando `product` está carregado. */
  effective_price?: number
  starts_at: string | null
  expires_at: string | null
  is_active: boolean
  product: ProductPromotionProductRef
}

/**
 * Upsert — se `product_uuid` já tem promoção, o backend atualiza a existente
 * em vez de criar outra. `promo_price` é obrigatório para `discount_type:
 * 'fixed_price'`; `discount_percentage` é obrigatório para `'percentage'` —
 * o backend rejeita o par errado (`discount_percentage` preenchido com
 * `fixed_price`), então só enviar o campo correspondente.
 */
export interface ProductPromotionPayload {
  product_uuid: string
  discount_type: ProductPromotionDiscountType
  promo_price?: number | null
  discount_percentage?: number | null
  starts_at?: string | null
  expires_at?: string | null
}
