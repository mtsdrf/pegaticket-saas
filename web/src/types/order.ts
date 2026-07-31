import type { PaginationMeta } from './pagination'

/** Bloco de endereço aditivo do `OrderResource` (roadmap Loja) — presente no detalhe do pedido da loja e na tela pública de preparo. */
export interface OrderClientAddress {
  logradouro: string | null
  numero: string | null
  complemento: string | null
  cep: string | null
  bairro_name: string | null
  cidade_name: string | null
}

export interface OrderClientRef {
  uuid: string
  name: string
  /** Sobrenome informado no checkout da loja — `null`/ausente para pedidos staff/sem informação. */
  last_name?: string | null
  /** Aditivos do `OrderResource` (roadmap Loja) — só presentes quando o pedido é carregado com o detalhe completo. */
  phone_primary?: string | null
  endereco?: OrderClientAddress | null
}

export interface OrderStockLocationRef {
  uuid: string
  name: string
}

export interface OrderItem {
  uuid: string
  product: {
    uuid: string
    name: string
    unit: string | null
  }
  quantity: number
  unit_price: number
  line_total: number
  options_total?: number
  options?: Array<{
    uuid: string
    quantity: number
    unit_price: number
    line_total: number
    product_option: {
      uuid: string
      name: string
      description: string | null
      group_name: string | null
    }
  }>
}

export interface OrderInstallment {
  uuid: string
  installment_number: number
  amount: number
  due_date: string
  is_paid: boolean
  paid_at: string | null
}

/**
 * Delivery Fase 1: todo pedido staff é sempre `status: 'confirmed'`/`origin: 'staff'` — só a loja (`origin: 'storefront'`) nasce `pending_approval`.
 * `cancellation_requested` (roadmap A4) é transitório — só pedido `origin: 'storefront'` chega nele, via solicitação do cliente final no Portal; volta pro status anterior ao aprovar/rejeitar.
 */
export type OrderStatus = 'pending_approval' | 'confirmed' | 'rejected' | 'cancellation_requested'
export type OrderOrigin = 'staff' | 'storefront' | 'ifood'
export type OrderOperationStage = 'approval' | 'production' | 'dispatch' | 'financial_pending'

export interface Order {
  uuid: string
  codigo: string
  is_installment: boolean
  total_amount: number
  /** Delivery Fase 2 — já somado ao `total_amount`; pedidos staff (sem loja) ficam com `0`. */
  delivery_fee: number
  /** Taxa de serviço, já somada ao `total_amount`; nos canais atuais costuma permanecer `0`. */
  service_fee: number
  /** Delivery Fase 3 — já subtraído do `total_amount`; pedidos staff/sem cupom ficam com `0`. */
  discount_amount: number
  /** Código do cupom aplicado, se houver (via relação `coupon`) — `null` quando nenhum cupom foi usado. */
  coupon_code: string | null
  is_paid: boolean
  /** Valor efetivamente pago — pode ser PARCIAL (menor que `total_amount`) mesmo com `is_paid: false`. */
  paid_amount: number | null
  paid_at: string | null
  is_delivered: boolean
  delivered_at: string | null
  due_date: string | null
  expected_delivery_date: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
  notes: string | null
  /** Meio de pagamento informado no checkout público — `null`/ausente para pedidos staff/sem informação. */
  payment_method?: string | null
  /** Só relevante quando `payment_method === 'cash'` — cliente pediu troco. */
  needs_change?: boolean
  /** Valor da nota/cédula usada pelo cliente para calcular o troco — presente quando `needs_change: true`. */
  change_for_amount?: number | null
  status: OrderStatus
  origin: OrderOrigin
  /** "Saiu para entrega" (tela dedicada de gestão de pedidos da loja) — etapa opcional entre aprovado e entregue. */
  is_out_for_delivery: boolean
  out_for_delivery_at: string | null
  client?: OrderClientRef
  stock_location?: OrderStockLocationRef | null
  items?: OrderItem[]
  installments?: OrderInstallment[]
  created_at: string
}

export interface OrderFilters {
  client_uuid?: string
  client_name?: string
  is_paid?: boolean
  is_delivered?: boolean
  is_installment?: boolean
  is_cancelled?: boolean
  total_amount_min?: number
  total_amount_max?: number
  status?: OrderStatus
  origin?: OrderOrigin
  stage?: OrderOperationStage
  /** Gestão de pedidos da loja (/pedidos-loja): exclui cancelados/recusados/concluídos e força ordenação fixa (pendentes primeiro, depois mais antigo). Ignora `sort_by`/`sort_dir`. */
  active_only?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface OrderCreateItemPayload {
  product_uuid: string
  quantity: number
  /** Preço praticado do item — sobrepõe `Product.price` quando enviado. Omitido = usa o preço de tabela. */
  unit_price?: number
  options?: Array<{
    product_option_uuid: string
    quantity?: number
  }>
}

export interface OrderPayload {
  client_uuid: string
  stock_location_uuid?: string
  is_installment: boolean
  installments_count?: number | null
  /** Backend limita a 500 caracteres. */
  notes?: string
  items: OrderCreateItemPayload[]
  /** Pedido já nasce entregue (mesma lógica interna do botão "Entregar"). */
  mark_as_delivered?: boolean
  /** Pedido já nasce pago — backend rejeita (422) combinado com `is_installment: true`. */
  mark_as_paid?: boolean
  /** Data prevista de entrega (informativo, `YYYY-MM-DD`) — não é o mesmo campo que `delivered_at`. */
  expected_delivery_date?: string
}

export interface OrderInstallmentPayload {
  installment_number: number
  amount: number
  due_date: string
}

/**
 * Item do lote de `PUT /orders/{order}/installments`. `uuid` presente = atualiza
 * parcela não paga existente; ausente = cria. Parcela não paga existente que não
 * aparecer no array é excluída pelo backend. Parcela paga nunca entra aqui.
 */
export interface OrderInstallmentDraft {
  uuid?: string
  installment_number: number
  amount: number
  due_date: string
}

export interface OrderInstallmentsReallocatePayload {
  installments: OrderInstallmentDraft[]
}

/**
 * Item do payload de `PUT /orders/{order}/items`. `uuid` presente = atualiza
 * item existente; ausente = cria. Item existente que não aparecer no array
 * (`items` é o array COMPLETO) é removido pelo backend.
 */
export interface OrderUpdateItemDraft {
  uuid?: string
  product_uuid: string
  quantity: number
  /** Omitido em item existente sem troca de produto = preserva o preço já gravado; em item novo/trocado, cai na resolução automática do backend. */
  unit_price?: number
  options?: Array<{
    product_option_uuid: string
    quantity?: number
  }>
}

export interface OrderUpdateItemsPayload {
  notes?: string
  stock_location_uuid?: string
  expected_delivery_date?: string
  items: OrderUpdateItemDraft[]
}

export interface OrderListResult {
  items: Order[]
  pagination: PaginationMeta
}

/**
 * `OrderPaymentResource` (roadmap Fase B, item 1 — PSP real). `metadata`
 * traz o QR code Pix quando o provider é o Mercado Pago (`qr_code`
 * copia-e-cola, `qr_code_base64` para renderizar a imagem, `ticket_url`
 * como alternativa) — `null`/vazio com `ManualPaymentProvider` (sem PSP
 * real configurado), tratar como "sem dado de QR" na UI, nunca assumir
 * shape feliz.
 */
export interface OrderPayment {
  uuid: string
  provider: string
  provider_charge_id: string | null
  method: string
  amount: string
  status: 'pending' | 'paid' | 'divergent' | 'failed' | 'requested' | string
  paid_at: string | null
  created_at: string
  metadata: {
    qr_code?: string | null
    qr_code_base64?: string | null
    ticket_url?: string | null
  } | null
}
