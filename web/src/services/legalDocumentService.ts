import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { LegalDocument, LegalDocumentType } from '../types/legal'

/** Rota 100% pública — 404 quando não há versão ativa publicada do tipo. */
export function getLegalDocument(type: LegalDocumentType): Promise<LegalDocument> {
  return unwrap(apiClient.get<ApiSuccess<LegalDocument>>(`/legal-documents/${type}`))
}
