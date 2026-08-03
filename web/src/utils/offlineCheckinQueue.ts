import type { CheckinTicketPayload } from '../types/ticket'

const STORAGE_KEY = 'pegaticket.offline_checkin_queue'

export interface QueuedCheckin {
  id: string
  payload: CheckinTicketPayload
  queued_at: string
}

/**
 * Fila de check-in offline (roadmap Fase 2 — "modo offline controlado para
 * acesso"). Persistida em `localStorage` (sobrevive a reload/fechar aba,
 * mesmo padrão de simplicidade de `checkinContextStorageKey`) — sem
 * IndexedDB/service worker novo, escopo mínimo: guardar as tentativas que
 * falharam por falta de rede e reenviar assim que a conexão voltar. Cada
 * item é reenviado via `checkinTicket()` (mesmo endpoint idempotente por
 * ticket) quando a fila é drenada.
 */
export function getQueuedCheckins(): QueuedCheckin[] {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function persist(queue: QueuedCheckin[]): void {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(queue))
}

export function enqueueCheckin(payload: CheckinTicketPayload): QueuedCheckin {
  const item: QueuedCheckin = {
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    payload,
    queued_at: new Date().toISOString(),
  }
  persist([...getQueuedCheckins(), item])
  return item
}

export function removeQueuedCheckin(id: string): void {
  persist(getQueuedCheckins().filter((item) => item.id !== id))
}

export function clearQueuedCheckins(): void {
  window.localStorage.removeItem(STORAGE_KEY)
}
