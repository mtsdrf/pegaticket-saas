import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { SaleRefund, SaleRefundPayload } from '../types/saleRefund'
import { extractFilenameFromContentDisposition, triggerBlobDownload } from '../utils/fileDownload'

/** Estornos já registrados de uma venda — spec 5.14 (estorno externo, PagBank fora do sistema). */
export function listSaleRefunds(saleUuid: string): Promise<SaleRefund[]> {
  return unwrap(apiClient.get<ApiSuccess<SaleRefund[]>>(`/sales/${saleUuid}/refunds`))
}

/** Multipart por causa do upload opcional de comprovante (`receipt`). */
export function createSaleRefund(saleUuid: string, payload: SaleRefundPayload): Promise<SaleRefund> {
  const formData = new FormData()
  formData.append('type', payload.type)
  formData.append('amount', String(payload.amount))
  formData.append('reason', payload.reason)
  formData.append('refunded_at', payload.refunded_at)
  if (payload.external_reference) formData.append('external_reference', payload.external_reference)
  if (payload.notes) formData.append('notes', payload.notes)
  if (payload.release_seats !== undefined) formData.append('release_seats', payload.release_seats ? '1' : '0')
  if (payload.receipt) formData.append('receipt', payload.receipt)
  if (payload.ticket_uuids) {
    payload.ticket_uuids.forEach((uuid, index) => formData.append(`ticket_uuids[${index}]`, uuid))
  }

  return unwrap(apiClient.post<ApiSuccess<SaleRefund>>(`/sales/${saleUuid}/refunds`, formData))
}

/** Comprovante do estorno — disco privado, só baixável autenticado/tenant-scoped (mesmo padrão de `tenantProfileService.exportTenantData`). */
export async function downloadSaleRefundReceipt(saleUuid: string, refundUuid: string): Promise<void> {
  const response = await apiClient.get(`/sales/${saleUuid}/refunds/${refundUuid}/receipt`, { responseType: 'blob' })
  const filename = extractFilenameFromContentDisposition(response.headers['content-disposition'], 'comprovante-estorno')
  triggerBlobDownload(response.data, filename)
}
