/**
 * Tipos do módulo PDV (Fase PDV-1) — espelham exatamente os Resources/Requests
 * do backend em `api/app/Http/{Resources,Requests}/Pdv/*`. Pagamento é sempre
 * "declarado" (todas as formas viram `Payment.status='paid'` na hora); a venda
 * reaproveita o `OrderResource` (ver `types/order.ts`).
 */

export type CashSessionStatus = 'open' | 'closed'
export type CashMovementType = 'supply' | 'withdrawal'

/**
 * Formas de pagamento aceitas pelo PDV — enum PRÓPRIO do backend
 * (`CreatePdvSaleRequest`: cash|pix|card|debit|credit), diferente do enum da
 * loja online (`credit_card`/`debit_card` em `constants/paymentMethods.ts`).
 * Não confundir os dois.
 */
export type PdvPaymentMethod = 'cash' | 'pix' | 'credit' | 'debit'

export interface CashRegisterRef {
  uuid: string
  name: string
  is_active: boolean
}

export interface CashMovement {
  uuid: string
  type: CashMovementType
  amount: number
  reason: string | null
  created_at: string
}

export interface CashSession {
  uuid: string
  status: CashSessionStatus
  opened_at: string | null
  opening_amount: number
  closed_at: string | null
  closing_amount_declared: number | null
  closing_amount_expected: number | null
  difference: number | null
  cash_register?: CashRegisterRef
  movements?: CashMovement[]
}

export interface OpenCashSessionPayload {
  opening_amount: number
  cash_register_uuid?: string
}

export interface CloseCashSessionPayload {
  closing_amount_declared: number
}

export interface RegisterCashMovementPayload {
  type: CashMovementType
  amount: number
  reason?: string
}

export interface PdvSaleItemPayload {
  product_uuid: string
  quantity: number
  /** Omitido = backend resolve o preço de tabela do produto. */
  unit_price?: number
}

export interface PdvSalePaymentPayload {
  method: PdvPaymentMethod
  amount: number
}

export interface CreatePdvSalePayload {
  items: PdvSaleItemPayload[]
  payments: PdvSalePaymentPayload[]
  /** Consumidor final quando omitido (backend resolve/cria "Consumidor Final"). */
  client_uuid?: string
  /** Sem ela, usa a sessão de caixa aberta atual do tenant. */
  cash_session_uuid?: string
  stock_location_uuid?: string
  notes?: string
  /** Operador resolvido via PIN (`POST /pdv/operator-session`) — grava `orders.operated_by`. Omitido = sem operador identificado. */
  operator_uuid?: string
  /** Chave idempotente local usada no sync offline para evitar duplicidade de venda ao reenviar a mesma operação. */
  client_sale_uuid?: string
}

/**
 * PIN de operador (roadmap A4, item 15) — 2ª camada de identificação DENTRO
 * da sessão de staff já aberta (não re-autentica via JWT). Espelha
 * `App\Http\Resources\Pdv\OperatorResource`.
 */
export interface Operator {
  uuid: string
  name: string
}

export interface SetOperatorPinPayload {
  pin: string
}

export interface OperatorSessionPayload {
  pin: string
}

export interface PdvOfflineSnapshotProduct {
  uuid: string
  name: string
  sku: string | null
  barcode: string | null
  unit: string | null
  price: number
  stock_quantity: number
  updated_at: string | null
}

export interface PdvOfflineSnapshot {
  generated_at: string
  offline_payment_methods: PdvPaymentMethod[]
  blocked_payment_methods: PdvPaymentMethod[]
  cash_session: CashSession | null
  products: PdvOfflineSnapshotProduct[]
}

export type PdvOfflineQueueStatus = 'pending' | 'syncing' | 'synced' | 'error'

export interface PdvOfflineQueuedSale {
  local_sale_uuid: string
  tenant_uuid: string
  cash_session_uuid: string
  client_name: string | null
  status: PdvOfflineQueueStatus
  payload: CreatePdvSalePayload
  receipt_order: {
    codigo: string
    total_amount: number
    created_at: string
    items: Array<{
      uuid: string
      product: {
        uuid: string
        name: string
        unit: string | null
      }
      quantity: number
      unit_price: number
      line_total: number
    }>
  }
  created_at: string
  synced_at: string | null
  synced_order_uuid: string | null
  last_error: string | null
}

export interface PdvOfflineReceiptState {
  local_sale_uuid: string
  is_offline_pending: boolean
}
