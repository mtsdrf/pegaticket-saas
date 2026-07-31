/** Espelha o `TenantResource` do backend (campos usados pela tela "Dados da empresa"). */
export interface TenantProfile {
  uuid: string
  name: string
  slug: string
  logo_url: string | null
  cnpj: string | null
  ie: string | null
  im: string | null
  cnae: string | null
  tax_regime: 'simples_nacional' | 'lucro_presumido' | 'lucro_real' | null
  fiscal_environment: 'homologacao' | 'producao' | null
  ibge_city_code: string | null
  fiscal_provider: 'manual' | 'focus_nfe' | 'plugnotas' | 'nfeio' | 'sped_nfe' | null
  fiscal_nfe_series: string | null
  fiscal_nfce_series: string | null
  fiscal_nfse_series: string | null
  fiscal_next_nfe_number: number | null
  fiscal_next_nfce_number: number | null
  fiscal_next_nfse_number: number | null
  fiscal_nfce_csc_id: string | null
  has_fiscal_nfce_csc_code: boolean
  has_fiscal_provider_api_token: boolean
  has_fiscal_certificate_a1: boolean
  fiscal_certificate_a1_name: string | null
  fiscal_certificate_a1_updated_at: string | null
}

export interface UpdateTenantProfilePayload {
  name: string
  logo?: File | null
  cnpj?: string | null
  ie?: string | null
  im?: string | null
  cnae?: string | null
  tax_regime?: 'simples_nacional' | 'lucro_presumido' | 'lucro_real' | null
  fiscal_environment?: 'homologacao' | 'producao' | null
  ibge_city_code?: string | null
  fiscal_provider?: 'manual' | 'focus_nfe' | 'plugnotas' | 'nfeio' | 'sped_nfe' | null
  fiscal_nfe_series?: string | null
  fiscal_nfce_series?: string | null
  fiscal_nfse_series?: string | null
  fiscal_next_nfe_number?: number | null
  fiscal_next_nfce_number?: number | null
  fiscal_next_nfse_number?: number | null
  fiscal_nfce_csc_id?: string | null
  fiscal_nfce_csc_code?: string | null
  fiscal_provider_api_token?: string | null
  fiscal_certificate_a1?: File | null
  fiscal_certificate_a1_password?: string | null
  clear_fiscal_nfce_csc_code?: boolean
  clear_fiscal_provider_api_token?: boolean
  clear_fiscal_certificate_a1?: boolean
}
