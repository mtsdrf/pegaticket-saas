export interface OrderTrackingItem {
  product_name: string
  quantity: string
  unit: string | null
  unit_price: string
  line_total: string
}

export interface OrderTrackingInstallment {
  installment_number: number
  amount: string
  due_date: string
  is_paid: boolean
  paid_at: string | null
}

/** Espelha a resposta pública (sem auth) de `GET /rastreio/{uuid}`. `installments` só vem preenchido quando `is_installment=true`. */
export interface OrderTracking {
  uuid: string
  tenant_name: string
  client_name: string
  is_installment: boolean
  total_amount: string
  is_paid: boolean
  paid_at: string | null
  is_delivered: boolean
  delivered_at: string | null
  is_out_for_delivery: boolean
  out_for_delivery_at: string | null
  /** `cancellation_requested` (roadmap A4) — solicitação feita via Portal autenticado, não por aqui (rota pública sem login). */
  status: 'pending_approval' | 'confirmed' | 'rejected' | 'cancellation_requested'
  expected_delivery_date: string | null
  is_cancelled: boolean
  created_at: string
  items: OrderTrackingItem[]
  installments: OrderTrackingInstallment[]
}
