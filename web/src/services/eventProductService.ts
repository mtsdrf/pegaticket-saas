import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { EventProduct, EventProductFilters, EventProductPayload } from '../types/eventProduct'

export function listEventProducts(filters: EventProductFilters): Promise<PaginatedResult<EventProduct>> {
  return listPaginated<EventProduct>('/event-products', filters)
}

export function getEventProduct(uuid: string): Promise<EventProduct> {
  return unwrap(apiClient.get<ApiSuccess<EventProduct>>(`/event-products/${uuid}`))
}

export function createEventProduct(payload: EventProductPayload): Promise<EventProduct> {
  return unwrap(apiClient.post<ApiSuccess<EventProduct>>('/event-products', payload))
}

export function updateEventProduct(uuid: string, payload: Partial<EventProductPayload>): Promise<EventProduct> {
  return unwrap(apiClient.put<ApiSuccess<EventProduct>>(`/event-products/${uuid}`, payload))
}

export function deleteEventProduct(uuid: string): Promise<void> {
  return apiClient.delete(`/event-products/${uuid}`).then(() => undefined)
}
