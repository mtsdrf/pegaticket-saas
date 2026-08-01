import type { PaymentMethod } from '../constants/paymentMethods'
import type { StorefrontCatalogLayout } from './storefront'

export interface TenantSettings {
  uuid: string
  send_tracking_link_whatsapp: boolean
  /** Quando ligado, `SaleService::create()` bloqueia (422 `INSUFFICIENT_STOCK`) pedido sem disponibilidade suficiente; hoje só tem efeito prático nas vendas do canal online. */
  block_order_without_stock: boolean
  /** `null` = sem valor mínimo configurado (não bloqueia nada no checkout online). */
  minimum_order_value: number | null
  /** `null` = sem estimativa configurada. */
  estimated_preparation_minutes: number | null
  /** Formas de pagamento aceitas (`cash|pix|credit_card|debit_card`); `[]` = nenhuma configurada. */
  accepted_payment_methods: PaymentMethod[]
  /** Como a empresa recebe os pagamentos combinados fora do gateway da plataforma. */
  payment_receiving_method: 'manual' | 'pix_key'
  /** Chave Pix da própria empresa, quando ela escolhe receber por Pix direto. */
  payment_pix_key: string | null
  /** Retirada presencial — desligada por padrão; checkout só aceita `fulfillment_type: 'pickup'` quando ligado. */
  allow_store_pickup: boolean
  /** Quando desligado, a página pública da empresa continua exibindo contato/reservas, mas o catálogo e o checkout deixam de ficar disponíveis. */
  storefront_enabled: boolean
  /** Layout do catálogo público — `list` (padrão) ou `grid` (cards com foto grande). */
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
  storefront_enabled: boolean
  catalog_layout: StorefrontCatalogLayout
}
