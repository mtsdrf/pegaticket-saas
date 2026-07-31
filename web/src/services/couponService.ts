import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { Coupon, CouponPayload } from '../types/coupon'

export function listCoupons(): Promise<Coupon[]> {
  return unwrap(apiClient.get<ApiSuccess<Coupon[]>>('/coupons'))
}

export function createCoupon(payload: CouponPayload): Promise<Coupon> {
  return unwrap(apiClient.post<ApiSuccess<Coupon>>('/coupons', payload))
}

export function updateCoupon(uuid: string, payload: CouponPayload): Promise<Coupon> {
  return unwrap(apiClient.put<ApiSuccess<Coupon>>(`/coupons/${uuid}`, payload))
}

export function deleteCoupon(uuid: string): Promise<void> {
  return apiClient.delete(`/coupons/${uuid}`).then(() => undefined)
}
