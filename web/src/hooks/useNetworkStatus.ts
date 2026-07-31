import { useEffect, useState } from 'react'
import { API_OFFLINE_EVENT, API_ONLINE_EVENT } from '../utils/connectionEvents'

export function useNetworkStatus() {
  const [isOffline, setIsOffline] = useState(() => !navigator.onLine)

  useEffect(() => {
    function handleOffline() {
      setIsOffline(true)
    }

    function handleOnline() {
      setIsOffline(false)
    }

    window.addEventListener('offline', handleOffline)
    window.addEventListener('online', handleOnline)
    window.addEventListener(API_OFFLINE_EVENT, handleOffline)
    window.addEventListener(API_ONLINE_EVENT, handleOnline)

    return () => {
      window.removeEventListener('offline', handleOffline)
      window.removeEventListener('online', handleOnline)
      window.removeEventListener(API_OFFLINE_EVENT, handleOffline)
      window.removeEventListener(API_ONLINE_EVENT, handleOnline)
    }
  }, [])

  return isOffline
}
