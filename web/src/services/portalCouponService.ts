import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type { PortalCouponRedemption } from '../types/portal'

/** "Meus vouchers" — histórico read-only de cupons já usados, cross-tenant, mais recente primeiro (ordem já vem do backend). */
export function listPortalCouponRedemptions(): Promise<PortalCouponRedemption[]> {
  return unwrap(portalApiClient.get<ApiSuccess<PortalCouponRedemption[]>>('/portal/coupon-redemptions'))
}
