import type { CouponType } from './coupon'
import type { PaginationMeta } from './pagination'
import type { PaymentMethod } from '../constants/paymentMethods'
import type { StoreBusinessHourDay } from './storeBusinessHours'

/** `GET /loja/{slug}` — allow-list estrita do backend (`StorefrontTenantResource`). */
export interface StorefrontTenant {
  slug: string
  name: string
  logo_url: string | null
  /**
   * Delivery Fase 2 — pode trazer MÚLTIPLOS turnos no mesmo `day_of_week`
   * (manhã + tarde, até 4). Dia sem nenhuma linha é considerado fechado.
   */
  business_hours: StoreBusinessHourDay[]
  estimated_preparation_minutes: number | null
  /** Delivery Fase 4 — agregado de `OrderRatingService::tenantSummary()`; `null` quando `ratings_count === 0` (nunca `0.0`). */
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
  /** Formas de pagamento aceitas na entrega/retirada (`tenant_settings.accepted_payment_methods`). */
  accepted_payment_methods: PaymentMethod[]
  /** Roadmap retirada na loja — `true` só quando o tenant habilitou `allow_store_pickup`; a UI de checkout só oferece a opção "retirar na loja" quando este campo vem `true`. */
  allow_store_pickup: boolean
  /** Par de `allow_store_pickup` — `true` só quando o tenant habilitou `allow_delivery`; a UI de checkout só oferece a opção "receber em casa" quando este campo vem `true`. */
  allow_delivery: boolean
  storefront_enabled: boolean
  /** Layout do catálogo escolhido pela empresa (`tenant_settings.catalog_layout`) — mantido no contrato do tenant; o catálogo público de eventos (migração PegaTicket, ver `.claude/memory`) usa sempre 1 cartão por evento, sem alternância grid/lista. */
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
  unit_price: number
  image_url: string | null
  quantity: number
  /** Observação livre do cliente sobre este item (ex: "meia-entrada") — até 200 caracteres, enviada como `items[].notes` no checkout. */
  notes?: string | null
}

/**
 * Mesmo shape de endereço já usado por `ClientAddressFields`/`ClientPayload`
 * — ver `.claude/memory` e `StorefrontCheckoutRequest` do backend. Campos
 * opcionais aqui porque `fulfillment_type: 'pickup'` dispensa endereço
 * (`required_if:fulfillment_type,delivery` no backend); quem monta o
 * payload garante que vêm preenchidos quando `fulfillment_type: 'delivery'`.
 */
export interface StorefrontCheckoutAddress {
  estado_uuid?: string
  cidade_uuid?: string
  bairro_uuid?: string
  logradouro?: string
  numero?: string
  complemento?: string
  cep?: string
}

/** Exatamente um de `ticket_type_uuid`/`event_product_uuid` por item — mesma regra do backend. */
export interface StorefrontCheckoutItemPayload {
  ticket_type_uuid?: string
  event_product_uuid?: string
  quantity: number
  /** Observação por item, até 200 caracteres, distinta do `notes` geral do pedido. */
  notes?: string
}

/** Roadmap retirada na loja — `delivery` (padrão, retrocompatível) entrega no endereço; `pickup` dispensa endereço, cliente busca na loja. */
export type StorefrontFulfillmentType = 'delivery' | 'pickup'

export interface StorefrontCheckoutPayload extends StorefrontCheckoutAddress {
  items: StorefrontCheckoutItemPayload[]
  client_name: string
  client_last_name: string
  client_phone: string
  notes?: string
  /** Delivery Fase 3 — código do cupom aplicado com sucesso na prévia (`validateStorefrontCoupon`); omitido = sem cupom. */
  coupon_code?: string
  /** Omitido = tratado como `'delivery'` no backend (retrocompatível). */
  fulfillment_type?: StorefrontFulfillmentType
  /** Meio de pagamento pretendido — só formato validado no backend; usado sobretudo para checar cupom restrito por meio de pagamento (`allowed_payment_methods`). */
  payment_method?: PaymentMethod
  /** Só relevante quando `payment_method: 'cash'` — cliente vai pagar em dinheiro e precisa de troco. */
  needs_change?: boolean
  /** Obrigatório numericamente quando `needs_change: true` — valor da nota/cédula que o cliente vai usar para pagar. */
  change_for_amount?: number
}

/** `POST /loja/{slug}/checkout` devolve só `{ order: { uuid } }` — o frontend redireciona pro rastreio público, não cria tela de status própria. */
export interface StorefrontCheckoutResult {
  order: { uuid: string }
}

/** Código de erro específico quando `tenant_settings.block_order_without_stock` está ligado e falta estoque. */
export const INSUFFICIENT_STOCK_CODE = 'INSUFFICIENT_STOCK'

/** Delivery Fase 2 — 3 códigos novos de erro do checkout (`StorefrontCheckoutController`), mesma família do `INSUFFICIENT_STOCK_CODE` acima. */
export const STORE_CLOSED_CODE = 'STORE_CLOSED'
export const BELOW_MINIMUM_ORDER_CODE = 'BELOW_MINIMUM_ORDER'
export const DELIVERY_AREA_NOT_SERVED_CODE = 'DELIVERY_AREA_NOT_SERVED'

/** Roadmap retirada na loja — cliente pediu `fulfillment_type: 'pickup'` mas o tenant não habilitou ou não tem endereço de loja cadastrado. */
export const STORE_PICKUP_UNAVAILABLE_CODE = 'STORE_PICKUP_UNAVAILABLE'

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
