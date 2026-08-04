import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { EventGate, EventGateFilters, EventGatePayload } from '../types/eventGate'
import type { PaginatedResult } from '../types/pagination'

export function listEventGates(eventUuid: string, filters: EventGateFilters = {}): Promise<PaginatedResult<EventGate>> {
  return listPaginated<EventGate>(`/events/${eventUuid}/gates`, { per_page: 100, ...filters })
}

export function getEventGate(eventUuid: string, gateUuid: string): Promise<EventGate> {
  return unwrap(apiClient.get<ApiSuccess<EventGate>>(`/events/${eventUuid}/gates/${gateUuid}`))
}

export function createEventGate(eventUuid: string, payload: EventGatePayload): Promise<EventGate> {
  return unwrap(apiClient.post<ApiSuccess<EventGate>>(`/events/${eventUuid}/gates`, payload))
}

export function updateEventGate(eventUuid: string, gateUuid: string, payload: Partial<EventGatePayload>): Promise<EventGate> {
  return unwrap(apiClient.put<ApiSuccess<EventGate>>(`/events/${eventUuid}/gates/${gateUuid}`, payload))
}

export function deleteEventGate(eventUuid: string, gateUuid: string): Promise<void> {
  return apiClient.delete(`/events/${eventUuid}/gates/${gateUuid}`).then(() => undefined)
}
