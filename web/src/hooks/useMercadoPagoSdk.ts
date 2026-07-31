import { useEffect, useState } from 'react'

const SDK_SRC = 'https://sdk.mercadopago.com/js/v2'

/** Objeto real devolvido por `new window.MercadoPago(...)` — só o método usado por `ChangeCardDialog` é tipado. */
export interface MercadoPagoInstance {
  createCardToken(data: {
    cardNumber: string
    cardholderName: string
    cardExpirationMonth: string
    cardExpirationYear: string
    securityCode: string
    identificationType: string
    identificationNumber: string
  }): Promise<{ id: string }>
}

declare global {
  interface Window {
    MercadoPago?: new (publicKey: string, options?: { locale?: string }) => MercadoPagoInstance
  }
}

let scriptPromise: Promise<void> | null = null

function loadSdkScript(): Promise<void> {
  if (window.MercadoPago) return Promise.resolve()

  scriptPromise ??= new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${SDK_SRC}"]`)
    if (existing) {
      existing.addEventListener('load', () => resolve())
      existing.addEventListener('error', () => reject(new Error('Falha ao carregar o SDK do Mercado Pago.')))
      return
    }

    const script = document.createElement('script')
    script.src = SDK_SRC
    script.async = true
    script.addEventListener('load', () => resolve())
    script.addEventListener('error', () => reject(new Error('Falha ao carregar o SDK do Mercado Pago.')))
    document.head.append(script)
  })

  return scriptPromise
}

/**
 * Carrega o MP.js v2 sob demanda (só quando o diálogo de troca de cartão
 * abre) e devolve uma instância pronta de `MercadoPago`. A tokenização
 * (`createCardToken`) acontece inteiramente no navegador, direto contra a
 * API do Mercado Pago — nenhum dado de cartão cru chega ao backend Maskats,
 * só o token resultante (mesmo padrão já usado no checkout Pix/pedido, ver
 * `.claude/memory/architecture-decisions.md`).
 */
export function useMercadoPagoSdk(enabled: boolean) {
  const [mp, setMp] = useState<MercadoPagoInstance | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!enabled) return

    const environment = import.meta.env.VITE_MERCADOPAGO_ENVIRONMENT as string | undefined
    const publicKey = (
      environment === 'production'
        ? import.meta.env.VITE_MERCADOPAGO_PUBLIC_KEY_PROD
        : import.meta.env.VITE_MERCADOPAGO_PUBLIC_KEY_TEST
    ) as string | undefined
    if (!publicKey) {
      setError('Troca de cartão indisponível: configuração de pagamento ausente.')
      return
    }

    let cancelled = false
    setError(null)

    loadSdkScript()
      .then(() => {
        if (cancelled || !window.MercadoPago) return
        setMp(new window.MercadoPago(publicKey, { locale: 'pt-BR' }))
      })
      .catch(() => {
        if (!cancelled) setError('Não foi possível carregar o formulário de cartão agora. Tente novamente.')
      })

    return () => {
      cancelled = true
    }
  }, [enabled])

  return { mp, error }
}
