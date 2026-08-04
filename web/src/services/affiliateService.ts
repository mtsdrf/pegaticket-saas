import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { Affiliate, AffiliateCommission, CreateAffiliatePayload, UpdateAffiliatePayload } from '../types/affiliate'
import type { PaginatedResult } from '../types/pagination'

export function listAffiliates(): Promise<PaginatedResult<Affiliate>> {
  return listPaginated<Affiliate>('/affiliates', {})
}

export function getAffiliate(uuid: string): Promise<Affiliate> {
  return unwrap(apiClient.get<ApiSuccess<Affiliate>>(`/affiliates/${uuid}`))
}

export function createAffiliate(payload: CreateAffiliatePayload): Promise<Affiliate> {
  return unwrap(apiClient.post<ApiSuccess<Affiliate>>('/affiliates', payload))
}

export function updateAffiliate(uuid: string, payload: UpdateAffiliatePayload): Promise<Affiliate> {
  return unwrap(apiClient.put<ApiSuccess<Affiliate>>(`/affiliates/${uuid}`, payload))
}

export function listAffiliateCommissions(uuid: string): Promise<PaginatedResult<AffiliateCommission>> {
  return listPaginated<AffiliateCommission>(`/affiliates/${uuid}/commissions`, {})
}
