import { accountingApiClient } from './accountingApiClient'
import { unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult, PaginationMeta } from '../types/pagination'
import type { Product } from '../types/product'

export interface AccountingProductFilters {
  q?: string
  page?: number
  per_page?: number
}

export interface AccountingProductFiscalPayload {
  ncm?: string
  cest?: string
  origin?: string
  default_cfop?: string
  csosn_cst?: string
}

export async function listAccountingProducts(
  tenantUuid: string,
  filters: AccountingProductFilters,
): Promise<PaginatedResult<Product>> {
  const response = await accountingApiClient.get<ApiSuccess<Product[]>>(
    `/accounting/tenants/${tenantUuid}/products`,
    { params: filters },
  )
  const meta = response.data.meta as { pagination?: PaginationMeta }

  return {
    items: response.data.data,
    pagination: meta.pagination ?? {
      current_page: 1,
      per_page: filters.per_page ?? response.data.data.length,
      total: response.data.data.length,
      last_page: 1,
    },
  }
}

export function updateAccountingProductFiscal(
  tenantUuid: string,
  productUuid: string,
  payload: AccountingProductFiscalPayload,
): Promise<Product> {
  return unwrap(
    accountingApiClient.put<ApiSuccess<Product>>(
      `/accounting/tenants/${tenantUuid}/products/${productUuid}`,
      payload,
    ),
  )
}
