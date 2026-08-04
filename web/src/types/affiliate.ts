export interface Affiliate {
  uuid: string
  name: string
  email: string | null
  tracking_code: string
  commission_percentage: number | null
  status: 'active' | 'inactive'
  commissions_total_amount: number
  created_at: string
}

export interface CreateAffiliatePayload {
  name: string
  email?: string
  tracking_code: string
  commission_percentage?: number
  status?: 'active' | 'inactive'
}

export type UpdateAffiliatePayload = CreateAffiliatePayload

export interface AffiliateCommission {
  uuid: string
  sale_uuid: string | null
  sale_amount: number
  percentage_applied: number
  amount: number
  status: 'pending' | 'paid'
  created_at: string
}
