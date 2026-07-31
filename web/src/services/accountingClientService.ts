import { accountingApiClient } from './accountingApiClient'
import { unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { Client, ClientFilters, ClientPayload } from '../types/client'
import type { PaginatedResult, PaginationMeta } from '../types/pagination'

export async function listAccountingClients(
  tenantUuid: string,
  filters: ClientFilters,
): Promise<PaginatedResult<Client>> {
  const response = await accountingApiClient.get<ApiSuccess<Client[]>>(
    `/accounting/tenants/${tenantUuid}/clients`,
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

export function updateAccountingClientFiscal(
  tenantUuid: string,
  clientUuid: string,
  payload: Pick<ClientPayload, 'cpf_cnpj' | 'ie' | 'ie_indicator'>,
): Promise<Client> {
  return unwrap(
    accountingApiClient.put<ApiSuccess<Client>>(
      `/accounting/tenants/${tenantUuid}/clients/${clientUuid}`,
      payload,
    ),
  )
}
