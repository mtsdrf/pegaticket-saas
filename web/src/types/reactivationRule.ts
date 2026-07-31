export type ReactivationCouponType = 'percentage' | 'fixed'

/** Espelha `ReactivationRuleResource` (backend) — singleton por tenant, roadmap A5 item 18. */
export interface ReactivationRule {
  uuid: string
  days_without_order: number
  coupon_type: ReactivationCouponType
  coupon_value: number
  coupon_validity_days: number
  is_active: boolean
}

export interface UpdateReactivationRulePayload {
  days_without_order: number
  coupon_type: ReactivationCouponType
  coupon_value: number
  coupon_validity_days: number
  is_active: boolean
}
