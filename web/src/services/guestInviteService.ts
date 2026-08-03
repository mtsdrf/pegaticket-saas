import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { GuestInvite, RedeemGuestInvitePayload, RedeemGuestInviteResult } from '../types/guestList'

export function getGuestInvite(token: string): Promise<GuestInvite> {
  return unwrap(apiClient.get<ApiSuccess<GuestInvite>>(`/convites/${token}`))
}

export function redeemGuestInvite(token: string, payload: RedeemGuestInvitePayload): Promise<RedeemGuestInviteResult> {
  return unwrap(apiClient.post<ApiSuccess<RedeemGuestInviteResult>>(`/convites/${token}/resgatar`, payload))
}
