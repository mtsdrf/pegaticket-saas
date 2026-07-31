import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { storefrontCartStorageKey } from '../constants/storage'
import type { StorefrontCartItem, StorefrontProduct } from '../types/storefront'
import { useDebouncedValue } from '../hooks/useDebouncedValue'
import { readOrCreateCartSession, sendCartEvent } from '../utils/cartTelemetry'
import { computeUnitPrice } from '../utils/storefrontPricing'
import {
  StorefrontCartContext,
  type StorefrontCartContextValue,
  type StorefrontCartSelectedOption,
} from './storefront-cart-context'

function createCartItemId(): string {
  return globalThis.crypto?.randomUUID?.() ?? `cart-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`
}

function buildConfigurationLabel(options: StorefrontCartSelectedOption[]): string | null {
  if (options.length === 0) return null

  return options
    .map((option) => `${option.group_name}: ${option.name}${option.quantity > 1 ? ` x${option.quantity}` : ''}`)
    .join(' • ')
}

/**
 * Normaliza um item lido do `localStorage` — carrinhos gravados antes do
 * preço de atacado (roadmap Loja) não têm `price`/`promo_price`/`wholesale_*`;
 * retrocompatibiliza a partir do `unit_price` já persistido pra o recálculo de
 * quantidade nunca produzir `NaN`/`undefined`.
 */
function normalizeCartItem(item: StorefrontCartItem): StorefrontCartItem {
  return {
    ...item,
    id: item.id ?? createCartItemId(),
    configuration_label: item.configuration_label ?? buildConfigurationLabel(item.options ?? []),
    price: item.price ?? item.unit_price,
    promo_price: item.promo_price ?? null,
    wholesale_min_quantity: item.wholesale_min_quantity ?? null,
    wholesale_price: item.wholesale_price ?? null,
    options: item.options ?? [],
  }
}

function readCart(slug: string): StorefrontCartItem[] {
  try {
    const raw = localStorage.getItem(storefrontCartStorageKey(slug))
    if (!raw) return []
    const parsed = JSON.parse(raw) as unknown
    return Array.isArray(parsed) ? (parsed as StorefrontCartItem[]).map(normalizeCartItem) : []
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

  const addItem = useCallback((product: StorefrontProduct, quantity = 1) => {
    setItems((current) => {
      const existing = current.find((item) => item.product_uuid === product.uuid)
      if (existing) {
        const newQuantity = existing.quantity + quantity
        return current.map((item) =>
          item.product_uuid === product.uuid
            ? { ...item, quantity: newQuantity, unit_price: computeUnitPrice(item, newQuantity) }
            : item,
        )
      }
      const pricing = {
        price: product.price,
        promo_price: product.promo_price,
        wholesale_min_quantity: product.wholesale_min_quantity,
        wholesale_price: product.wholesale_price,
      }
      return [
        ...current,
        {
          id: createCartItemId(),
          product_uuid: product.uuid,
          name: product.name,
          image_url: product.image_url,
          quantity,
          configuration_label: null,
          unit_price: computeUnitPrice(pricing, quantity),
          ...pricing,
          options: [],
        },
      ]
    })
  }, [])

  const addConfiguredItem = useCallback((product: StorefrontProduct, quantity: number, options: StorefrontCartSelectedOption[]) => {
    const pricing = {
      price: product.price,
      promo_price: product.promo_price,
      wholesale_min_quantity: product.wholesale_min_quantity,
      wholesale_price: product.wholesale_price,
    }
    const baseUnitPrice = computeUnitPrice(pricing, quantity)
    const optionsUnitPrice = options.reduce((sum, option) => sum + option.unit_price * option.quantity, 0)

    setItems((current) => [
      ...current,
      {
        id: createCartItemId(),
        product_uuid: product.uuid,
        name: product.name,
        image_url: product.image_url,
        quantity,
        configuration_label: buildConfigurationLabel(options),
        unit_price: baseUnitPrice + optionsUnitPrice,
        ...pricing,
        options,
      },
    ])
  }, [])

  const removeItem = useCallback((itemId: string) => {
    setItems((current) => {
      const hasDirectId = current.some((item) => item.id === itemId)
      if (hasDirectId) {
        return current.filter((item) => item.id !== itemId)
      }

      return current.filter((item) => !(item.product_uuid === itemId && (item.options?.length ?? 0) === 0))
    })
  }, [])

  const updateQuantity = useCallback((itemId: string, quantity: number) => {
    setItems((current) => {
      const hasDirectId = current.some((item) => item.id === itemId)

      if (quantity <= 0) {
        return hasDirectId
          ? current.filter((item) => item.id !== itemId)
          : current.filter((item) => !(item.product_uuid === itemId && (item.options?.length ?? 0) === 0))
      }

      return current.map((item) =>
        (hasDirectId ? item.id === itemId : item.product_uuid === itemId && (item.options?.length ?? 0) === 0)
          ? {
              ...item,
              quantity,
              unit_price:
                computeUnitPrice(item, quantity) +
                (item.options ?? []).reduce((sum, option) => sum + option.unit_price * option.quantity, 0),
            }
          : item,
      )
    })
  }, [])

  const setItemNotes = useCallback((itemId: string, notes: string) => {
    setItems((current) => current.map((item) => (item.id === itemId ? { ...item, notes } : item)))
  }, [])

  const clear = useCallback(() => setItems([]), [])

  const getQuantity = useCallback(
    (productUuid: string) => items.find((item) => item.product_uuid === productUuid)?.quantity ?? 0,
    [items],
  )

  const value = useMemo<StorefrontCartContextValue>(
    () => ({
      items,
      totalQuantity,
      totalAmount,
      addItem,
      addConfiguredItem,
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
      addItem,
      addConfiguredItem,
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
