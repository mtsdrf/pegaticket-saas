export interface ProductTypeRef {
  uuid: string
  name: string
  product_category: { uuid: string; name: string } | null
}

export interface ProductOption {
  uuid: string
  name: string
  description: string | null
  price: number
  sort_order: number
  is_available: boolean
}

export interface ProductOptionGroup {
  uuid: string
  name: string
  description: string | null
  min_select: number
  max_select: number
  sort_order: number
  is_active: boolean
  options: ProductOption[]
}

export interface Product {
  uuid: string
  name: string
  sku: string | null
  barcode: string | null
  brand: string | null
  ncm: string | null
  cest: string | null
  origin: string | null
  default_cfop: string | null
  csosn_cst: string | null
  unit: string | null
  price: number
  description: string | null
  image_url: string | null
  is_available: boolean
  stock_quantity: number
  surcharge_rate: number | null
  is_lot_controlled: boolean
  is_expiry_controlled: boolean
  is_serial_controlled: boolean
  min_stock: number | null
  max_stock: number | null
  reorder_point: number | null
  reorder_qty: number | null
  product_type: ProductTypeRef | null
  option_groups?: ProductOptionGroup[]
  created_at: string
  /** Só presente se o usuário tiver a permissão `stock.view_costs` — a chave some, não vem `null`. */
  last_purchase_cost?: number | null
}

export interface ProductPayload {
  name: string
  price: number
  description?: string
  is_available?: boolean
  surcharge_rate?: number | null
  product_type_uuid: string
  sku?: string
  barcode?: string
  brand?: string
  ncm?: string
  cest?: string
  origin?: string
  default_cfop?: string
  csosn_cst?: string
  unit?: string
  is_lot_controlled?: boolean
  is_expiry_controlled?: boolean
  is_serial_controlled?: boolean
  min_stock?: number | null
  max_stock?: number | null
  reorder_point?: number | null
  reorder_qty?: number | null
  last_purchase_cost?: number | null
  option_groups?: Array<{
    uuid?: string
    name: string
    description?: string
    min_select: number
    max_select: number
    sort_order?: number
    is_active?: boolean
    options: Array<{
      uuid?: string
      name: string
      description?: string
      price: number
      sort_order?: number
      is_available?: boolean
    }>
  }>
}

export interface ProductFilters {
  /** Busca genérica OR-contains (name, product_type_name, product_category_name) — usada pela caixa "Buscar em todos os campos". */
  q?: string
  name?: string
  /** Match EXATO de código de barras (usado pelo PDV com leitor de código). */
  barcode?: string
  product_type_uuid?: string
  product_category_uuid?: string
  product_type_name?: string
  product_category_name?: string
  price_min?: number
  price_max?: number
  is_available?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}
