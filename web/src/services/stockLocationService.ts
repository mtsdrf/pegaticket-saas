import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { StockLocation, StockLocationFilters, StockLocationPayload } from '../types/stockLocation'

export function listStockLocations(filters: StockLocationFilters): Promise<PaginatedResult<StockLocation>> {
  return listPaginated<StockLocation>('/stock-locations', filters)
}

export async function getStockLocation(uuid: string): Promise<StockLocation> {
  const { items } = await listPaginated<StockLocation>('/stock-locations', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Local de estoque não encontrado.')
  return found
}

export function createStockLocation(payload: StockLocationPayload): Promise<StockLocation> {
  return unwrap(apiClient.post<ApiSuccess<StockLocation>>('/stock-locations', payload))
}

export function updateStockLocation(uuid: string, payload: Partial<StockLocationPayload>): Promise<StockLocation> {
  return unwrap(apiClient.put<ApiSuccess<StockLocation>>(`/stock-locations/${uuid}`, payload))
}

export function deleteStockLocation(uuid: string): Promise<void> {
  return apiClient.delete(`/stock-locations/${uuid}`).then(() => undefined)
}
