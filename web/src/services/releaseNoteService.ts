import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { ReleaseNote } from '../types/releaseNote'

/** `GET /release-notes` — leitura liberada pra qualquer usuário autenticado (sem perm dedicada). CRUD é só via API/tinker por enquanto (roadmap A1.6). */
export function listReleaseNotes(limit = 10): Promise<ReleaseNote[]> {
  return unwrap(apiClient.get<ApiSuccess<ReleaseNote[]>>('/release-notes', { params: { limit } }))
}
