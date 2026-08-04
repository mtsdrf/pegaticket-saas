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
  /** Taxa de erro de checkout (roadmap Fase 7) — derivada do funil de InventoryHold (reservado -> convertido/expirado/abandonado) na janela de `window_hours`. */
  checkout: {
    window_hours: number
    started: number
    completed: number
    error_rate_percent: number
  }
  /** Fila virtual para alta demanda (roadmap Fase 7) — soma de todos os eventos com `high_demand_mode` ativo neste tenant. */
  virtual_queue: {
    waiting: number
    admitted: number
  }
  generated_at: string
}
