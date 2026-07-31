/**
 * Notificações locais de chegada em parada da rota — via Service Worker
 * (`registration.showNotification`), NÃO Web Push (sem VAPID/backend, decisão
 * já tomada: o app fica aberto em segundo plano enquanto o motorista navega
 * pelo Maps/Waze). Todo o módulo falha silenciosamente quando o navegador não
 * suporta Notification/Service Worker, ou quando a permissão é negada — nunca
 * deve quebrar a tela de rota.
 */

const SW_READY_TIMEOUT_MS = 3000

function supportsNotification(): boolean {
  return 'Notification' in window && 'serviceWorker' in navigator
}

/**
 * Pede permissão de notificação — só deve ser chamada a partir de uma ação
 * explícita do usuário (ex: clique em "Montar rota"), nunca no carregamento
 * da página (pedir sem contexto é bloqueado/ignorado por navegadores modernos).
 */
export async function requestNotificationPermission(): Promise<void> {
  if (!supportsNotification() || Notification.permission !== 'default') return

  try {
    await Notification.requestPermission()
  } catch {
    // Navegador recusou/erro inesperado — segue sem notificação, não é crítico.
  }
}

async function getReadyRegistration(): Promise<ServiceWorkerRegistration | null> {
  const existing = await navigator.serviceWorker.getRegistration().catch(() => undefined)
  if (existing?.active) return existing

  // `serviceWorker.ready` nunca rejeita e pode ficar pendente pra sempre se o
  // SW nunca registrar — limitamos a espera pra não deixar a chamada solta.
  return Promise.race([
    navigator.serviceWorker.ready,
    new Promise<null>((resolve) => setTimeout(() => resolve(null), SW_READY_TIMEOUT_MS)),
  ]).catch(() => null)
}

/**
 * Notifica chegada numa parada da rota. `tag` único por parada
 * (`route-arrival-{client_uuid}`) evita notificação duplicada pra mesma
 * parada enquanto o motorista permanece na área de proximidade.
 */
export async function notifyRouteArrival(clientUuid: string, clientName: string): Promise<void> {
  if (!supportsNotification() || Notification.permission !== 'granted') return

  try {
    const registration = await getReadyRegistration()
    if (!registration) return

    await registration.showNotification('Você chegou!', {
      body: `Parada: ${clientName}`,
      icon: '/android-chrome-192x192.png',
      tag: `route-arrival-${clientUuid}`,
    })
  } catch {
    // Notificação é um extra — falha aqui nunca deve quebrar a tela de rota.
  }
}
