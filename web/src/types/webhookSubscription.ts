/** Espelha `WebhookEventCatalog::SUPPORTED` (backend) — adicionar evento novo exige o par nos dois lados. */
export const WEBHOOK_EVENT_TYPES = [
  'order.created',
  'order.approved',
  'order.rejected',
  'order.delivered',
  'order.cancelled',
  'order.paid',
] as const

export type WebhookEventType = (typeof WEBHOOK_EVENT_TYPES)[number]

export const WEBHOOK_EVENT_LABELS: Record<WebhookEventType, string> = {
  'order.created': 'Pedido criado',
  'order.approved': 'Pedido aprovado',
  'order.rejected': 'Pedido recusado',
  'order.delivered': 'Pedido entregue',
  'order.cancelled': 'Pedido cancelado',
  'order.paid': 'Pedido pago',
}

/** Espelha `WebhookSubscriptionResource` — nunca traz `secret`. */
export interface WebhookSubscription {
  uuid: string
  url: string
  event_types: WebhookEventType[]
  is_active: boolean
  created_at: string
}

/** Resposta única do `POST /webhook-subscriptions` — `secret` só existe aqui, nunca mais recuperável. */
export interface WebhookSubscriptionCreateResult extends WebhookSubscription {
  secret: string
}

export interface WebhookSubscriptionPayload {
  url: string
  event_types: WebhookEventType[]
  is_active?: boolean
}

/** Espelha `WebhookDeliveryResource`. */
export interface WebhookDelivery {
  uuid: string
  event_type: string
  response_status: number | null
  success: boolean
  attempt: number
  error: string | null
  attempted_at: string | null
  created_at: string
}
