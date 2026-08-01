/** Espelha `SaleRefundResource` (`GET/POST /sales/{sale}/refunds`) — estorno EXTERNO (spec 5.14): o clube já estornou o pagamento no PagBank fora do sistema, aqui só se registra o que aconteceu. */

export type SaleRefundType = 'total' | 'parcial'

/** Único valor hoje é `SaleRefund::STATUS_REGISTERED` ('registrado') — tipado como string por segurança a valores futuros. */
export type SaleRefundStatus = 'registrado' | string

export interface SaleRefundTicketRef {
  uuid: string
  code: string
  status: string
}

export interface SaleRefund {
  uuid: string
  type: SaleRefundType
  amount: number
  reason: string
  refunded_at: string
  external_reference: string | null
  has_receipt: boolean
  notes: string | null
  release_seats: boolean
  status: SaleRefundStatus
  /** Só presente quando `type === 'parcial'` (`whenLoaded` no backend). */
  tickets?: SaleRefundTicketRef[]
  created_at: string
}

/** Payload de `POST /sales/{sale}/refunds` — enviado como multipart (upload opcional de `receipt`). */
export interface SaleRefundPayload {
  type: SaleRefundType
  amount: number
  reason: string
  /** `YYYY-MM-DD`. */
  refunded_at: string
  external_reference?: string
  notes?: string
  release_seats?: boolean
  receipt?: File | null
  /** Obrigatório e não vazio quando `type === 'parcial'` (mesma regra do backend). */
  ticket_uuids?: string[]
}
