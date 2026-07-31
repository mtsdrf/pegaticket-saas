import { STORAGE_KEYS } from '../constants/storage'

export function getOfflineDeviceId(): string {
  const existing = localStorage.getItem(STORAGE_KEYS.offlineDeviceId)
  if (existing) {
    return existing
  }

  const next = crypto.randomUUID()
  localStorage.setItem(STORAGE_KEYS.offlineDeviceId, next)
  return next
}
