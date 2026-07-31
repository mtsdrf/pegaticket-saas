import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Venue, VenueFilters, VenuePayload } from '../types/venue'

export function listVenues(filters: VenueFilters): Promise<PaginatedResult<Venue>> {
  return listPaginated<Venue>('/venues', filters)
}

export function getVenue(uuid: string): Promise<Venue> {
  return unwrap(apiClient.get<ApiSuccess<Venue>>(`/venues/${uuid}`))
}

function buildFormData(payload: Record<string, unknown>, imageFile?: File | null): FormData {
  const formData = new FormData()

  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null) return
    formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  if (imageFile) formData.append('background_image', imageFile)

  return formData
}

export function createVenue(payload: VenuePayload, imageFile?: File | null): Promise<Venue> {
  const formData = buildFormData(payload as unknown as Record<string, unknown>, imageFile)
  return unwrap(apiClient.post<ApiSuccess<Venue>>('/venues', formData))
}

export function updateVenue(uuid: string, payload: Partial<VenuePayload>, imageFile?: File | null): Promise<Venue> {
  if (!imageFile) {
    return unwrap(apiClient.put<ApiSuccess<Venue>>(`/venues/${uuid}`, payload))
  }

  const formData = buildFormData(payload as unknown as Record<string, unknown>, imageFile)
  formData.append('_method', 'PUT')
  return unwrap(apiClient.post<ApiSuccess<Venue>>(`/venues/${uuid}`, formData))
}

export function deleteVenue(uuid: string): Promise<void> {
  return apiClient.delete(`/venues/${uuid}`).then(() => undefined)
}

export function publishVenueMap(uuid: string): Promise<void> {
  return apiClient.post(`/venues/${uuid}/publish`).then(() => undefined)
}
