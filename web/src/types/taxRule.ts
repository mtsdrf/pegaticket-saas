export type TaxType = 'icms' | 'icms_st' | 'ipi' | 'pis' | 'cofins' | 'iss'

export interface TaxRuleScope {
  cfop?: string[]
  ncm?: string[]
  uf_origin?: string[]
  uf_dest?: string[]
}

export interface TaxRule {
  uuid: string
  tax_type: TaxType
  scope: TaxRuleScope | null
  rate_percent: number
  valid_from: string | null
  valid_to: string | null
  is_active: boolean
  created_at: string | null
}

export interface TaxRulePayload {
  tax_type: TaxType
  scope?: TaxRuleScope | null
  rate_percent: number
  valid_from?: string | null
  valid_to?: string | null
  is_active?: boolean
}

export const TAX_TYPE_LABELS: Record<TaxType, string> = {
  icms: 'ICMS',
  icms_st: 'ICMS-ST',
  ipi: 'IPI',
  pis: 'PIS',
  cofins: 'COFINS',
  iss: 'ISS',
}

export const TAX_TYPE_OPTIONS: Array<{ value: TaxType; label: string }> = [
  { value: 'icms', label: 'ICMS' },
  { value: 'icms_st', label: 'ICMS-ST' },
  { value: 'ipi', label: 'IPI' },
  { value: 'pis', label: 'PIS' },
  { value: 'cofins', label: 'COFINS' },
  { value: 'iss', label: 'ISS' },
]
