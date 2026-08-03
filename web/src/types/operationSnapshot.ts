export interface OperationSnapshot {
  cash_session: {
    status: 'aberto' | 'fechado'
    opening_amount: string
    expected_cash_amount: string | null
  } | null
  sales_pending_approval_count: number
  sales_today: {
    count: number
    total_amount: string
  }
  checkins_today: {
    total: number
    granted: number
    warning: number
    blocked: number
  }
  generated_at: string
}
