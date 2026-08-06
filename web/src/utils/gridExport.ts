import type { ServerGridColumn } from '../components/crud/serverGridTypes'

interface NumberFilterModel {
  type: 'equals' | 'greaterThan' | 'greaterThanOrEqual' | 'lessThan' | 'lessThanOrEqual' | 'inRange'
  filter?: number
  filterTo?: number
}

interface TextFilterModel {
  filter?: string
}

/**
 * Converte o `filterModel` cru do ag-Grid nos parâmetros exatos que o
 * backend espera (contains/min-max/bool), usando `backendField` de cada
 * coluna quando definido. Usado tanto pelo `ServerDataGrid` (paginação/
 * exportação CSV/PDF-impresso) quanto por qualquer botão de exportação
 * server-side que precise reaproveitar os filtros já aplicados na grid
 * (ex.: catálogo de produtos/diretório de clientes em PDF gerado no
 * backend) — mantido aqui, e não duplicado, por ser a única fonte da
 * tradução filtro-de-coluna → parâmetro de API.
 */
export function buildBackendFilters<T>(
  columns: ServerGridColumn<T>[],
  filterModel: Record<string, unknown>,
): Record<string, string | number | boolean | undefined> {
  const filters: Record<string, string | number | boolean | undefined> = {}

  for (const column of columns) {
    const model = filterModel[column.field]
    if (!model) continue

    const backendField = column.backendField ?? column.field
    const filterType = column.filterType ?? 'text'

    if (filterType === 'number') {
      const { type, filter, filterTo } = model as NumberFilterModel
      if (type === 'equals') {
        filters[`${backendField}_min`] = filter
        filters[`${backendField}_max`] = filter
      } else if (type === 'greaterThan' || type === 'greaterThanOrEqual') {
        filters[`${backendField}_min`] = filter
      } else if (type === 'lessThan' || type === 'lessThanOrEqual') {
        filters[`${backendField}_max`] = filter
      } else if (type === 'inRange') {
        filters[`${backendField}_min`] = filter
        filters[`${backendField}_max`] = filterTo
      }
    } else if (filterType === 'boolean') {
      const maybeText = (model as TextFilterModel).filter?.trim().toLowerCase()
      if (!maybeText) continue
      if (['sim', 's', 'true', '1', 'ativo', 'ativa', 'pago', 'liberado'].includes(maybeText)) {
        filters[backendField] = true
      } else if (['nao', 'não', 'n', 'false', '0', 'inativo', 'inativa', 'pendente'].includes(maybeText)) {
        filters[backendField] = false
      }
    } else {
      const { filter } = model as TextFilterModel
      if (filter) filters[backendField] = column.filterTextToBackend ? column.filterTextToBackend(filter) : filter
    }
  }

  return filters
}

/** Colunas exportáveis (exclui ação/imagem marcadas com `exportable: false`). */
export function getExportableColumns<T>(columns: ServerGridColumn<T>[]): ServerGridColumn<T>[] {
  return columns.filter((column) => column.exportable !== false)
}

function formatExportValue(value: string | number | boolean | null | undefined): string {
  if (value === null || value === undefined) return ''
  if (typeof value === 'boolean') return value ? 'Sim' : 'Não'
  return String(value)
}

function getExportCellValue<T>(column: ServerGridColumn<T>, row: T): string {
  const raw = column.exportValue ? column.exportValue(row) : (row as Record<string, unknown>)[column.field]
  return formatExportValue(raw as string | number | boolean | null | undefined)
}

/** Monta cabeçalho + linhas (já formatadas em texto) a partir das colunas exportáveis, reutilizável por CSV e PDF. */
export function buildExportTable<T>(columns: ServerGridColumn<T>[], rows: T[]): { headers: string[]; body: string[][] } {
  const exportColumns = getExportableColumns(columns)
  const headers = exportColumns.map((column) => column.headerName)
  const body = rows.map((row) => exportColumns.map((column) => getExportCellValue(column, row)))
  return { headers, body }
}

/** Aspas quando o valor tem vírgula, quebra de linha ou aspas — regra padrão CSV (RFC 4197). */
function escapeCsvCell(value: string): string {
  if (/[",\n\r]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`
  }
  return value
}

/** BOM UTF-8 no início: sem ele, Excel abre acentuação como caracteres corrompidos. */
export function buildCsvContent(headers: string[], rows: string[][]): string {
  const lines = [headers, ...rows].map((line) => line.map(escapeCsvCell).join(','))
  return '﻿' + lines.join('\r\n')
}

export function downloadTextFile(content: string, filename: string, mimeType: string): void {
  const blob = new Blob([content], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}
