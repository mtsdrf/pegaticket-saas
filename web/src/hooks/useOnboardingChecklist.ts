import { useEffect, useState } from 'react'
import * as onboardingService from '../services/onboardingService'
import type { OnboardingChecklist } from '../types/onboarding'
import { useAuth } from './useAuth'

interface OnboardingChecklistState {
  checklist: OnboardingChecklist | null
  isLoading: boolean
  error: string | null
}

export function useOnboardingChecklist(enabled: boolean = true) {
  const { activeTenantUuid } = useAuth()
  const [state, setState] = useState<OnboardingChecklistState>({
    checklist: null,
    isLoading: true,
    error: null,
  })

  useEffect(() => {
    if (!enabled || !activeTenantUuid) {
      setState({ checklist: null, isLoading: false, error: null })
      return
    }

    let cancelled = false
    setState((previous) => ({ ...previous, isLoading: true, error: null }))

    onboardingService
      .getOnboardingChecklist()
      .then((checklist) => {
        if (!cancelled) setState({ checklist, isLoading: false, error: null })
      })
      .catch(() => {
        // Falha aqui é silenciosa de propósito — o checklist é um extra de
        // implantação, não um dado crítico: um erro de rede não deve gerar
        // um Alert no topo do Dashboard, o card simplesmente não aparece.
        if (!cancelled) setState({ checklist: null, isLoading: false, error: null })
      })

    return () => {
      cancelled = true
    }
  }, [activeTenantUuid, enabled])

  async function dismiss() {
    const checklist = await onboardingService.dismissOnboardingChecklist()
    setState({ checklist, isLoading: false, error: null })
    return checklist
  }

  async function restore() {
    const checklist = await onboardingService.restoreOnboardingChecklist()
    setState({ checklist, isLoading: false, error: null })
    return checklist
  }

  return { ...state, dismiss, restore }
}
