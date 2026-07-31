import { useEffect, useRef } from 'react'
import { sendCartEvent } from '../utils/cartTelemetry'
import { useStorefrontCart } from './useStorefrontCart'

/**
 * Dispara `cart_abandoned` (telemetria best-effort, roadmap A3.14) quando o
 * visitante sai da tela de carrinho/checkout sem finalizar: troca de aba/app
 * em segundo plano (`visibilitychange`) ou fecha/navega pra fora do app
 * (`pagehide`) — mais confiável que `beforeunload` em mobile (Safari não
 * dispara `beforeunload` de forma consistente, especialmente com bfcache).
 * Navegação interna da SPA (ex.: carrinho → checkout) não muda a
 * visibilidade da página, então não dispara falso positivo.
 *
 * Chame `markCompleted()` antes de navegar para longe após um checkout
 * bem-sucedido, para não registrar abandono nesse caso.
 */
export function useCartAbandonmentTelemetry(slug: string | undefined) {
  const { items, totalAmount, sessionId } = useStorefrontCart()
  const completedRef = useRef(false)
  const itemsRef = useRef(items)
  const totalRef = useRef(totalAmount)
  itemsRef.current = items
  totalRef.current = totalAmount

  useEffect(() => {
    if (!slug) return

    function fireAbandoned() {
      if (completedRef.current) return
      if (itemsRef.current.length === 0) return
      sendCartEvent(slug as string, sessionId, 'cart_abandoned', itemsRef.current, totalRef.current, { useBeacon: true })
    }

    function handleVisibilityChange() {
      if (document.visibilityState === 'hidden') fireAbandoned()
    }

    document.addEventListener('visibilitychange', handleVisibilityChange)
    window.addEventListener('pagehide', fireAbandoned)

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange)
      window.removeEventListener('pagehide', fireAbandoned)
    }
  }, [slug, sessionId])

  return { markCompleted: () => { completedRef.current = true } }
}
