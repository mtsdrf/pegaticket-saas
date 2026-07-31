export interface EventCategory {
  uuid: string
  name: string
  priority: number | null
  is_active: boolean
  created_at: string
}

export interface EventCategoryPayload {
  name: string
  priority?: number | null
  is_active?: boolean
}

export interface EventCategoryFilters {
  /** Busca genérica OR-contains (name) — usada pela caixa "Buscar em todos os campos". */
  q?: string
  name?: string
  priority_min?: number
  priority_max?: number
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}
