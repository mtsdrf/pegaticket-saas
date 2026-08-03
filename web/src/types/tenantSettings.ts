import type { PaymentMethod } from '../constants/paymentMethods'
import type { StorefrontCatalogLayout } from './storefront'

export interface TenantSettings {
  uuid: string
  /** Formas de pagamento aceitas (`cash|pix|credit_card|debit_card`); `[]` = nenhuma configurada. */
  accepted_payment_methods: PaymentMethod[]
  /** Como a empresa recebe os pagamentos combinados fora do gateway da plataforma. */
  payment_receiving_method: 'manual' | 'pix_key'
  /** Chave Pix da própria empresa, quando ela escolhe receber por Pix direto. */
  payment_pix_key: string | null
  /** Quando desligado, a página pública da empresa continua exibindo contato/reservas, mas o catálogo e o checkout deixam de ficar disponíveis. */
  storefront_enabled: boolean
  /** Layout do catálogo público — `list` (padrão) ou `grid` (cards com foto grande). */
  catalog_layout: StorefrontCatalogLayout
}

export interface UpdateTenantSettingsPayload {
  accepted_payment_methods: PaymentMethod[]
  payment_receiving_method: 'manual' | 'pix_key'
  payment_pix_key: string | null
  storefront_enabled: boolean
  catalog_layout: StorefrontCatalogLayout
}
