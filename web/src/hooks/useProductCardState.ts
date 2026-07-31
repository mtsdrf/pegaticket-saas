import { useStorefrontCart } from './useStorefrontCart'
import type { StorefrontProduct } from '../types/storefront'

/** `Math.round((1 - promo/price) * 100)` — só usado para o rótulo "X% OFF"; o preço exibido continua vindo pronto do backend (`promo_price`), nunca recalculado aqui. */
function discountPercentLabel(price: number, promoPrice: number): string | null {
  if (price <= 0 || promoPrice >= price) return null
  const percent = Math.round((1 - promoPrice / price) * 100)
  return percent > 0 ? `${percent}% OFF` : null
}

/**
 * Estado/lógica compartilhada entre os cards de produto da loja pública
 * (`ProductListItem` e `ProductGridCard`) — quantidade no carrinho, se tem
 * opcionais, rótulo de desconto e atacado. Só a estrutura visual/DOM difere
 * entre os dois layouts; extraído aqui pra não duplicar a lógica quando o
 * dono do produto trocar de ideia de novo sobre qual layout é o padrão.
 */
export function useProductCardState(product: StorefrontProduct) {
  const { addItem, updateQuantity, getQuantity } = useStorefrontCart()
  const quantity = getQuantity(product.uuid)
  const hasOptions = (product.option_groups?.length ?? 0) > 0
  const discountLabel = product.promo_price !== null ? discountPercentLabel(product.price, product.promo_price) : null
  const showWholesaleNote =
    product.promo_price === null && product.wholesale_min_quantity !== null && product.wholesale_price !== null

  return { quantity, hasOptions, discountLabel, showWholesaleNote, addItem, updateQuantity }
}
