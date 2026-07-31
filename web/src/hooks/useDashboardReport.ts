import { useEffect, useState } from 'react'
import * as reportService from '../services/reportService'
import { getApiErrorMessage } from '../types/api'
import type { OperationHealthSummary, ReportCharts, ReportIndicators } from '../types/report'
import { useAuth } from './useAuth'

interface DashboardReportState {
  indicators: ReportIndicators | null
  charts: ReportCharts | null
  operationHealth: OperationHealthSummary | null
  isLoading: boolean
  error: string | null
}

export function useDashboardReport(dateFrom: string, dateTo: string, enabled: boolean = true) {
  const { activeTenantUuid } = useAuth()
  const [state, setState] = useState<DashboardReportState>({
    indicators: null,
    charts: null,
    operationHealth: null,
    isLoading: true,
    error: null,
  })

  useEffect(() => {
    // Usuário sem a permissão `dashboard:read` não deve nem chamar a API
    // (sempre daria 403) — quem decide isso é a página, via `enabled`.
    if (!enabled) {
      setState({ indicators: null, charts: null, operationHealth: null, isLoading: false, error: null })
      return
    }

    // Sem tenant ativo o token ainda não carrega claim de tenant — a
    // API responde 403 para qualquer rota tenant-scoped. Espera o
    // tenant ser resolvido (auto-seleção ou escolha manual) em vez de
    // disparar uma requisição fadada a falhar.
    if (!activeTenantUuid) {
      setState((previous) => ({ ...previous, isLoading: true, error: null }))
      return
    }

    let cancelled = false
    setState((previous) => ({ ...previous, isLoading: true, error: null }))

    const params = { date_from: dateFrom, date_to: dateTo }

    Promise.all([
      reportService.getIndicators(params),
      reportService.getCharts(params),
      reportService.getOperationHealth(),
    ])
      .then(([indicators, charts, operationHealth]) => {
        if (!cancelled) setState({ indicators, charts, operationHealth, isLoading: false, error: null })
      })
      .catch((error) => {
        if (!cancelled) {
          setState({
            indicators: null,
            charts: null,
            operationHealth: null,
            isLoading: false,
            error: getApiErrorMessage(error, 'Não foi possível carregar os números da operação agora.'),
          })
        }
      })

    return () => {
      cancelled = true
    }
  }, [activeTenantUuid, dateFrom, dateTo, enabled])

  return state
}
