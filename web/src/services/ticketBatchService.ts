import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { TicketBatch, TicketBatchFilters, TicketBatchPayload } from '../types/ticketBatch'

export function listTicketBatches(ticketTypeUuid: string, filters: TicketBatchFilters): Promise<PaginatedResult<TicketBatch>> {
  return listPaginated<TicketBatch>(`/ticket-types/${ticketTypeUuid}/batches`, filters)
}

export function getTicketBatch(ticketTypeUuid: string, batchUuid: string): Promise<TicketBatch> {
  return unwrap(apiClient.get<ApiSuccess<TicketBatch>>(`/ticket-types/${ticketTypeUuid}/batches/${batchUuid}`))
}

export function createTicketBatch(ticketTypeUuid: string, payload: TicketBatchPayload): Promise<TicketBatch> {
  return unwrap(apiClient.post<ApiSuccess<TicketBatch>>(`/ticket-types/${ticketTypeUuid}/batches`, payload))
}

export function updateTicketBatch(
  ticketTypeUuid: string,
  batchUuid: string,
  payload: Partial<TicketBatchPayload>,
): Promise<TicketBatch> {
  return unwrap(apiClient.put<ApiSuccess<TicketBatch>>(`/ticket-types/${ticketTypeUuid}/batches/${batchUuid}`, payload))
}

export function deleteTicketBatch(ticketTypeUuid: string, batchUuid: string): Promise<void> {
  return apiClient.delete(`/ticket-types/${ticketTypeUuid}/batches/${batchUuid}`).then(() => undefined)
}
