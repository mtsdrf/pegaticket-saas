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
    if (!Array.isArray(parsed)) return []
    return parsed
      .filter((item): item is StorefrontCartItem => typeof item === 'object' && item !== null)
      .map((item) => ({
        ...item,
        event_slug: typeof item.event_slug === 'string' && item.event_slug.trim() ? item.event_slug : null,
        session_uuid: typeof item.session_uuid === 'string' && item.session_uuid.trim() ? item.session_uuid : null,
        session_name: typeof item.session_name === 'string' && item.session_name.trim() ? item.session_name : null,
        seat_uuid: typeof item.seat_uuid === 'string' && item.seat_uuid.trim() ? item.seat_uuid : null,
        seat_label: typeof item.seat_label === 'string' && item.seat_label.trim() ? item.seat_label : null,
        seat_sector_name:
          typeof item.seat_sector_name === 'string' && item.seat_sector_name.trim() ? item.seat_sector_name : null,
        seat_kind: typeof item.seat_kind === 'string' && item.seat_kind.trim() ? item.seat_kind : null,
        seat_capacity: typeof item.seat_capacity === 'number' && Number.isFinite(item.seat_capacity) ? item.seat_capacity : null,
      }))
  } catch {
    // JSON corrompido/manipulado manualmente — carrinho vazio é mais seguro que travar a tela.
    return []
  }
}

function writeCart(slug: string, items: StorefrontCartItem[]): void {
  localStorage.setItem(storefrontCartStorageKey(slug), JSON.stringify(items))
}

function isExclusiveSeatKind(kind: string | null | undefined): boolean {
  return kind === 'mesa' || kind === 'camarote'
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

  const addTicketType = useCallback((
    event: StorefrontEvent,
    ticketType: StorefrontTicketType,
    quantity = 1,
    session?: { uuid: string; name: string } | null,
    seat?: { uuid: string; label: string; sector_name?: string | null; kind: string; capacity?: number | null } | null,
  ) => {
    setItems((current) => {
      const normalizedSessionUuid = session?.uuid ?? null
      const normalizedSeatUuid = seat?.uuid ?? null
      const seatCapacity = Math.max(1, seat?.capacity ?? 1)
      const existing = current.find(
        (item) =>
          item.ticket_type_uuid === ticketType.uuid &&
          (item.session_uuid ?? null) === normalizedSessionUuid &&
          (item.seat_uuid ?? null) === normalizedSeatUuid,
      )
      if (existing) {
        return current.map((item) =>
          item.id === existing.id
            ? {
                ...item,
                quantity: existing.seat_uuid
                  ? isExclusiveSeatKind(existing.seat_kind)
                    ? Math.max(1, existing.seat_capacity ?? 1)
                    : Math.min(existing.quantity + quantity, Math.max(1, existing.seat_capacity ?? 1))
                  : item.quantity + quantity,
              }
            : item,
        )
      }
      return [
        ...current,
        {
          id: createCartItemId(),
          ticket_type_uuid: ticketType.uuid,
          name: ticketType.name,
          event_name: event.name,
          event_slug: event.slug,
          session_uuid: session?.uuid ?? null,
          session_name: session?.name ?? null,
          seat_uuid: seat?.uuid ?? null,
          seat_label: seat?.label ?? null,
          seat_sector_name: seat?.sector_name ?? null,
          seat_kind: seat?.kind ?? null,
          seat_capacity: seat?.capacity ?? null,
          unit_price: ticketType.price,
          image_url: ticketType.image_url,
          quantity: seat
            ? isExclusiveSeatKind(seat.kind)
              ? seatCapacity
              : Math.min(quantity, seatCapacity)
            : quantity,
        },
      ]
    })
  }, [])

  const addEventProduct = useCallback((
    event: StorefrontEvent,
    eventProduct: StorefrontEventProduct,
    quantity = 1,
    session?: { uuid: string; name: string } | null,
  ) => {
    setItems((current) => {
      const normalizedSessionUuid = session?.uuid ?? null
      const existing = current.find(
        (item) => item.event_product_uuid === eventProduct.uuid && (item.session_uuid ?? null) === normalizedSessionUuid,
      )
      if (existing) {
        return current.map((item) =>
          item.id === existing.id ? { ...item, quantity: item.quantity + quantity } : item,
        )
      }
      return [
        ...current,
        {
          id: createCartItemId(),
          event_product_uuid: eventProduct.uuid,
          name: eventProduct.name,
          event_name: event.name,
          event_slug: event.slug,
          session_uuid: session?.uuid ?? null,
          session_name: session?.name ?? null,
          seat_uuid: null,
          seat_label: null,
          seat_sector_name: null,
          seat_kind: null,
          seat_capacity: null,
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

      const matchedItem = current.find((item) => matches(item))
      const normalizedQuantity = matchedItem?.seat_uuid
        ? isExclusiveSeatKind(matchedItem.seat_kind)
          ? quantity <= 0
            ? 0
            : Math.max(1, matchedItem.seat_capacity ?? 1)
          : Math.max(0, Math.min(quantity, Math.max(1, matchedItem.seat_capacity ?? 1)))
        : quantity

      if (normalizedQuantity <= 0) {
        return current.filter((item) => !matches(item))
      }

      return current.map((item) => (matches(item) ? { ...item, quantity: normalizedQuantity } : item))
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
