import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { ApiKey, ApiKeyCreateResult, ApiKeyPayload } from '../types/apiKey'

export function listApiKeys(): Promise<ApiKey[]> {
  return unwrap(apiClient.get<ApiSuccess<ApiKey[]>>('/api-keys'))
}

/** A chave em texto puro (`key`) só vem nesta resposta — nunca mais recuperável depois. */
export function createApiKey(payload: ApiKeyPayload): Promise<ApiKeyCreateResult> {
  return unwrap(apiClient.post<ApiSuccess<ApiKeyCreateResult>>('/api-keys', payload))
}

export function revokeApiKey(uuid: string): Promise<void> {
  return apiClient.delete(`/api-keys/${uuid}`).then(() => undefined)
}
