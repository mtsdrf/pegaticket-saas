import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { TicketFeeRule, TicketFeeSimulationRequest, TicketFeeSimulationResult } from '../types/ticketFee'

/** Tenant-scoped, perm `ticket_types,read`. */
export function getTicketFeeRule(): Promise<TicketFeeRule> {
  return unwrap(apiClient.get<ApiSuccess<TicketFeeRule>>('/tenant-tools/ticket-pricing/rule'))
}

export function simulateTicketFee(payload: TicketFeeSimulationRequest): Promise<TicketFeeSimulationResult> {
  return unwrap(
    apiClient.post<ApiSuccess<TicketFeeSimulationResult>>('/tenant-tools/ticket-pricing/simulate', payload),
  )
}
