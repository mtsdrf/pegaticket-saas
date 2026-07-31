import type { ReactNode } from 'react'

export type ServerGridFilterType = 'text' | 'number' | 'boolean' | 'none'

export interface ServerGridColumn<T> {
  /** Chave usada pelo ag-Grid (colId) — precisa ser única na tabela, não precisa mapear 1:1 pra um campo real do dado. */
  field: string
  headerName: string
  /** Largura fixa em px — sem valor, a coluna divide o espaço restante (`flex: 1`). */
  width?: number
  /** Default `true` (`false` só faz sentido pra coluna de ação/imagem, sem valor pra ordenar). */
  sortable?: boolean
  /** Default `'text'`. `'none'` desliga filtro (mesmo caso de uso de `sortable: false`). */
  filterType?: ServerGridFilterType
  cellRenderer?: (row: T) => ReactNode
  /** Nome do parâmetro que o backend espera, quando difere de `field` (ex.: coluna exibe `product_type.name`, backend filtra/ordena por `product_type_name`). */
  backendField?: string
  /** Default `true`. `false` para coluna de ação/imagem sem valor exportável (CSV/PDF). */
  exportable?: boolean
  /**
   * Valor primitivo usado na exportação (CSV/PDF) quando o dado não está
   * direto em `row[field]` (ex.: campo aninhado, ou o texto já renderizado
   * faz mais sentido que o valor cru, ex. moeda formatada). Sem isso, cai no
   * fallback `row[field]`.
   */
  exportValue?: (row: T) => string | number | boolean | null | undefined
}

export interface ServerGridFetchParams {
  page: number
  perPage: number
  sortBy?: string
  sortDir?: 'asc' | 'desc'
  /** Filtros das colunas do grid, já convertidos pros nomes de parâmetro do backend (contains/min/max/bool). */
  filters: Record<string, string | number | boolean | undefined>
}

export interface ServerGridFetchResult<T> {
  rows: T[]
  total: number
}
