import type { PaginationMeta } from './pagination'

export interface StockLocation {
  uuid: string
  name: string
  type: string | null
  address: string | null
  is_default: boolean
  is_active: boolean
  created_at: string
}

export interface StockLocationPayload {
  name: string
  type?: string | null
  address?: string | null
  is_default?: boolean
  is_active?: boolean
}

export interface StockLocationFilters {
  name?: string
  type?: string
  address?: string
  is_default?: boolean
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface StockLocationListResult {
  items: StockLocation[]
  pagination: PaginationMeta
}
