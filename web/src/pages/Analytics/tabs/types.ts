export interface AnalyticsTabProps {
  from: string
  to: string
  /** Sobe o gating de plano para a página inteira (evita repetir o aviso por aba). */
  onPlanLocked: () => void
}
