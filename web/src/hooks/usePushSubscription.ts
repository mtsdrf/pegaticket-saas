import { useCallback } from 'react'
import { unwrap } from '../services/apiClient'
import { portalApiClient } from '../services/portalApiClient'
import type { ApiSuccess } from '../types/api'

/** Converte a VAPID public key (base64url) para o `BufferSource` exigido por `pushManager.subscribe`. */
function urlBase64ToUint8Array(base64String: string): BufferSource {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replaceAll('-', '+').replaceAll('_', '/')
  const rawData = atob(base64)
  const outputArray = new Uint8Array(rawData.length)
  for (let i = 0; i < rawData.length; i++) {
    outputArray[i] = rawData.charCodeAt(i)
  }
  return outputArray.buffer as ArrayBuffer
}

/**
 * Web Push (VAPID) real, roadmap Delivery Fase 4 — última fatia. Chamado uma
 * vez logo após o `FinalCustomer` autenticar com sucesso (`verifyOtp`, ver
 * `PortalAuthContext.tsx` — ponto único de chamada, cobre checkout e login
 * do portal sem duplicar). Falha silenciosa em qualquer etapa (sem suporte
 * do navegador, permissão negada, `VITE_VAPID_PUBLIC_KEY` não configurada)
 * — nunca deve travar o fluxo de autenticação.
 */
export function usePushSubscription() {
  const subscribeToPush = useCallback(async (): Promise<void> => {
    try {
      const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY
      if (!vapidPublicKey) return
      if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return

      const permission = await Notification.requestPermission()
      if (permission !== 'granted') return

      const registration = await navigator.serviceWorker.ready
      const existing = await registration.pushManager.getSubscription()
      const subscription =
        existing ??
        (await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
        }))

      const json = subscription.toJSON()
      if (!json.endpoint || !json.keys?.p256dh || !json.keys.auth) return

      await unwrap(
        portalApiClient.post<ApiSuccess<null>>('/portal/push-subscriptions', {
          endpoint: json.endpoint,
          keys: { p256dh: json.keys.p256dh, auth: json.keys.auth },
        }),
      )
    } catch {
      // Silêncio de propósito — push é um extra, nunca deve travar login/checkout.
    }
  }, [])

  return { subscribeToPush }
}
