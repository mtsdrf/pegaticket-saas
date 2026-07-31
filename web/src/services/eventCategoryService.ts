import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { EventCategory, EventCategoryFilters, EventCategoryPayload } from '../types/eventCategory'

export function listEventCategories(
  filters: EventCategoryFilters,
): Promise<PaginatedResult<EventCategory>> {
  return listPaginated<EventCategory>('/event-categories', filters)
}

/**
 * A API não tem `GET /event-categories/{uuid}` (só index/store/update/
 * destroy) — pra editar, busca a lista (as categorias de um tenant são
 * uma lista curta e enumerável) e resolve o registro pelo uuid no client.
 */
export async function getEventCategory(uuid: string): Promise<EventCategory> {
  const { items } = await listPaginated<EventCategory>('/event-categories', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Categoria de evento não encontrada.')
  return found
}

export function createEventCategory(payload: EventCategoryPayload): Promise<EventCategory> {
  return unwrap(apiClient.post<ApiSuccess<EventCategory>>('/event-categories', payload))
}

export function updateEventCategory(
  uuid: string,
  payload: Partial<EventCategoryPayload>,
): Promise<EventCategory> {
  return unwrap(apiClient.put<ApiSuccess<EventCategory>>(`/event-categories/${uuid}`, payload))
}

export function deleteEventCategory(uuid: string): Promise<void> {
  return apiClient.delete(`/event-categories/${uuid}`).then(() => undefined)
}
