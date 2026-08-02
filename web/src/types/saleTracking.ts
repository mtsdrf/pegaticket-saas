export interface SaleTrackingItem {
  product_name: string
  seat_label: string | null
  seat_sector_name: string | null
  seat_kind: string | null
  quantity: string
  unit: string | null
  unit_price: string
  line_total: string
}

export interface SaleTrackingInstallment {
  installment_number: number
  amount: string
  due_date: string
  is_paid: boolean
  paid_at: string | null
}

/** Espelha a resposta pública (sem auth) de `GET /rastreio/{uuid}`. `installments` só vem preenchido quando `is_installment=true`. */
export interface SaleTracking {
  uuid: string
  tenant_name: string
  final_customer_name: string
  is_installment: boolean
  total_amount: string
  is_paid: boolean
  paid_at: string | null
  is_completed: boolean
  completed_at: string | null
  /** `cancellation_requested` (roadmap A4) — solicitação feita via Portal autenticado, não por aqui (rota pública sem login). */
  status: 'pending_approval' | 'confirmed' | 'rejected' | 'cancellation_requested'
  is_cancelled: boolean
  created_at: string
  items: SaleTrackingItem[]
  installments: SaleTrackingInstallment[]
}
