import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Event, EventFilters, EventPayload } from '../types/event'

export function listEvents(filters: EventFilters): Promise<PaginatedResult<Event>> {
  return listPaginated<Event>('/events', filters)
}

export function getEvent(uuid: string): Promise<Event> {
  return unwrap(apiClient.get<ApiSuccess<Event>>(`/events/${uuid}`))
}

/** `undefined`/`null` ficam de fora do FormData; booleano vira "1"/"0" (regra `boolean` do Laravel aceita ambos). */
function buildFormData(payload: Record<string, unknown>, imageFile?: File | null): FormData {
  const formData = new FormData()

  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null) return
    formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  if (imageFile) formData.append('cover_image', imageFile)

  return formData
}

export function createEvent(payload: EventPayload, imageFile?: File | null): Promise<Event> {
  const formData = buildFormData(payload as unknown as Record<string, unknown>, imageFile)
  return unwrap(apiClient.post<ApiSuccess<Event>>('/events', formData))
}

/**
 * Sem imagem nova, envia PUT normal (JSON). Com imagem nova, o Laravel
 * precisa de multipart — PHP não faz parse de multipart em PUT nativo,
 * então usa o spoofing padrão do framework: POST + campo `_method=PUT`.
 */
export function updateEvent(
  uuid: string,
  payload: Partial<EventPayload>,
  imageFile?: File | null,
): Promise<Event> {
  if (!imageFile) {
    return unwrap(apiClient.put<ApiSuccess<Event>>(`/events/${uuid}`, payload))
  }

  const formData = buildFormData(payload as unknown as Record<string, unknown>, imageFile)
  formData.append('_method', 'PUT')
  return unwrap(apiClient.post<ApiSuccess<Event>>(`/events/${uuid}`, formData))
}

export function deleteEvent(uuid: string): Promise<void> {
  return apiClient.delete(`/events/${uuid}`).then(() => undefined)
}
