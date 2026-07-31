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
  allow_table_reservations: boolean
  storefront_enabled: boolean
  /** Layout do catálogo escolhido pela empresa (`tenant_settings.catalog_layout`) — `grid` (cards com foto grande) ou `list` (padrão, lista compacta com imagem à direita). */
  catalog_layout: StorefrontCatalogLayout
}

/** `grid` (cards com foto grande, 1:1) ou `list` (padrão — lista compacta com imagem à direita). */
export type StorefrontCatalogLayout = 'grid' | 'list'

export interface StorefrontProductTypeRef {
  name: string
  product_category: { name: string } | null
}

export interface StorefrontProductOption {
  uuid: string
  name: string
  description: string | null
  price: number
  sort_order: number
}

/** `addon` (default) soma preço ao escolher; `ingredient_removal` é usado para desmarcar um ingrediente do produto (preço tipicamente 0). */
export type StorefrontProductOptionGroupKind = 'addon' | 'ingredient_removal'

export interface StorefrontProductOptionGroup {
  uuid: string
  name: string
  description: string | null
  kind: StorefrontProductOptionGroupKind
  min_select: number
  max_select: number
  sort_order: number
  options: StorefrontProductOption[]
}

/** Selos calculados pelo backend em `StorefrontCatalogService::paginateProducts()` — array vazio quando nenhum se aplica. */
export type StorefrontProductBadge = 'new' | 'best_selling' | 'low_stock'

/** `GET /loja/{slug}/produtos` — allow-list estrita (`StorefrontProductResource`), nunca custo/estoque. */
export interface StorefrontProduct {
  uuid: string
  name: string
  description: string | null
  price: number
  /** Delivery Fase 3 — preço promocional ativo (`ProductPromotion`), quando houver. `null` = sem promoção vigente, exibe só `price`. */
  promo_price: number | null
  /** Roadmap Loja — preço de atacado por quantidade. Os dois nulos = sem atacado configurado; promoção sempre vence sobre atacado. */
  wholesale_min_quantity: number | null
  wholesale_price: number | null
  image_url: string | null
  is_available: boolean
  /**
   * Delivery Fase 4 — só reflete o cliente final logado quando o Bearer do
   * portal é enviado em `GET /loja/{slug}/produtos` (`customer.jwt.optional`
   * no backend); `false` para visitante anônimo. Mesmo campo, sempre `true`,
   * em `GET /portal/favorites` (é a própria lista de favoritos).
   */
  is_favorited: boolean
  /** Selos ('new'|'best_selling'|'low_stock') — [] quando o endpoint não passa por `paginateProducts()` (ex: detalhe avulso), nunca `null`. */
  badges: StorefrontProductBadge[]
  option_groups?: StorefrontProductOptionGroup[]
  product_type?: StorefrontProductTypeRef
}

/** Vitrine estilo iFood — `relevance` (default, mesmo comportamento de sempre) é a única ordenação sem custo extra de query; as outras 3 usam preço efetivo/vendas dos últimos 90 dias no backend (`StorefrontCatalogService::applySort`). */
export type StorefrontSortOption = 'relevance' | 'price_asc' | 'price_desc' | 'best_selling'

export interface StorefrontProductFilters {
  name?: string
  product_type_uuid?: string
  product_category_uuid?: string
  /** Delivery Fase 3 — filtra só produtos com promoção ativa no momento. */
  on_promotion?: boolean
  sort?: StorefrontSortOption
  per_page?: number
  page?: number
}

/** `GET /loja/{slug}/categorias` — só categorias com produto disponível, ordenadas por priority no backend. */
export interface StorefrontCategory {
  uuid: string
  name: string
}

export interface StorefrontListResult {
  items: StorefrontProduct[]
  pagination: PaginationMeta
}

/** Item do carrinho local (`localStorage`, por slug — ver `constants/storage.ts`). */
export interface StorefrontCartItem {
  id: string
  product_uuid: string
  name: string
  configuration_label?: string | null
  /** Preço efetivo corrente — recalculado por `computeUnitPrice()` a cada mudança de quantidade (promoção > atacado > base). */
  unit_price: number
  /** Snapshot dos preços do produto no momento de adicionar (config de produto, não muda por sessão) — base do recálculo de atacado. */
  price: number
  promo_price: number | null
  wholesale_min_quantity: number | null
  wholesale_price: number | null
  image_url: string | null
  quantity: number
  /** Observação livre do cliente sobre este item (ex: "sem cebola") — até 200 caracteres, enviada como `items[].notes` no checkout. */
  notes?: string | null
  options?: Array<{
    product_option_uuid: string
    group_name: string
    name: string
    quantity: number
    unit_price: number
  }>
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

export interface StorefrontCheckoutItemPayload {
  product_uuid: string
  quantity: number
  /** Observação por item (ex: "sem cebola") — distinta do `notes` geral do pedido, até 200 caracteres. */
  notes?: string
  options?: Array<{
    product_option_uuid: string
    quantity?: number
  }>
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
