export interface FiscalReadinessCheck {
  key: string
  label: string
  status: 'ok' | 'warning'
  details: string
}

export interface FiscalReadiness {
  status: 'ready' | 'attention'
  score_percent: number
  checks: FiscalReadinessCheck[]
}
