import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { OrderFiscalDocumentDetail, OrderFiscalDocumentPreparationResult, OrderFiscalPreview } from '../types/orderFiscalPreview'
import { extractFilenameFromContentDisposition, triggerBlobDownload } from '../utils/fileDownload'

export function getOrderFiscalPreview(orderUuid: string): Promise<OrderFiscalPreview> {
  return unwrap(apiClient.get<ApiSuccess<OrderFiscalPreview>>(`/orders/${orderUuid}/fiscal-preview`))
}

export function prepareOrderFiscalDocument(orderUuid: string): Promise<OrderFiscalDocumentPreparationResult> {
  return unwrap(apiClient.post<ApiSuccess<OrderFiscalDocumentPreparationResult>>(`/orders/${orderUuid}/fiscal-document`))
}

export function submitOrderFiscalDocument(orderUuid: string): Promise<OrderFiscalDocumentPreparationResult> {
  return unwrap(apiClient.post<ApiSuccess<OrderFiscalDocumentPreparationResult>>(`/orders/${orderUuid}/fiscal-document/submit`))
}

export function syncOrderFiscalDocumentStatus(orderUuid: string): Promise<OrderFiscalDocumentPreparationResult> {
  return unwrap(apiClient.post<ApiSuccess<OrderFiscalDocumentPreparationResult>>(`/orders/${orderUuid}/fiscal-document/sync-status`))
}

export function getOrderFiscalDocument(orderUuid: string): Promise<OrderFiscalDocumentDetail> {
  return unwrap(apiClient.get<ApiSuccess<OrderFiscalDocumentDetail>>(`/orders/${orderUuid}/fiscal-document`))
}

export function cancelOrderFiscalDocument(orderUuid: string): Promise<OrderFiscalDocumentPreparationResult> {
  return unwrap(apiClient.post<ApiSuccess<OrderFiscalDocumentPreparationResult>>(`/orders/${orderUuid}/fiscal-document/cancel`))
}

export async function downloadOrderFiscalDocumentXmlPreview(orderUuid: string): Promise<void> {
  const response = await apiClient.get(`/orders/${orderUuid}/fiscal-document/xml-preview`, {
    responseType: 'blob',
  })

  const filename = extractFilenameFromContentDisposition(
    response.headers['content-disposition'],
    `pedido-${orderUuid}-rascunho-fiscal.xml`,
  )

  triggerBlobDownload(response.data as Blob, filename)
}
