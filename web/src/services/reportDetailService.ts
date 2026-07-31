import { apiClient } from './apiClient'
import { unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult, PaginationMeta } from '../types/pagination'
import { extractFilenameFromContentDisposition, triggerBlobDownload } from '../utils/fileDownload'
import type {
  ClientReportItem,
  ClientReportFilters,
  OrderReportFilters,
  ReceivableReportFilters,
  ReceivableReportItem,
  ReceivableSummary,
  ReceivableInteraction,
  CreateReceivableInteractionPayload,
  OrderReportSummary,
} from '../types/reportDetail'
import type { Order } from '../types/order'

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

export function listOrderReports(filters: OrderReportFilters): Promise<PaginatedResult<Order>> {
  return listPaginatedMeta<Order>('/reports/orders', filters)
}

export async function getOrdersSummary(filters: OrderReportFilters): Promise<OrderReportSummary> {
  const response = await apiClient.get<ApiSuccess<OrderReportSummary>>('/reports/orders/summary', { params: filters })
  return response.data.data
}

export function listClientReports(filters: ClientReportFilters): Promise<PaginatedResult<ClientReportItem>> {
  return listPaginatedMeta<ClientReportItem>('/reports/clients', filters)
}

export function listReceivableReports(filters: ReceivableReportFilters): Promise<PaginatedResult<ReceivableReportItem>> {
  return listPaginatedMeta<ReceivableReportItem>('/reports/receivables', filters)
}

export async function getReceivableSummary(): Promise<ReceivableSummary> {
  const response = await apiClient.get<ApiSuccess<ReceivableSummary>>('/reports/receivables/summary')
  return response.data.data
}

export function listReceivableInteractions(orderUuid: string, installmentUuid?: string): Promise<ReceivableInteraction[]> {
  return unwrap(apiClient.get<ApiSuccess<ReceivableInteraction[]>>(`/reports/receivables/${orderUuid}/interactions`, {
    params: installmentUuid ? { installment_uuid: installmentUuid } : undefined,
  }))
}

export function createReceivableInteraction(orderUuid: string, payload: CreateReceivableInteractionPayload): Promise<ReceivableInteraction> {
  return unwrap(apiClient.post<ApiSuccess<ReceivableInteraction>>(`/reports/receivables/${orderUuid}/interactions`, payload))
}

async function exportPdf(url: string, payload: object, fallbackFilename: string): Promise<void> {
  const response = await apiClient.post(url, payload, { responseType: 'blob' })
  const filename = extractFilenameFromContentDisposition(response.headers['content-disposition'], fallbackFilename)
  triggerBlobDownload(response.data, filename)
}

export function exportOrderReportsPdf(filters: OrderReportFilters): Promise<void> {
  return exportPdf('/reports/orders/pdf', filters, 'relatorio-pedidos.pdf')
}

export function exportClientReportsPdf(filters: ClientReportFilters): Promise<void> {
  return exportPdf('/reports/clients/pdf', filters, 'relatorio-clientes.pdf')
}
