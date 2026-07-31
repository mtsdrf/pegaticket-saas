/** Espelha `ProductImportService::toPreviewRow()` (backend). */
export interface ProductImportPreviewRow {
  line: number
  status: 'valid' | 'error'
  errors: string[]
  nome: string
  categoria: string | null
  tipo: string
  preco: number | null
  descricao: string | null
  sku: string | null
  disponivel: boolean
  category_will_be_created: boolean
  type_will_be_created: boolean
}

export interface ProductImportPreviewResult {
  total: number
  valid_count: number
  error_count: number
  max_rows: number
  rows: ProductImportPreviewRow[]
}

/** Linha reenviada para `/products/import/commit` — mesmo shape aceito por `ProductImportCommitRequest`. */
export interface ProductImportCommitRow {
  nome: string
  categoria: string | null
  tipo: string
  preco: number | null
  descricao: string | null
  sku: string | null
  disponivel: boolean
}

export interface ProductImportCommitResultRow {
  line: number
  status: 'created' | 'skipped'
  errors: string[]
  nome: string
  product_uuid?: string
}

export interface ProductImportCommitResult {
  total: number
  created_count: number
  skipped_count: number
  categories_created_count: number
  types_created_count: number
  rows: ProductImportCommitResultRow[]
}
