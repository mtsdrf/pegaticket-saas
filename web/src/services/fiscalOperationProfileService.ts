import type { ApiSuccess } from '../types/api'
import type { FiscalOperationProfile, FiscalOperationProfilePayload } from '../types/fiscalOperationProfile'
import { apiClient, unwrap } from './apiClient'

export function listFiscalOperationProfiles(): Promise<FiscalOperationProfile[]> {
  return unwrap(apiClient.get<ApiSuccess<FiscalOperationProfile[]>>('/fiscal-operation-profiles'))
}

export async function getFiscalOperationProfile(uuid: string): Promise<FiscalOperationProfile> {
  const profiles = await listFiscalOperationProfiles()
  const found = profiles.find((profile) => profile.uuid === uuid)
  if (!found) {
    throw new Error('Perfil fiscal não encontrado.')
  }
  return found
}

export function createFiscalOperationProfile(payload: FiscalOperationProfilePayload): Promise<FiscalOperationProfile> {
  return unwrap(apiClient.post<ApiSuccess<FiscalOperationProfile>>('/fiscal-operation-profiles', payload))
}

export function updateFiscalOperationProfile(uuid: string, payload: FiscalOperationProfilePayload): Promise<FiscalOperationProfile> {
  return unwrap(apiClient.put<ApiSuccess<FiscalOperationProfile>>(`/fiscal-operation-profiles/${uuid}`, payload))
}

export function deleteFiscalOperationProfile(uuid: string): Promise<void> {
  return apiClient.delete(`/fiscal-operation-profiles/${uuid}`).then(() => undefined)
}
