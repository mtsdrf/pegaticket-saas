export type FiscalReadinessStatus = 'ok' | 'warning' | 'error' | 'attention'

export interface FiscalReadinessCheck {
  key: string
  label: string
  status: Exclude<FiscalReadinessStatus, 'attention'>
  details: string
}

export interface FiscalReadiness {
  status: FiscalReadinessStatus
  score_percent: number
  checks: FiscalReadinessCheck[]
}
