import type { PaymentMethod } from '../constants/paymentMethods'
import type { StorefrontCatalogLayout } from './storefront'

export interface TenantSettings {
  uuid: string
  /** Formas de pagamento aceitas (`cash|pix|credit_card|debit_card`); `[]` = nenhuma configurada. */
  accepted_payment_methods: PaymentMethod[]
  /** Como a empresa recebe os pagamentos combinados fora do gateway da plataforma. */
  payment_receiving_method: 'manual' | 'pix_key' | 'pagbank_token'
  /** Chave Pix da própria empresa, quando ela escolhe receber por Pix direto. */
  payment_pix_key: string | null
  /** Modo de integração PagBank da empresa. */
  pagbank_integration_mode: 'disabled' | 'manual_token' | 'connect'
  /** Ambiente PagBank da empresa. */
  pagbank_environment: 'sandbox' | 'production'
  /** Indica se já existe token PagBank salvo para a empresa. */
  has_pagbank_access_token: boolean
  /** Conta recebedora PagBank da empresa para split/custódia. */
  pagbank_receiver_account_id: string | null
  /** Quando desligado, a página pública da empresa continua exibindo contato/reservas, mas o catálogo e o checkout deixam de ficar disponíveis. */
  storefront_enabled: boolean
  /** Layout do catálogo público — `list` (padrão) ou `grid` (cards com foto grande). */
  catalog_layout: StorefrontCatalogLayout
}

export interface UpdateTenantSettingsPayload {
  accepted_payment_methods: PaymentMethod[]
  payment_receiving_method: 'manual' | 'pix_key' | 'pagbank_token'
  payment_pix_key: string | null
  pagbank_integration_mode: 'disabled' | 'manual_token' | 'connect'
  pagbank_environment: 'sandbox' | 'production'
  pagbank_access_token?: string | null
  pagbank_receiver_account_id: string | null
  storefront_enabled: boolean
  catalog_layout: StorefrontCatalogLayout
}
