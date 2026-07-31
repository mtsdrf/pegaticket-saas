import type { PaymentMethod } from '../constants/paymentMethods'

export type CouponType = 'percentage' | 'fixed' | 'free_shipping'

/** `GET/POST/PUT /coupons` (`CouponResource`) — cupom vale sobre o carrinho todo, sem elegibilidade por item. */
export interface Coupon {
  uuid: string
  code: string
  type: CouponType
  /** % (0-100) ou R$ conforme `type`; sempre `null` quando `type === 'free_shipping'`. */
  value: number | null
  minimum_order_value: number | null
  max_uses_total: number | null
  max_uses_per_customer: number | null
  starts_at: string | null
  expires_at: string | null
  is_active: boolean
  /** `null`/`[]` = cupom vale para qualquer meio de pagamento (comportamento padrão). */
  allowed_payment_methods: PaymentMethod[] | null
}

export interface CouponPayload {
  code: string
  type: CouponType
  value?: number | null
  minimum_order_value?: number | null
  max_uses_total?: number | null
  max_uses_per_customer?: number | null
  starts_at?: string | null
  expires_at?: string | null
  is_active?: boolean
  allowed_payment_methods?: PaymentMethod[] | null
}
