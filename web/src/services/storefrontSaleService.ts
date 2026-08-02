import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { Sale, SaleFilters } from '../types/sale'

/**
 * Tela dedicada de gestão de vendas online (`/vendas-online`) — mesmo
 * contrato de `saleService.ts`, mas contra `/storefront-sales/...`
 * (permissão `storefront-sales,*`, independente de `sales,*`). O
 * backend já força `origin=storefront` no servidor — nenhum filtro de
 * origin precisa (ou pode) ser enviado daqui.
 */
export function listStorefrontSales(filters: SaleFilters): Promise<PaginatedResult<Sale>> {
  return listPaginated<Sale>('/storefront-sales', filters)
}

/** Detalhe completo de uma venda do canal online (itens/telefone/endereço/cupom). */
export function getStorefrontSale(uuid: string): Promise<Sale> {
  return unwrap(apiClient.get<ApiSuccess<Sale>>(`/storefront-sales/${uuid}`))
}

/** Resposta de `POST /storefront-sales/{uuid}/prep-link` — token temporário (expira) pro QR de preparo sem login. */
export interface PrepLinkResult {
  token: string
  expires_at: string
}

export function generatePrepLink(uuid: string): Promise<PrepLinkResult> {
  return unwrap(apiClient.post<ApiSuccess<PrepLinkResult>>(`/storefront-sales/${uuid}/prep-link`))
}

export function approveStorefrontSale(uuid: string): Promise<Sale> {
  return unwrap(apiClient.post<ApiSuccess<Sale>>(`/storefront-sales/${uuid}/approve`))
}

/** `reason` opcional — vira `cancellation_reason` na venda recusada. */
export function rejectStorefrontSale(uuid: string, reason?: string): Promise<Sale> {
  return unwrap(apiClient.post<ApiSuccess<Sale>>(`/storefront-sales/${uuid}/reject`, reason ? { reason } : undefined))
}

export function cancelStorefrontSale(uuid: string, cancellation_reason: string): Promise<Sale> {
  return unwrap(apiClient.patch<ApiSuccess<Sale>>(`/storefront-sales/${uuid}/cancel`, { cancellation_reason }))
}

