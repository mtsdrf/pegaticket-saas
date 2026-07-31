import { createContext } from 'react'
import type { StorefrontCartItem, StorefrontEvent, StorefrontEventProduct, StorefrontTicketType } from '../types/storefront'

export interface StorefrontCartContextValue {
  items: StorefrontCartItem[]
  totalQuantity: number
  totalAmount: number
  addTicketType: (event: StorefrontEvent, ticketType: StorefrontTicketType, quantity?: number) => void
  addEventProduct: (event: StorefrontEvent, eventProduct: StorefrontEventProduct, quantity?: number) => void
  removeItem: (itemId: string) => void
  updateQuantity: (itemId: string, quantity: number) => void
  /** Observação livre do cliente sobre o item (ex: "meia-entrada") — persistida no carrinho local e enviada como `items[].notes` no checkout. */
  setItemNotes: (itemId: string, notes: string) => void
  clear: () => void
  getQuantity: (ticketTypeOrEventProductUuid: string) => number
  /** Id anônimo de sessão de telemetria de carrinho (roadmap A3.14) — ver `utils/cartTelemetry.ts`. */
  sessionId: string
}

export const StorefrontCartContext = createContext<StorefrontCartContextValue | null>(null)
