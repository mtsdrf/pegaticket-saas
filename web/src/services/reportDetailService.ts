import { apiClient } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult, PaginationMeta } from '../types/pagination'
import { extractFilenameFromContentDisposition, triggerBlobDownload } from '../utils/fileDownload'
import type {
  SaleReportFilters,
  SaleReportSummary,
} from '../types/reportDetail'
import type { Sale } from '../types/sale'

async function listPaginatedMeta<T>(url: string, params: object): Promise<PaginatedResult<T>> {
  const response = await apiClient.get<ApiSuccess<T[]>>(url, { params })
  const meta = response.data.meta as { pagination?: PaginationMeta }
  const perPage = (params as { per_page?: number }).per_page

  return {
    items: response.data.data,
    pagination: meta.pagination ?? {
      current_page: 1,
      per_page: perPage ?? response.data.data.length,
      total: response.data.data.length,
      last_page: 1,
    },
  }
}

export function listOrderReports(filters: SaleReportFilters): Promise<PaginatedResult<Sale>> {
  return listPaginatedMeta<Sale>('/reports/sales', filters)
}

export async function getOrdersSummary(filters: SaleReportFilters): Promise<SaleReportSummary> {
  const response = await apiClient.get<ApiSuccess<SaleReportSummary>>('/reports/sales/summary', { params: filters })
  return response.data.data
}

async function exportPdf(url: string, payload: object, fallbackFilename: string): Promise<void> {
  const response = await apiClient.post(url, payload, { responseType: 'blob' })
  const filename = extractFilenameFromContentDisposition(response.headers['content-disposition'], fallbackFilename)
  triggerBlobDownload(response.data, filename)
}

export function exportOrderReportsPdf(filters: SaleReportFilters): Promise<void> {
  return exportPdf('/reports/sales/pdf', filters, 'relatorio-vendas.pdf')
}

/** Exportação XLSX do relatório de vendas (roadmap A2) — MESMA base filtrada do PDF acima. */
export async function exportOrderReportsXlsx(filters: SaleReportFilters): Promise<void> {
  const response = await apiClient.post('/reports/sales/xlsx', filters, { responseType: 'blob' })
  const filename = extractFilenameFromContentDisposition(response.headers['content-disposition'], 'relatorio-vendas.xlsx')
  triggerBlobDownload(response.data, filename)
}
