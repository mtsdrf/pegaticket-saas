import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { TenantProfile, UpdateTenantProfilePayload } from '../types/tenantProfile'
import { extractFilenameFromContentDisposition, triggerBlobDownload } from '../utils/fileDownload'

export function getTenantProfile(): Promise<TenantProfile> {
  return unwrap(apiClient.get<ApiSuccess<TenantProfile>>('/tenant-profile'))
}

/**
 * "Exportar meus dados" (roadmap A1.2) — ZIP com 1 CSV por entidade
 * principal do tenant (clientes, ingressos e vendas), baixado direto como
 * binário (`Content-Disposition: attachment`), mesmo padrão de
 * `reportDetailService.exportPdf`. Throttle dedicado no backend
 * (`throttle:3,60`) — tela chamadora trata 429 com mensagem própria.
 */
export async function exportTenantData(): Promise<void> {
  const response = await apiClient.post('/tenant-data-export', undefined, { responseType: 'blob' })
  const filename = extractFilenameFromContentDisposition(response.headers['content-disposition'], 'dados-empresa.zip')
  triggerBlobDownload(response.data, filename)
}

/**
 * Sem logo novo, envia PUT normal (JSON). Com logo novo, o Laravel precisa de
 * multipart e não faz parse de PUT multipart nativo — mesmo spoofing já usado
 * em `tenantAdminService.updateTenant`: POST + campo `_method=PUT`.
 */
export function updateTenantProfile(payload: UpdateTenantProfilePayload): Promise<TenantProfile> {
  const name = payload.name.trim()
  const body = {
    name,
    cnpj: payload.cnpj ?? null,
  }

  if (!payload.logo) {
    return unwrap(apiClient.put<ApiSuccess<TenantProfile>>('/tenant-profile', body))
  }

  const formData = new FormData()
  for (const [key, value] of Object.entries(body)) {
    if (value !== null) {
      formData.append(key, String(value))
    }
  }
  formData.append('logo', payload.logo)
  formData.append('_method', 'PUT')
  return unwrap(apiClient.post<ApiSuccess<TenantProfile>>('/tenant-profile', formData))
}
