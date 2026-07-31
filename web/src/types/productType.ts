export interface ProductType {
  uuid: string
  name: string
  priority: number | null
  is_active: boolean
  product_category_uuid: string
  product_category_name: string
  created_at: string
}

export interface ProductTypePayload {
  name: string
  priority?: number | null
  is_active?: boolean
  product_category_uuid: string
}

export interface ProductTypeFilters {
  /** Busca genérica OR-contains (name, product_category_name) — usada pela caixa "Buscar em todos os campos". */
  q?: string
  name?: string
  product_category_name?: string
  priority_min?: number
  priority_max?: number
  is_active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}
