import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { ChangePasswordPayload, RequestEmailChangePayload, UpdateProfilePayload, UserProfile } from '../types/profile'

export function getProfile(): Promise<UserProfile> {
  return unwrap(apiClient.get<ApiSuccess<UserProfile>>('/auth/profile'))
}

/**
 * Sempre multipart (mesmo sem avatar novo) — payload aqui é sempre pequeno
 * (só `name` opcional) e usar `_method=PUT` via POST evita duplicar um
 * caminho JSON só pra esse caso, ao contrário de `productService`
 * (que tem payload grande e prioriza JSON quando não há imagem).
 */
export function updateProfile(payload: UpdateProfilePayload, avatarFile?: File | null): Promise<UserProfile> {
  const formData = new FormData()
  formData.append('_method', 'PUT')
  if (payload.name !== undefined) formData.append('name', payload.name)
  if (payload.phone !== undefined) formData.append('phone', payload.phone ?? '')
  if (avatarFile) formData.append('avatar', avatarFile)

  return unwrap(apiClient.post<ApiSuccess<UserProfile>>('/auth/profile', formData))
}

export function changePassword(payload: ChangePasswordPayload): Promise<void> {
  return apiClient.post('/auth/profile/password', payload).then(() => undefined)
}

export function requestEmailChange(payload: RequestEmailChangePayload): Promise<void> {
  return apiClient.post('/auth/profile/email', payload).then(() => undefined)
}

export function confirmEmail(token: string): Promise<void> {
  return apiClient.post('/auth/confirm-email', { token }).then(() => undefined)
}
