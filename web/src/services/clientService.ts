import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { Client, ClientFilters, ClientListResult, ClientPayload, PaginationMeta } from '../types/client'

/**
 * unwrap() descarta `meta` (só devolve `data`) — listagem paginada precisa
 * de `meta.pagination`, então essa chamada usa o apiClient diretamente em
 * vez do helper padrão, sem alterar apiClient.ts.
 */
export async function listClients(filters: ClientFilters): Promise<ClientListResult> {
  const response = await apiClient.get<ApiSuccess<Client[]>>('/clients', { params: filters })
  const meta = response.data.meta as { pagination?: PaginationMeta }

  return {
    clients: response.data.data,
    pagination: meta.pagination ?? {
      current_page: 1,
      per_page: filters.per_page ?? response.data.data.length,
      total: response.data.data.length,
      last_page: 1,
    },
  }
}

export function getClient(uuid: string): Promise<Client> {
  return unwrap(apiClient.get<ApiSuccess<Client>>(`/clients/${uuid}`))
}

export function createClient(payload: ClientPayload): Promise<Client> {
  return unwrap(apiClient.post<ApiSuccess<Client>>('/clients', payload))
}

export function updateClient(uuid: string, payload: Partial<ClientPayload>): Promise<Client> {
  return unwrap(apiClient.put<ApiSuccess<Client>>(`/clients/${uuid}`, payload))
}

export function deleteClient(uuid: string): Promise<void> {
  return apiClient.delete(`/clients/${uuid}`).then(() => undefined)
}

/**
 * Diretório de clientes com endereço completo em PDF, gerado no servidor a
 * partir dos mesmos filtros de `listClients`. Não confundir com
 * `/reports/clients/pdf` (relatório financeiro de `reportDetailService.ts`,
 * tela de Relatórios).
 */
export function exportClientsPdf(filters: ClientFilters): Promise<Blob> {
  return apiClient
    .post<Blob>('/clients/export-pdf', filters, { responseType: 'blob' })
    .then((response) => response.data)
}
