import type { PaymentMethod } from '../constants/paymentMethods'
import type { StorefrontCatalogLayout } from './storefront'

export interface TenantSettings {
  uuid: string
  send_tracking_link_whatsapp: boolean
  /** Delivery Fase 1 — quando ligado, `OrderService::create()` bloqueia (422 `INSUFFICIENT_STOCK`) pedido sem estoque suficiente; hoje só tem efeito prático nos pedidos da loja pública. */
  block_order_without_stock: boolean
  /** Delivery Fase 2 — `null` = sem pedido mínimo configurado (não bloqueia nada no checkout da loja). */
  minimum_order_value: number | null
  /** Delivery Fase 2 — `null` = sem estimativa configurada (catálogo não exibe "~XX min"). */
  estimated_preparation_minutes: number | null
  /** Reforma da loja — formas de pagamento aceitas (`cash|pix|credit_card|debit_card`); `[]` = nenhuma configurada. */
  accepted_payment_methods: PaymentMethod[]
  /** Como a empresa recebe os pagamentos combinados fora do gateway da plataforma. */
  payment_receiving_method: 'manual' | 'pix_key'
  /** Chave Pix da própria empresa, quando ela escolhe receber por Pix direto. */
  payment_pix_key: string | null
  /** Roadmap retirada na loja — desligado por padrão; checkout só aceita `fulfillment_type: 'pickup'` quando ligado E o tenant tem endereço de loja cadastrado. */
  allow_store_pickup: boolean
  /** Par de `allow_store_pickup` — desligar impede o cliente de escolher "receber em casa" no checkout da loja. */
  allow_delivery: boolean
  /** Quando desligado, a página pública da empresa continua exibindo contato/reservas, mas o catálogo e o checkout deixam de ficar disponíveis. */
  storefront_enabled: boolean
  /** Layout do catálogo da loja pública — `list` (padrão) ou `grid` (cards com foto grande). */
  catalog_layout: StorefrontCatalogLayout
}

export interface UpdateTenantSettingsPayload {
  send_tracking_link_whatsapp: boolean
  block_order_without_stock: boolean
  minimum_order_value: number | null
  estimated_preparation_minutes: number | null
  accepted_payment_methods: PaymentMethod[]
  payment_receiving_method: 'manual' | 'pix_key'
  payment_pix_key: string | null
  allow_store_pickup: boolean
  allow_delivery: boolean
  storefront_enabled: boolean
  catalog_layout: StorefrontCatalogLayout
}
