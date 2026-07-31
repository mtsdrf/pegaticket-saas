import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type { TicketType, TicketTypeFilters, TicketTypePayload } from '../types/ticketType'

export function listTicketTypes(filters: TicketTypeFilters): Promise<PaginatedResult<TicketType>> {
  return listPaginated<TicketType>('/ticket-types', filters)
}

export function getTicketType(uuid: string): Promise<TicketType> {
  return unwrap(apiClient.get<ApiSuccess<TicketType>>(`/ticket-types/${uuid}`))
}

/** `undefined`/`null` ficam de fora do FormData; booleano vira "1"/"0" (regra `boolean` do Laravel aceita ambos). */
function buildFormData(payload: Record<string, unknown>, imageFile?: File | null): FormData {
  const formData = new FormData()

  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null) return
    formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  if (imageFile) formData.append('image', imageFile)

  return formData
}

export function createTicketType(payload: TicketTypePayload, imageFile?: File | null): Promise<TicketType> {
  const formData = buildFormData(payload as unknown as Record<string, unknown>, imageFile)
  return unwrap(apiClient.post<ApiSuccess<TicketType>>('/ticket-types', formData))
}

/**
 * Sem imagem nova, envia PUT normal (JSON). Com imagem nova, o Laravel
 * precisa de multipart — PHP não faz parse de multipart em PUT nativo,
 * então usa o spoofing padrão do framework: POST + campo `_method=PUT`.
 */
export function updateTicketType(
  uuid: string,
  payload: Partial<TicketTypePayload>,
  imageFile?: File | null,
): Promise<TicketType> {
  if (!imageFile) {
    return unwrap(apiClient.put<ApiSuccess<TicketType>>(`/ticket-types/${uuid}`, payload))
  }

  const formData = buildFormData(payload as unknown as Record<string, unknown>, imageFile)
  formData.append('_method', 'PUT')
  return unwrap(apiClient.post<ApiSuccess<TicketType>>(`/ticket-types/${uuid}`, formData))
}

export function deleteTicketType(uuid: string): Promise<void> {
  return apiClient.delete(`/ticket-types/${uuid}`).then(() => undefined)
}

/** Sem `status`, o backend alterna entre `ativo`/`pausado` — usado pelo toggle rápido de bloqueio na listagem. */
export function toggleTicketTypeStatus(uuid: string, status?: string): Promise<TicketType> {
  return unwrap(
    apiClient.patch<ApiSuccess<TicketType>>(
      `/ticket-types/${uuid}/toggle-status`,
      status === undefined ? {} : { status },
    ),
  )
}
