import { createContext } from 'react'
import type { StorefrontCartItem, StorefrontProduct } from '../types/storefront'

export interface StorefrontCartSelectedOption {
  product_option_uuid: string
  group_name: string
  name: string
  quantity: number
  unit_price: number
}

export interface StorefrontCartContextValue {
  items: StorefrontCartItem[]
  totalQuantity: number
  totalAmount: number
  addItem: (product: StorefrontProduct, quantity?: number) => void
  addConfiguredItem: (product: StorefrontProduct, quantity: number, options: StorefrontCartSelectedOption[]) => void
  removeItem: (itemId: string) => void
  updateQuantity: (itemId: string, quantity: number) => void
  /** Observação livre do cliente sobre o item (ex: "sem cebola") — persistida no carrinho local e enviada como `items[].notes` no checkout. */
  setItemNotes: (itemId: string, notes: string) => void
  clear: () => void
  getQuantity: (productUuid: string) => number
  /** Id anônimo de sessão de telemetria de carrinho (roadmap A3.14) — ver `utils/cartTelemetry.ts`. */
  sessionId: string
}

export const StorefrontCartContext = createContext<StorefrontCartContextValue | null>(null)
