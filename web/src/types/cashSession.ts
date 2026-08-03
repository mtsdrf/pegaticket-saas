export type CashSessionStatus = 'aberto' | 'fechado'

export interface CashSession {
  uuid: string
  status: CashSessionStatus
  opening_amount: string
  closing_amount: string | null
  expected_cash_amount: string | null
  difference_amount: string | null
  opening_notes: string | null
  closing_notes: string | null
  opened_at: string
  closed_at: string | null
  opened_by_name?: string | null
  closed_by_name?: string | null
}

export interface OpenCashSessionPayload {
  opening_amount: number
  opening_notes?: string | null
}

export interface CloseCashSessionPayload {
  closing_amount: number
  closing_notes?: string | null
}
