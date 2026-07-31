export type FiscalDocumentType = 'nfce' | 'nfe' | 'nfse'

export interface FiscalOperationScope {
  order_origin?: string[]
  fulfillment_type?: string[]
  destination_type?: string[]
}

export interface FiscalOperationProfile {
  uuid: string
  name: string
  operation_nature: string
  document_type: FiscalDocumentType
  default_cfop: string | null
  scope: FiscalOperationScope | null
  description: string | null
  is_active: boolean
}

export interface FiscalOperationProfilePayload {
  name: string
  operation_nature: string
  document_type: FiscalDocumentType
  default_cfop?: string | null
  scope?: FiscalOperationScope | null
  description?: string | null
  is_active?: boolean
}

export const FISCAL_DOCUMENT_TYPE_LABELS: Record<FiscalDocumentType, string> = {
  nfce: 'NFC-e',
  nfe: 'NF-e',
  nfse: 'NFS-e',
}
