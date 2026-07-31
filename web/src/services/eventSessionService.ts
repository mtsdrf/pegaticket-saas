import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { EventSession, EventSessionFilters, EventSessionPayload } from '../types/eventSession'

export function listEventSessions(eventUuid: string, filters: EventSessionFilters): Promise<PaginatedResult<EventSession>> {
  return listPaginated<EventSession>(`/events/${eventUuid}/sessions`, filters)
}

export function getEventSession(eventUuid: string, sessionUuid: string): Promise<EventSession> {
  return unwrap(apiClient.get<ApiSuccess<EventSession>>(`/events/${eventUuid}/sessions/${sessionUuid}`))
}

export function createEventSession(eventUuid: string, payload: EventSessionPayload): Promise<EventSession> {
  return unwrap(apiClient.post<ApiSuccess<EventSession>>(`/events/${eventUuid}/sessions`, payload))
}

export function updateEventSession(
  eventUuid: string,
  sessionUuid: string,
  payload: Partial<EventSessionPayload>,
): Promise<EventSession> {
  return unwrap(apiClient.put<ApiSuccess<EventSession>>(`/events/${eventUuid}/sessions/${sessionUuid}`, payload))
}

export function deleteEventSession(eventUuid: string, sessionUuid: string): Promise<void> {
  return apiClient.delete(`/events/${eventUuid}/sessions/${sessionUuid}`).then(() => undefined)
}
