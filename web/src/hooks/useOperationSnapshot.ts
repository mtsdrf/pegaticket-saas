import { useEffect, useState } from 'react'
import { useAuth } from './useAuth'
import * as operationSnapshotService from '../services/operationSnapshotService'
import type { OperationSnapshot } from '../types/operationSnapshot'

const POLL_INTERVAL_MS = 30_000

interface OperationSnapshotState {
  snapshot: OperationSnapshot | null
  isLoading: boolean
}

export function useOperationSnapshot(enabled: boolean = true) {
  const { activeTenantUuid } = useAuth()
  const [state, setState] = useState<OperationSnapshotState>({ snapshot: null, isLoading: true })

  useEffect(() => {
    if (!enabled) {
      setState({ snapshot: null, isLoading: false })
      return
    }

    if (!activeTenantUuid) {
      setState((previous) => ({ ...previous, isLoading: true }))
      return
    }

    let cancelled = false

    function load() {
      operationSnapshotService
        .getOperationSnapshot()
        .then((snapshot) => {
          if (!cancelled) setState({ snapshot, isLoading: false })
        })
        .catch(() => {
          if (!cancelled) setState((previous) => ({ ...previous, isLoading: false }))
        })
    }

    setState((previous) => ({ ...previous, isLoading: true }))
    load()
    const interval = window.setInterval(load, POLL_INTERVAL_MS)

    return () => {
      cancelled = true
      window.clearInterval(interval)
    }
  }, [activeTenantUuid, enabled])

  return state
}
