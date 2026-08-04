export interface EventGateTicketTypeRef {
  uuid: string
  name: string
}

export interface EventGate {
  uuid: string
  name: string
  is_active: boolean
  /** Lista vazia = portaria aberta, aceita qualquer tipo de ingresso. */
  allowed_ticket_types: EventGateTicketTypeRef[]
  created_at: string
}

export interface EventGateFilters {
  page?: number
  per_page?: number
  is_active?: boolean
}

export interface EventGatePayload {
  name: string
  is_active?: boolean
  /** `undefined` = não altera a restrição atual (só faz sentido no update); array vazio = volta a aceitar qualquer tipo. */
  ticket_type_uuids?: string[]
}
