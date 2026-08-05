import { useEffect, useRef, useState } from 'react'

const TURNSTILE_SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js'

interface TurnstileRenderOptions {
  sitekey: string
  size?: 'normal' | 'compact' | 'invisible' | 'flexible'
  callback?: (token: string) => void
  'expired-callback'?: () => void
  'error-callback'?: () => void
}

declare global {
  interface Window {
    turnstile?: {
      render: (container: HTMLElement, options: TurnstileRenderOptions) => string
      reset: (widgetId?: string) => void
      remove: (widgetId?: string) => void
    }
  }
}

let turnstileScriptPromise: Promise<void> | null = null

function loadTurnstileScript(): Promise<void> {
  if (window.turnstile) return Promise.resolve()

  if (!turnstileScriptPromise) {
    turnstileScriptPromise = new Promise((resolve, reject) => {
      const script = document.createElement('script')
      script.src = TURNSTILE_SCRIPT_SRC
      script.async = true
      script.defer = true
      script.onload = () => resolve()
      script.onerror = () => reject(new Error('Falha ao carregar o Cloudflare Turnstile'))
      document.head.appendChild(script)
    })
  }

  return turnstileScriptPromise
}

export interface TurnstileWidgetProps {
  onVerify: (token: string) => void
  onExpire?: () => void
  size?: 'normal' | 'compact' | 'invisible' | 'flexible'
}

/**
 * Widget do Cloudflare Turnstile (CAPTCHA gratuito) — camada ADICIONAL ao
 * anti-bot já existente nos formulários públicos (honeypot + tempo mínimo
 * de preenchimento), nunca substitui. Lê a chave pública de
 * `VITE_TURNSTILE_SITE_KEY`; quando ausente, o componente não renderiza
 * nada e o formulário segue funcionando normalmente sem CAPTCHA — mesmo
 * espírito do backend (App\Services\Security\TurnstileVerificationService),
 * que também tolera token ausente enquanto a secret key não estiver
 * configurada.
 */
export function TurnstileWidget({ onVerify, onExpire, size = 'flexible' }: TurnstileWidgetProps) {
  const siteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY as string | undefined
  const containerRef = useRef<HTMLDivElement | null>(null)
  const widgetIdRef = useRef<string | undefined>(undefined)
  const [isScriptReady, setIsScriptReady] = useState(false)

  useEffect(() => {
    if (!siteKey) return

    let cancelled = false

    loadTurnstileScript()
      .then(() => {
        if (!cancelled) setIsScriptReady(true)
      })
      .catch(() => undefined)

    return () => {
      cancelled = true
    }
  }, [siteKey])

  useEffect(() => {
    if (!siteKey || !isScriptReady || !containerRef.current || !window.turnstile) return

    const turnstile = window.turnstile
    widgetIdRef.current = turnstile.render(containerRef.current, {
      sitekey: siteKey,
      size,
      callback: onVerify,
      'expired-callback': onExpire,
      'error-callback': onExpire,
    })

    return () => {
      if (widgetIdRef.current) turnstile.remove(widgetIdRef.current)
      widgetIdRef.current = undefined
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [siteKey, isScriptReady, size])

  if (!siteKey) return null

  return <div ref={containerRef} />
}
