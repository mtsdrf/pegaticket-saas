import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { DiaIdeal, PeriodoIdeal } from '../types/client'
import { listPaginated } from './crudService'
import type { PaginatedResult } from '../types/pagination'

export function getDiaIdeais(): Promise<DiaIdeal[]> {
  return unwrap(apiClient.get<ApiSuccess<DiaIdeal[]>>('/dias-ideais', { params: { per_page: 100 } }))
}

export function getPeriodoIdeais(): Promise<PeriodoIdeal[]> {
  return unwrap(
    apiClient.get<ApiSuccess<PeriodoIdeal[]>>('/periodos-ideais', { params: { per_page: 100 } }),
  )
}

export function listDiaIdeais(params: object): Promise<PaginatedResult<DiaIdeal>> {
  return listPaginated<DiaIdeal>('/dias-ideais', params)
}

export function listPeriodoIdeais(params: object): Promise<PaginatedResult<PeriodoIdeal>> {
  return listPaginated<PeriodoIdeal>('/periodos-ideais', params)
}

export async function getDiaIdeal(uuid: string): Promise<DiaIdeal> {
  const { items } = await listPaginated<DiaIdeal>('/dias-ideais', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Dia ideal não encontrado.')
  return found
}

export async function getPeriodoIdeal(uuid: string): Promise<PeriodoIdeal> {
  const { items } = await listPaginated<PeriodoIdeal>('/periodos-ideais', { per_page: 100 })
  const found = items.find((item) => item.uuid === uuid)
  if (!found) throw new Error('Período ideal não encontrado.')
  return found
}

export function createDiaIdeal(payload: Pick<DiaIdeal, 'name' | 'is_active'>): Promise<DiaIdeal> {
  return unwrap(apiClient.post<ApiSuccess<DiaIdeal>>('/dias-ideais', payload))
}

export function updateDiaIdeal(uuid: string, payload: Partial<Pick<DiaIdeal, 'name' | 'is_active'>>): Promise<DiaIdeal> {
  return unwrap(apiClient.put<ApiSuccess<DiaIdeal>>(`/dias-ideais/${uuid}`, payload))
}

export function deleteDiaIdeal(uuid: string): Promise<void> {
  return apiClient.delete(`/dias-ideais/${uuid}`).then(() => undefined)
}

export function createPeriodoIdeal(payload: Pick<PeriodoIdeal, 'name' | 'is_active'>): Promise<PeriodoIdeal> {
  return unwrap(apiClient.post<ApiSuccess<PeriodoIdeal>>('/periodos-ideais', payload))
}

export function updatePeriodoIdeal(
  uuid: string,
  payload: Partial<Pick<PeriodoIdeal, 'name' | 'is_active'>>,
): Promise<PeriodoIdeal> {
  return unwrap(apiClient.put<ApiSuccess<PeriodoIdeal>>(`/periodos-ideais/${uuid}`, payload))
}

export function deletePeriodoIdeal(uuid: string): Promise<void> {
  return apiClient.delete(`/periodos-ideais/${uuid}`).then(() => undefined)
}
