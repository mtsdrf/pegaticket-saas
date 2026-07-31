/** Campos de preço de um produto/item de carrinho relevantes ao cálculo do preço efetivo. */
export interface UnitPricingInput {
  price: number
  promo_price: number | null
  wholesale_min_quantity: number | null
  wholesale_price: number | null
}

/**
 * Preço unitário efetivo (roadmap Loja): promoção sempre vence; senão atacado
 * quando `quantity >= wholesale_min_quantity`; senão o preço base. O carrinho
 * não conhece a categoria de cliente antes do login — o backend recalcula com
 * autoridade no checkout (mesmo espírito do preview de cashback).
 */
export function computeUnitPrice(input: UnitPricingInput, quantity: number): number {
  if (input.promo_price !== null) {
    return input.promo_price
  }

  if (
    input.wholesale_min_quantity !== null &&
    input.wholesale_price !== null &&
    quantity >= input.wholesale_min_quantity
  ) {
    return input.wholesale_price
  }

  return input.price
}
