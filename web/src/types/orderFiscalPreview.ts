export interface OrderFiscalPreviewIssue {
  key: string
  label: string
  severity: 'error' | 'warning'
  details: string
}

export interface OrderFiscalPreviewOperationProfile {
  uuid: string
  name: string
  operation_nature: 'sale' | 'return' | 'transfer' | 'service'
  document_type: 'nfce' | 'nfe' | 'nfse'
  default_cfop: string | null
  scope: {
    order_origin?: string[]
    fulfillment_type?: string[]
    destination_type?: string[]
  } | null
}

export interface OrderFiscalPreviewTaxRule {
  uuid: string
  tax_type: 'icms' | 'icms_st' | 'ipi' | 'pis' | 'cofins' | 'iss' | string
  rate_percent: number
  scope: Record<string, string[]> | null
}

export interface OrderFiscalPreviewLineItem {
  product_uuid: string | null
  product_name: string | null
  quantity: number
  ncm: string | null
  origin: string | null
  csosn_cst: string | null
  resolved_cfop: string | null
  matched_tax_rules: OrderFiscalPreviewTaxRule[]
}

export interface OrderFiscalPreview {
  status: 'ready' | 'attention'
  can_prepare: boolean
  provider: string
  provider_mode: 'manual' | 'official' | string
  official_submission_enabled: boolean
  context: {
    order_origin: 'staff' | 'storefront' | 'pdv' | 'counter' | string
    fulfillment_type: 'delivery' | 'pickup' | string
    destination_type: 'consumer_final' | 'business' | string
    uf_origin: string | null
    uf_dest: string | null
    document_type: 'nfce' | 'nfe' | 'nfse' | string
  }
  operation_profile: OrderFiscalPreviewOperationProfile | null
  line_items: OrderFiscalPreviewLineItem[]
  issues: OrderFiscalPreviewIssue[]
}

export interface OrderFiscalDocumentPreparationResult {
  uuid: string
  document_type: 'nfce' | 'nfe' | 'nfse' | string
  series: string | null
  document_number: number | null
  status: 'draft' | 'provider_submitted' | 'pending' | 'authorized' | 'rejected' | 'canceled' | string
  provider: string
  provider_document_id: string | null
  access_key: string | null
  pdf_path: string | null
  submitted_at: string | null
  provider_status_checked_at: string | null
  authorized_at: string | null
  rejected_at: string | null
  canceled_at: string | null
  rejection_reason: string | null
  payload_snapshot_summary: {
    generated_at: string | null
    order_code: string | null
    operation_profile_name: string | null
    items_count: number
    issues_count: number
  } | null
  created_at: string | null
}

export interface OrderFiscalDocumentDetail extends OrderFiscalDocumentPreparationResult {
  provider_response_payload: Record<string, unknown> | null
  provider_messages: Array<{
    uuid: string
    provider: string
    provider_document_id: string | null
    message_type: string
    level: 'info' | 'warning' | 'error' | string
    provider_status: string | null
    summary: string
    payload: Record<string, unknown> | null
    received_at: string | null
    created_at: string | null
  }>
  attempts: Array<{
    uuid: string
    operation_type: string
    status: string
    provider: string | null
    provider_reference: string | null
    idempotency_key: string | null
    payload_hash: string | null
    response_hash: string | null
    attempt_number: number
    payload: Record<string, unknown> | null
    response_payload: Record<string, unknown> | null
    started_at: string | null
    completed_at: string | null
    created_at: string | null
  }>
  payload_snapshot: {
    generated_at: string | null
    issuer: {
      tenant_uuid: string | null
      name: string | null
      cnpj: string | null
      tax_regime: string | null
      state_registration: string | null
      ibge_city_code: string | null
      uf: string | null
    }
    recipient: {
      client_uuid: string | null
      name: string | null
      document: string | null
      state_registration: string | null
      uf: string | null
    }
    operation: {
      order_uuid: string | null
      order_code: string | null
      order_origin: string | null
      fulfillment_type: string | null
      destination_type: string | null
      document_type: string | null
      operation_profile: {
        uuid: string | null
        name: string | null
        operation_nature: string | null
        default_cfop: string | null
      } | null
    }
    items: Array<{
      product_uuid: string | null
      product_name: string | null
      quantity: number
      unit_price: number
      line_total: number
      cfop: string | null
      ncm: string | null
      origin: string | null
      csosn_cst: string | null
      tax_rules: OrderFiscalPreviewTaxRule[]
    }>
    totals: {
      items_amount: number
      delivery_fee: number
      service_fee: number
      discount_amount: number
      paid_amount: number
      total_amount: number
    }
    issues: OrderFiscalPreviewIssue[]
  } | null
}
