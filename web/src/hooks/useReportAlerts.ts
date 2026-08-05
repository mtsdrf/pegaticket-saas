import { useEffect, useState } from 'react'
import * as reportService from '../services/reportService'
import type { ReportAlert } from '../types/report'
import { useAuth } from './useAuth'

interface ReportAlertsState {
  alerts: ReportAlert[] | null
  isLoading: boolean
}

/** Alertas básicos do Home (roadmap Fase A1) — sem período, ver AlertsCard. */
export function useReportAlerts(enabled: boolean = true) {
  const { activeTenantUuid } = useAuth()
  const [state, setState] = useState<ReportAlertsState>({ alerts: null, isLoading: true })

  useEffect(() => {
    if (!enabled || !activeTenantUuid) {
      setState((previous) => ({ ...previous, isLoading: !enabled ? false : previous.isLoading }))
      return
    }

    let cancelled = false
    setState((previous) => ({ ...previous, isLoading: true }))

    reportService
      .getAlerts()
      .then((alerts) => {
        if (!cancelled) setState({ alerts, isLoading: false })
      })
      .catch(() => {
        if (!cancelled) setState({ alerts: null, isLoading: false })
      })

    return () => {
      cancelled = true
    }
  }, [activeTenantUuid, enabled])

  return state
}
