import type { PaginationMeta } from './pagination'

export interface ClientCategory {
  uuid: string
  name: string
  is_active: boolean
  created_at: string
}

export interface ClientCategoryPayload {
  name: string
  is_active?: boolean
}

export interface ClientCategoryFilters {
  q?: string
  name?: string
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface ClientCategoryListResult {
  items: ClientCategory[]
  pagination: PaginationMeta
}
