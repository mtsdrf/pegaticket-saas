export type FiscalDocumentType = 'nfce' | 'nfe' | 'nfse'
export type FiscalOperationNature = 'sale' | 'return' | 'transfer' | 'service'

export interface FiscalOperationProfileScope {
  order_origin?: Array<'staff' | 'storefront' | 'pdv' | 'counter'>
  fulfillment_type?: Array<'delivery' | 'pickup'>
  destination_type?: Array<'consumer_final' | 'business'>
}

export interface FiscalOperationProfile {
  uuid: string
  name: string
  operation_nature: FiscalOperationNature
  document_type: FiscalDocumentType
  default_cfop: string | null
  scope: FiscalOperationProfileScope | null
  description: string | null
  is_active: boolean
  created_at: string | null
}

export interface FiscalOperationProfilePayload {
  name: string
  operation_nature: FiscalOperationNature
  document_type: FiscalDocumentType
  default_cfop?: string | null
  scope?: FiscalOperationProfileScope | null
  description?: string | null
  is_active?: boolean
}

export const FISCAL_DOCUMENT_TYPE_LABELS: Record<FiscalDocumentType, string> = {
  nfce: 'NFC-e',
  nfe: 'NF-e',
  nfse: 'NFS-e',
}

export const FISCAL_OPERATION_NATURE_LABELS: Record<FiscalOperationNature, string> = {
  sale: 'Venda',
  return: 'Devolução',
  transfer: 'Transferência',
  service: 'Serviço',
}
