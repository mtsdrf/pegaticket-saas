import { useCallback, useEffect, useRef, useState } from 'react'
import { getApiErrorMessage, isPlanUpgradeRequired } from '../types/api'
import { useAuth } from './useAuth'

interface AnalyticsDataState<T> {
  data: T | null
  isLoading: boolean
  error: string | null
  /** true quando a API respondeu `PLAN_UPGRADE_REQUIRED` — mostrar `PlanUpgradeNotice`, não erro genérico. */
  planLocked: boolean
}

/**
 * Busca de dado de análise tenant-scoped: espera o tenant ativo resolver
 * (mesma razão de `useDashboardReport`), trata loading/erro/gating de plano
 * e refaz a busca quando `key` muda (serializar from/to/filtros na key).
 */
export function useAnalyticsData<T>(fetcher: () => Promise<T>, key: string) {
  const { activeTenantUuid } = useAuth()
  const [nonce, setNonce] = useState(0)
  const [state, setState] = useState<AnalyticsDataState<T>>({
    data: null,
    isLoading: true,
    error: null,
    planLocked: false,
  })

  const fetcherRef = useRef(fetcher)
  fetcherRef.current = fetcher

  useEffect(() => {
    if (!activeTenantUuid) {
      setState((previous) => ({ ...previous, isLoading: true, error: null }))
      return
    }

    let cancelled = false
    setState((previous) => ({ ...previous, isLoading: true, error: null, planLocked: false }))

    fetcherRef
      .current()
      .then((data) => {
        if (!cancelled) setState({ data, isLoading: false, error: null, planLocked: false })
      })
      .catch((error: unknown) => {
        if (cancelled) return
        if (isPlanUpgradeRequired(error)) {
          setState({ data: null, isLoading: false, error: null, planLocked: true })
          return
        }
        setState({
          data: null,
          isLoading: false,
          error: getApiErrorMessage(error, 'Não foi possível carregar as análises agora.'),
          planLocked: false,
        })
      })

    return () => {
      cancelled = true
    }
  }, [activeTenantUuid, key, nonce])

  const reload = useCallback(() => setNonce((current) => current + 1), [])

  return { ...state, reload }
}
