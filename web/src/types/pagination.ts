export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface PaginatedResult<T> {
  items: T[]
  pagination: PaginationMeta
}
