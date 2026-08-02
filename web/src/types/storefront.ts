import type { CouponType } from './coupon'
import type { PaginationMeta } from './pagination'
import type { PaymentMethod } from '../constants/paymentMethods'

/** `GET /loja/{slug}` — allow-list estrita do backend (`StorefrontTenantResource`). */
export interface StorefrontTenant {
  slug: string
  name: string
  logo_url: string | null
  estimated_preparation_minutes: number | null
  /** Delivery Fase 4 — agregado de `SaleRatingService::tenantSummary()`; `null` quando `ratings_count === 0` (nunca `0.0`). */
  average_rating: number | null
  ratings_count: number
  email: string | null
  phone: string | null
  mobile_phone: string | null
  whatsapp: string | null
  instagram: string | null
  facebook: string | null
  /** Reforma da loja — endereço já formatado por extenso (`null` se a empresa não cadastrou endereço). */
  address: string | null
  /** Coordenadas do geocoding assíncrono do endereço — `null` enquanto não geocodificado (mapa read-only só renderiza quando ambos existem). */
  address_lat: number | null
  address_lng: number | null
  /** Formas de pagamento aceitas no checkout (`tenant_settings.accepted_payment_methods`). */
  accepted_payment_methods: PaymentMethod[]
  storefront_enabled: boolean
  /** Layout do catálogo escolhido pela empresa (`tenant_settings.catalog_layout`) — mantido no contrato do tenant; o catálogo público de eventos usa sempre 1 cartão por evento, sem alternância grid/lista. */
  catalog_layout: StorefrontCatalogLayout
}

export type StorefrontCatalogLayout = 'grid' | 'list'

export type StorefrontEventType = 'ingresso' | 'inscricao' | 'mesa' | 'assento' | 'misto'
export type StorefrontEventStatus =
  | 'rascunho'
  | 'agendado'
  | 'publicado'
  | 'vendas_pausadas'
  | 'esgotado'
  | 'encerrado'
  | 'cancelado'
  | 'arquivado'

export interface StorefrontEventCategoryRef {
  uuid: string
  name: string
}

export interface StorefrontVenueRef {
  uuid: string
  name: string
  map_version_uuid: string
  map_version_number: number
}

export type StorefrontTicketTypeStatus = 'rascunho' | 'ativo' | 'pausado' | 'esgotado' | 'encerrado'

/** Nó `ticket_types[]` do detalhe do evento (`TicketTypeResource`, allow-list de leitura pública). */
export interface StorefrontTicketType {
  uuid: string
  name: string
  description: string | null
  price: number
  image_url: string | null
  quantity_available: number | null
  min_per_order: number | null
  max_per_order: number | null
  sales_start_at: string | null
  sales_end_at: string | null
  status: StorefrontTicketTypeStatus
}

/** Nó `event_products[]` do detalhe do evento (`EventProductResource`). */
export interface StorefrontEventProduct {
  uuid: string
  name: string
  description: string | null
  price: number
  quantity_available: number | null
  max_per_order: number | null
  sales_start_at: string | null
  sales_end_at: string | null
  kind: 'addon' | 'parking'
  requires_plate: boolean
  requires_model: boolean
  requires_color: boolean
  status: StorefrontTicketTypeStatus
}

/** `GET /loja/{slug}/eventos` (`EventResource`) — catálogo público de eventos publicados/públicos, substitui o antigo catálogo de produtos. */
export interface StorefrontEvent {
  uuid: string
  name: string
  slug: string
  description_short: string | null
  description_full: string | null
  cover_image_url: string | null
  type: StorefrontEventType
  location_name: string | null
  location_address: string | null
  starts_at: string
  ends_at: string
  status: StorefrontEventStatus
  category: StorefrontEventCategoryRef | null
  venue: StorefrontVenueRef | null
  /**
   * Só reflete o cliente final logado quando o Bearer do portal é enviado
   * (`customer.jwt.optional`); `undefined` para visitante anônimo — nunca
   * tratar ausência como `false` definitivo.
   */
  is_favorited?: boolean
  /** Só presentes no detalhe (`GET /loja/{slug}/eventos/{eventSlug}`), ausentes na listagem. */
  ticket_types?: StorefrontTicketType[]
  event_products?: StorefrontEventProduct[]
}

export interface StorefrontEventFilters {
  name?: string
  event_category_uuid?: string
  type?: StorefrontEventType
  per_page?: number
  page?: number
}

/** `GET /loja/{slug}/categorias` — só categorias com evento disponível, ordenadas por priority no backend. */
export interface StorefrontCategory {
  uuid: string
  name: string
}

export interface StorefrontEventListResult {
  items: StorefrontEvent[]
  pagination: PaginationMeta
}

/**
 * Item do carrinho local (`localStorage`, por slug — ver `constants/storage.ts`).
 * Exatamente um de `ticket_type_uuid`/`event_product_uuid` por item (mesma
 * regra de `StorefrontCheckoutItemPayload`/backend).
 */
export interface StorefrontCartItem {
  id: string
  ticket_type_uuid?: string
  event_product_uuid?: string
  name: string
  event_name: string
  event_slug?: string | null
  session_uuid?: string | null
  session_name?: string | null
  seat_uuid?: string | null
  seat_label?: string | null
  seat_sector_name?: string | null
  seat_kind?: string | null
  seat_capacity?: number | null
  unit_price: number
  image_url: string | null
  quantity: number
  /** Observação livre do cliente sobre este item (ex: "meia-entrada") — até 200 caracteres, enviada como `items[].notes` no checkout. */
  notes?: string | null
}

/**
 * Participante opcional de um item de `ticket_type_uuid` (spec 5.10 Etapa 2)
 * — quando ausente/omitido, o Ticket emitido fica com attendee_name/document
 * nulos (comprador é o participante implícito). Não se aplica a
 * `event_product_uuid` (adicional/estacionamento não tem participante).
 */
export interface StorefrontCheckoutParticipantPayload {
  name?: string
  document?: string
}

/** Exatamente um de `ticket_type_uuid`/`event_product_uuid` por item — mesma regra do backend. */
export interface StorefrontCheckoutItemPayload {
  ticket_type_uuid?: string
  event_product_uuid?: string
  quantity: number
  /** Observação por item, até 200 caracteres, distinta do `notes` geral da venda. */
  notes?: string
  /** Só enviado para itens de `ticket_type_uuid` com ao menos um participante preenchido. */
  participants?: StorefrontCheckoutParticipantPayload[]
}

export interface StorefrontCheckoutPayload {
  items: StorefrontCheckoutItemPayload[]
  hold_uuid?: string
  session_token?: string
  client_name: string
  client_last_name: string
  client_phone: string
  notes?: string
  /** Delivery Fase 3 — código do cupom aplicado com sucesso na prévia (`validateStorefrontCoupon`); omitido = sem cupom. */
  coupon_code?: string
  /** Meio de pagamento pretendido — só formato validado no backend; usado sobretudo para checar cupom restrito por meio de pagamento (`allowed_payment_methods`). */
  payment_method?: PaymentMethod
  /** Só relevante quando `payment_method: 'cash'` — cliente vai pagar em dinheiro e precisa de troco. */
  needs_change?: boolean
  /** Obrigatório numericamente quando `needs_change: true` — valor da nota/cédula que o cliente vai usar para pagar. */
  change_for_amount?: number
}

/** `POST /loja/{slug}/checkout` devolve só `{ sale: { uuid } }` — o frontend redireciona pro rastreio público, não cria tela de status própria da venda. */
export interface StorefrontCheckoutResult {
  sale: { uuid: string }
}

export interface StorefrontAvailabilityTicketType {
  uuid: string
  name: string
  description: string | null
  session_uuid: string | null
  base_price: number
  effective_price: number
  available_quantity: number
  min_per_order: number | null
  max_per_order: number | null
  sales_start_at: string | null
  sales_end_at: string | null
  active_batch: {
    uuid: string
    name: string
    price: number
    quantity_available: number
  } | null
  requires_seat_selection: boolean
}

export interface StorefrontAvailabilityEventProduct {
  uuid: string
  name: string
  description: string | null
  kind: 'addon' | 'parking'
  price: number
  available_quantity: number
  max_per_order: number | null
  sales_start_at: string | null
  sales_end_at: string | null
}

export interface StorefrontAvailabilitySeat {
  uuid: string
  sector_name: string
  label: string
  kind: string
  capacity: number | null
  available_quantity: number
  pos_x: number | null
  pos_y: number | null
  is_accessible: boolean
  status: string
  availability_status: 'disponivel' | 'reservado' | 'indisponivel'
}

export interface StorefrontAvailabilitySession {
  uuid: string
  name: string
  starts_at: string | null
  ends_at: string | null
  gate_opens_at: string | null
  capacity: number | null
  status: string
  sales_start_at: string | null
  sales_end_at: string | null
}

export interface StorefrontAvailabilityResult {
  server_time: string
  default_hold_seconds: number
  event: {
    uuid: string
    name: string
    slug: string
    venue: {
      uuid: string
      name: string
      map_version_uuid: string
      map_version_number: number
      background_image_url: string | null
      width: number | null
      height: number | null
    } | null
  }
  requires_session_selection: boolean
  selected_session_uuid: string | null
  sessions: StorefrontAvailabilitySession[]
  ticket_types: StorefrontAvailabilityTicketType[]
  event_products: StorefrontAvailabilityEventProduct[]
  seats: StorefrontAvailabilitySeat[]
}

export interface StorefrontInventoryHoldItem {
  uuid: string
  quantity: number
  unit_price: number | null
  ticket_type: {
    uuid: string
    name: string
  } | null
  event_product: {
    uuid: string
    name: string
  } | null
  seat: {
    uuid: string
    label: string
    sector_name: string
    kind: string
  } | null
  ticket_batch: {
    uuid: string
    name: string
    price: number
  } | null
  meta: Record<string, unknown> | null
}

export interface StorefrontInventoryHold {
  uuid: string
  status: string
  origin: string
  session_token: string
  expires_at: string | null
  server_time: string
  remaining_seconds: number
  event?: {
    uuid: string
    name: string
    slug: string
  }
  session?: {
    uuid: string
    name: string
    starts_at: string | null
    ends_at: string | null
  } | null
  items: StorefrontInventoryHoldItem[]
  created_at: string
}

export interface StorefrontCreateHoldPayload {
  session_token: string
  session_uuid?: string
  items: Array<{
    ticket_type_uuid?: string
    event_product_uuid?: string
    seat_uuid?: string
    quantity: number
  }>
}

/** Delivery Fase 2 — códigos de erro do checkout (`StorefrontCheckoutController`). */
export const BELOW_MINIMUM_ORDER_CODE = 'BELOW_MINIMUM_ORDER'
export const DELIVERY_AREA_NOT_SERVED_CODE = 'DELIVERY_AREA_NOT_SERVED'

/** Delivery Fase 3 — códigos de erro de cupom, retornados tanto pela prévia (`POST /loja/{slug}/cupons/validar`) quanto pelo submit final do checkout. */
export const INVALID_COUPON_CODE = 'INVALID_COUPON'
export const COUPON_USAGE_LIMIT_REACHED_CODE = 'COUPON_USAGE_LIMIT_REACHED'

/**
 * `POST /loja/{slug}/cupons/validar` — prévia pública, não checa limite por
 * cliente nem calcula frete grátis de verdade (`discount_amount: 0` pro tipo
 * `free_shipping` nesta prévia, já que a taxa de entrega só é conhecida
 * depois do bairro escolhido). Exibir tal como vier, sem reconstruir a
 * lógica de frete grátis no frontend.
 */
export interface StorefrontCouponValidationResult {
  type: CouponType
  value: number | null
  discount_amount: number
}
