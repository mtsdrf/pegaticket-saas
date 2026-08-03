import type { PaginationMeta } from './pagination'

/** `final_customer` do `SaleResource`/`SaleListResource` — comprador (`FinalCustomer`), não mais `Client` (CRUD descontinuado, migração 2026-07-31). */
export interface SaleFinalCustomerRef {
  uuid: string
  name: string
  /** Sobrenome informado no checkout online — `null`/ausente para vendas staff/sem informação. */
  last_name?: string | null
  /** Aditivos do `SaleResource` (roadmap Loja) — só presentes quando a venda é carregada com o detalhe completo. */
  phone_primary?: string | null
}

/** Espelha `items[]` de `SaleResource` — exatamente um de `ticket_type`/`event_product` vem não-nulo por item. */
export interface SaleItem {
  uuid: string
  ticket_type: {
    uuid: string
    name: string
    unit: string | null
  } | null
  event_product: {
    uuid: string
    name: string
  } | null
  seat: {
    uuid: string
    label: string
    sector_name: string | null
    kind: string
  } | null
  quantity: number
  unit_price: number
  line_total: number
  notes: string | null
}

export interface SaleInstallment {
  uuid: string
  installment_number: number
  amount: number
  due_date: string
  is_paid: boolean
  paid_at: string | null
}

/**
 * Toda venda staff é sempre `status: 'confirmed'`/`origin: 'staff'` — só o
 * canal online (`origin: 'storefront'`) nasce `pending_approval`.
 * `cancellation_requested` (roadmap A4) é transitório — só venda `origin: 'storefront'` chega nele, via solicitação do cliente final no Portal; volta pro status anterior ao aprovar/rejeitar.
 */
export type SaleStatus = 'pending_approval' | 'confirmed' | 'rejected' | 'cancellation_requested'
export type SaleOrigin = 'staff' | 'storefront'
export type SaleOperationStage = 'approval' | 'confirmed'

export interface Sale {
  uuid: string
  codigo: string
  is_installment: boolean
  total_amount: number
  /** Taxa de serviço, já somada ao `total_amount`; nos canais atuais costuma permanecer `0`. */
  service_fee: number
  /** Já subtraído do `total_amount`; vendas staff/sem cupom ficam com `0`. */
  discount_amount: number
  /** Código do cupom aplicado, se houver (via relação `coupon`) — `null` quando nenhum cupom foi usado. */
  coupon_code: string | null
  is_paid: boolean
  /** Valor efetivamente pago — pode ser PARCIAL (menor que `total_amount`) mesmo com `is_paid: false`. */
  paid_amount: number | null
  paid_at: string | null
  due_date: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
  notes: string | null
  /** Meio de pagamento informado no checkout público — `null`/ausente para vendas staff/sem informação. */
  payment_method?: string | null
  /** Só relevante quando `payment_method === 'cash'` — cliente pediu troco. */
  needs_change?: boolean
  /** Valor da nota/cédula usada pelo cliente para calcular o troco — presente quando `needs_change: true`. */
  change_for_amount?: number | null
  status: SaleStatus
  origin: SaleOrigin
  final_customer?: SaleFinalCustomerRef
  items?: SaleItem[]
  installments?: SaleInstallment[]
  created_at: string
}

export interface SaleFilters {
  client_uuid?: string
  client_name?: string
  is_paid?: boolean
  is_installment?: boolean
  is_cancelled?: boolean
  total_amount_min?: number
  total_amount_max?: number
  status?: SaleStatus
  origin?: SaleOrigin
  stage?: SaleOperationStage
  /** Gestão de vendas online (`/vendas-online`): exclui cancelados/recusados/concluídos e força ordenação fixa (pendentes primeiro, depois mais antigo). Ignora `sort_by`/`sort_dir`. */
  active_only?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

/** Exatamente um de `ticket_type_uuid`/`event_product_uuid` por item — nunca os dois nem nenhum. */
export interface SaleCreateItemPayload {
  ticket_type_uuid?: string
  event_product_uuid?: string
  quantity: number
  /** Preço praticado do item — sobrepõe o preço de tabela quando enviado. Omitido = usa o preço de tabela. */
  unit_price?: number
  /** Observação por item, até 200 caracteres. */
  notes?: string
}

export interface SalePayload {
  final_customer_uuid: string
  is_installment: boolean
  installments_count?: number | null
  /** Backend limita a 500 caracteres. */
  notes?: string
  items: SaleCreateItemPayload[]
}

export interface SaleInstallmentPayload {
  installment_number: number
  amount: number
  due_date: string
}

/**
 * Item do lote de `PUT /sales/{sale}/installments`. `uuid` presente = atualiza
 * parcela não paga existente; ausente = cria. Parcela não paga existente que não
 * aparecer no array é excluída pelo backend. Parcela paga nunca entra aqui.
 */
export interface SaleInstallmentDraft {
  uuid?: string
  installment_number: number
  amount: number
  due_date: string
}

export interface SaleInstallmentsReallocatePayload {
  installments: SaleInstallmentDraft[]
}

/**
 * Item do payload de `PUT /sales/{sale}/items`. `uuid` presente = atualiza
 * item existente; ausente = cria. Item existente que não aparecer no array
 * (`items` é o array COMPLETO) é removido pelo backend.
 */
export interface SaleUpdateItemDraft {
  uuid?: string
  ticket_type_uuid?: string
  event_product_uuid?: string
  quantity: number
  /** Omitido em item existente sem troca de ingresso/produto = preserva o preço já gravado; em item novo/trocado, cai na resolução automática do backend. */
  unit_price?: number
  notes?: string
}

export interface SaleUpdateItemsPayload {
  notes?: string
  items: SaleUpdateItemDraft[]
}

export interface SaleListResult {
  items: Sale[]
  pagination: PaginationMeta
}

/**
 * `SalePaymentResource` (roadmap Fase B, item 1 — PSP real). `metadata`
 * traz o QR code Pix quando o provider é o Mercado Pago (`qr_code`
 * copia-e-cola, `qr_code_base64` para renderizar a imagem, `ticket_url`
 * como alternativa) — `null`/vazio com `ManualPaymentProvider` (sem PSP
 * real configurado), tratar como "sem dado de QR" na UI, nunca assumir
 * shape feliz.
 */
export interface SalePayment {
  uuid: string
  provider: string
  provider_charge_id: string | null
  method: string
  amount: string
  status: 'pending' | 'paid' | 'authorized' | 'in_analysis' | 'divergent' | 'failed' | 'canceled' | 'requested' | string
  paid_at: string | null
  created_at: string
  metadata: {
    qr_code?: string | null
    qr_code_base64?: string | null
    ticket_url?: string | null
    provider_status?: string | null
    raw_status?: string | null
    charge_id?: string | null
    provider_response?: unknown
  } | null
}

export interface SalePaymentCheckoutConfig {
  provider: string
  available: boolean
  environment: 'SANDBOX' | 'PROD' | null
  public_key: string | null
  three_ds_session: string | null
  three_ds_session_expires_at: string | null
  sdk_script_url: string | null
}

export interface SalePaymentAuthenticationMethodPayload {
  type: 'THREEDS' | 'INAPP'
  id: string
}

export interface SalePaymentChargePayload {
  method?: 'pix' | 'credit_card' | 'debit_card'
  payer_tax_id?: string
  payer_name?: string
  payer_email?: string
  payer_phone?: string
  card?: {
    encrypted: string
    holder_name: string
    holder_tax_id: string
    installments?: number
  }
  authentication_method?: SalePaymentAuthenticationMethodPayload
}
