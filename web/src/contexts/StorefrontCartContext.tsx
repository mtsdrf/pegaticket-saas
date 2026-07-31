import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { storefrontCartStorageKey } from '../constants/storage'
import type { StorefrontCartItem, StorefrontEvent, StorefrontEventProduct, StorefrontTicketType } from '../types/storefront'
import { useDebouncedValue } from '../hooks/useDebouncedValue'
import { readOrCreateCartSession, sendCartEvent } from '../utils/cartTelemetry'
import { StorefrontCartContext, type StorefrontCartContextValue } from './storefront-cart-context'

function createCartItemId(): string {
  return globalThis.crypto?.randomUUID?.() ?? `cart-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

function readCart(slug: string): StorefrontCartItem[] {
  try {
    const raw = localStorage.getItem(storefrontCartStorageKey(slug))
    if (!raw) return []
    const parsed = JSON.parse(raw) as unknown
    return Array.isArray(parsed) ? (parsed as StorefrontCartItem[]) : []
  } catch {
    // JSON corrompido/manipulado manualmente — carrinho vazio é mais seguro que travar a tela.
    return []
  }
}

function writeCart(slug: string, items: StorefrontCartItem[]): void {
  localStorage.setItem(storefrontCartStorageKey(slug), JSON.stringify(items))
}

/**
 * Carrinho da loja pública — 100% local (`localStorage`), chave por slug
 * (ver `constants/storage.ts`) pra nunca misturar carrinho de tenants
 * diferentes no mesmo navegador. Navegação e montagem do carrinho são
 * anônimas; a identidade (OTP do Portal) só é exigida no passo final do
 * checkout (`StorefrontCheckoutPage`), não aqui.
 *
 * Migração PegaTicket (2026-07-31): item de carrinho passou de `Product`
 * pra `TicketType`/`EventProduct` — sem opcionais/promoções/atacado
 * (conceitos exclusivos do domínio de comércio removido do backend).
 */
export function StorefrontCartProvider({ slug, children }: { slug: string; children: ReactNode }) {
  const [items, setItems] = useState<StorefrontCartItem[]>(() => readCart(slug))
  const [cartSession, setCartSession] = useState(() => readOrCreateCartSession(slug))

  // Troca de loja (slug muda) recarrega o carrinho daquela loja — não reaproveita estado da anterior.
  useEffect(() => {
    setItems(readCart(slug))
    setCartSession(readOrCreateCartSession(slug))
  }, [slug])

  useEffect(() => {
    writeCart(slug, items)
  }, [slug, items])

  const totalQuantity = useMemo(() => items.reduce((sum, item) => sum + item.quantity, 0), [items])
  const totalAmount = useMemo(() => items.reduce((sum, item) => sum + item.quantity * item.unit_price, 0), [items])

  // Telemetria de carrinho/abandono (roadmap A3.14) — best-effort, nunca
  // bloqueia a experiência de compra (ver `utils/cartTelemetry.ts`).
  const hasSentRecoveredRef = useRef(false)
  const hadItemsRef = useRef(items.length > 0)
  const skipNextUpdateEventRef = useRef(false)

  useEffect(() => {
    hasSentRecoveredRef.current = false
    hadItemsRef.current = items.length > 0
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug])

  // Carrinho retomado de uma visita anterior (localStorage ainda não expirado) — dispara uma vez por sessão.
  useEffect(() => {
    if (hasSentRecoveredRef.current) return
    if (cartSession.isRecovered && items.length > 0) {
      sendCartEvent(slug, cartSession.sessionId, 'cart_recovered', items, totalAmount)
      hasSentRecoveredRef.current = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug, cartSession, items])

  // Primeiro item do carrinho nesta sessão — evento imediato, sem debounce.
  useEffect(() => {
    const hasItems = items.length > 0
    if (hasItems && !hadItemsRef.current) {
      sendCartEvent(slug, cartSession.sessionId, 'cart_viewed', items, totalAmount)
      skipNextUpdateEventRef.current = true
    }
    hadItemsRef.current = hasItems
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [items, slug, cartSession.sessionId])

  // Mudanças subsequentes no carrinho já não-vazio — debounced pra não disparar a cada clique de +/-.
  const debouncedItems = useDebouncedValue(items, 1500)
  const isFirstDebounceRef = useRef(true)
  useEffect(() => {
    if (isFirstDebounceRef.current) {
      isFirstDebounceRef.current = false
      return
    }
    if (skipNextUpdateEventRef.current) {
      skipNextUpdateEventRef.current = false
      return
    }
    if (debouncedItems.length === 0) return
    const total = debouncedItems.reduce((sum, item) => sum + item.quantity * item.unit_price, 0)
    sendCartEvent(slug, cartSession.sessionId, 'cart_updated', debouncedItems, total)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedItems])

  const addTicketType = useCallback((event: StorefrontEvent, ticketType: StorefrontTicketType, quantity = 1) => {
    setItems((current) => {
      const existing = current.find((item) => item.ticket_type_uuid === ticketType.uuid)
      if (existing) {
        return current.map((item) =>
          item.ticket_type_uuid === ticketType.uuid ? { ...item, quantity: item.quantity + quantity } : item,
        )
      }
      return [
        ...current,
        {
          id: createCartItemId(),
          ticket_type_uuid: ticketType.uuid,
          name: ticketType.name,
          event_name: event.name,
          unit_price: ticketType.price,
          image_url: ticketType.image_url,
          quantity,
        },
      ]
    })
  }, [])

  const addEventProduct = useCallback((event: StorefrontEvent, eventProduct: StorefrontEventProduct, quantity = 1) => {
    setItems((current) => {
      const existing = current.find((item) => item.event_product_uuid === eventProduct.uuid)
      if (existing) {
        return current.map((item) =>
          item.event_product_uuid === eventProduct.uuid ? { ...item, quantity: item.quantity + quantity } : item,
        )
      }
      return [
        ...current,
        {
          id: createCartItemId(),
          event_product_uuid: eventProduct.uuid,
          name: eventProduct.name,
          event_name: event.name,
          unit_price: eventProduct.price,
          image_url: null,
          quantity,
        },
      ]
    })
  }, [])

  const removeItem = useCallback((itemId: string) => {
    setItems((current) => {
      const hasDirectId = current.some((item) => item.id === itemId)
      if (hasDirectId) {
        return current.filter((item) => item.id !== itemId)
      }
      return current.filter((item) => item.ticket_type_uuid !== itemId && item.event_product_uuid !== itemId)
    })
  }, [])

  const updateQuantity = useCallback((itemId: string, quantity: number) => {
    setItems((current) => {
      const hasDirectId = current.some((item) => item.id === itemId)
      const matches = (item: StorefrontCartItem) =>
        hasDirectId ? item.id === itemId : item.ticket_type_uuid === itemId || item.event_product_uuid === itemId

      if (quantity <= 0) {
        return current.filter((item) => !matches(item))
      }

      return current.map((item) => (matches(item) ? { ...item, quantity } : item))
    })
  }, [])

  const setItemNotes = useCallback((itemId: string, notes: string) => {
    setItems((current) => current.map((item) => (item.id === itemId ? { ...item, notes } : item)))
  }, [])

  const clear = useCallback(() => setItems([]), [])

  const getQuantity = useCallback(
    (uuid: string) => items.find((item) => item.ticket_type_uuid === uuid || item.event_product_uuid === uuid)?.quantity ?? 0,
    [items],
  )

  const value = useMemo<StorefrontCartContextValue>(
    () => ({
      items,
      totalQuantity,
      totalAmount,
      addTicketType,
      addEventProduct,
      removeItem,
      updateQuantity,
      setItemNotes,
      clear,
      getQuantity,
      sessionId: cartSession.sessionId,
    }),
    [
      items,
      totalQuantity,
      totalAmount,
      addTicketType,
      addEventProduct,
      removeItem,
      updateQuantity,
      setItemNotes,
      clear,
      getQuantity,
      cartSession.sessionId,
    ],
  )

  return <StorefrontCartContext.Provider value={value}>{children}</StorefrontCartContext.Provider>
}
