import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Seat, SeatFilters, SeatPayload } from '../types/venue'

export function listSeats(venueUuid: string, filters: SeatFilters): Promise<PaginatedResult<Seat>> {
  return listPaginated<Seat>(`/venues/${venueUuid}/seats`, filters)
}

export function getSeat(venueUuid: string, seatUuid: string): Promise<Seat> {
  return unwrap(apiClient.get<ApiSuccess<Seat>>(`/venues/${venueUuid}/seats/${seatUuid}`))
}

export function createSeat(venueUuid: string, payload: SeatPayload): Promise<Seat> {
  return unwrap(apiClient.post<ApiSuccess<Seat>>(`/venues/${venueUuid}/seats`, payload))
}

export function updateSeat(venueUuid: string, seatUuid: string, payload: Partial<SeatPayload>): Promise<Seat> {
  return unwrap(apiClient.put<ApiSuccess<Seat>>(`/venues/${venueUuid}/seats/${seatUuid}`, payload))
}

export function deleteSeat(venueUuid: string, seatUuid: string): Promise<void> {
  return apiClient.delete(`/venues/${venueUuid}/seats/${seatUuid}`).then(() => undefined)
}
