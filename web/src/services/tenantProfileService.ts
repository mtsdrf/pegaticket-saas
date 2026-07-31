import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { TenantProfile, UpdateTenantProfilePayload } from '../types/tenantProfile'
import { extractFilenameFromContentDisposition, triggerBlobDownload } from '../utils/fileDownload'

export function getTenantProfile(): Promise<TenantProfile> {
  return unwrap(apiClient.get<ApiSuccess<TenantProfile>>('/tenant-profile'))
}

/**
 * "Exportar meus dados" (roadmap A1.2) — ZIP com 1 CSV por entidade
 * principal do tenant (clients/products/orders), baixado direto como
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
    ie: payload.ie ?? null,
    im: payload.im ?? null,
    cnae: payload.cnae ?? null,
    tax_regime: payload.tax_regime ?? null,
    fiscal_environment: payload.fiscal_environment ?? null,
    ibge_city_code: payload.ibge_city_code ?? null,
    fiscal_provider: payload.fiscal_provider ?? null,
    fiscal_nfe_series: payload.fiscal_nfe_series ?? null,
    fiscal_nfce_series: payload.fiscal_nfce_series ?? null,
    fiscal_nfse_series: payload.fiscal_nfse_series ?? null,
    fiscal_next_nfe_number: payload.fiscal_next_nfe_number ?? null,
    fiscal_next_nfce_number: payload.fiscal_next_nfce_number ?? null,
    fiscal_next_nfse_number: payload.fiscal_next_nfse_number ?? null,
    fiscal_nfce_csc_id: payload.fiscal_nfce_csc_id ?? null,
    fiscal_nfce_csc_code: payload.fiscal_nfce_csc_code ?? null,
    fiscal_provider_api_token: payload.fiscal_provider_api_token ?? null,
    fiscal_certificate_a1_password: payload.fiscal_certificate_a1_password ?? null,
    clear_fiscal_nfce_csc_code: payload.clear_fiscal_nfce_csc_code ?? false,
    clear_fiscal_provider_api_token: payload.clear_fiscal_provider_api_token ?? false,
    clear_fiscal_certificate_a1: payload.clear_fiscal_certificate_a1 ?? false,
  }

  if (!payload.logo && !payload.fiscal_certificate_a1) {
    return unwrap(apiClient.put<ApiSuccess<TenantProfile>>('/tenant-profile', body))
  }

  const formData = new FormData()
  for (const [key, value] of Object.entries(body)) {
    if (value !== null) {
      if (typeof value === 'boolean') {
        formData.append(key, value ? '1' : '0')
        continue
      }

      formData.append(key, String(value))
    }
  }
  if (payload.logo) {
    formData.append('logo', payload.logo)
  }
  if (payload.fiscal_certificate_a1) {
    formData.append('fiscal_certificate_a1', payload.fiscal_certificate_a1)
  }
  formData.append('_method', 'PUT')
  return unwrap(apiClient.post<ApiSuccess<TenantProfile>>('/tenant-profile', formData))
}
