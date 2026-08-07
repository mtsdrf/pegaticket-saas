import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { AddGuestListEntryPayload, CreateGuestListPayload, GuestList, GuestListEntry, UpdateGuestListPayload } from '../types/guestList'
import type { PaginatedResult } from '../types/pagination'

export function listGuestLists(filters: Record<string, unknown> = {}): Promise<PaginatedResult<GuestList>> {
  return listPaginated<GuestList>('/guest-lists', filters)
}

export function getGuestList(uuid: string): Promise<GuestList> {
  return unwrap(apiClient.get<ApiSuccess<GuestList>>(`/guest-lists/${uuid}`))
}

export function createGuestList(payload: CreateGuestListPayload): Promise<GuestList> {
  return unwrap(apiClient.post<ApiSuccess<GuestList>>('/guest-lists', payload))
}

export function updateGuestList(uuid: string, payload: UpdateGuestListPayload): Promise<GuestList> {
  return unwrap(apiClient.put<ApiSuccess<GuestList>>(`/guest-lists/${uuid}`, payload))
}

export function deleteGuestList(uuid: string): Promise<void> {
  return apiClient.delete(`/guest-lists/${uuid}`).then(() => undefined)
}

export function addGuestListEntry(uuid: string, payload: AddGuestListEntryPayload): Promise<GuestListEntry> {
  return unwrap(apiClient.post<ApiSuccess<GuestListEntry>>(`/guest-lists/${uuid}/entries`, payload))
}
