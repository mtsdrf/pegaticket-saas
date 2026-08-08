/** Espelha `PlatformFinanceSettingsResource` (backend) — configuração global
 * (sem tenant), rota exclusiva de staff da PegaTicket (`payment_admin`). */
export type SettlementReference = 'event_end' | 'sale_date'

/** Chave = número de parcelas (`"1"`..`"12"`) como string, valor = percentual estimado. */
export type CardInstallmentProcessingMap = Record<string, number>

export interface PlatformFinanceSettings {
  uuid: string
  platform_fee_fixed_amount: number
  default_settlement_offset_days: number
  settlement_reference: SettlementReference
  split_custody_enabled: boolean
  extra_reserve_enabled: boolean
  extra_reserve_percentage: number
  extra_reserve_release_offset_days: number
  pagbank_primary_account_id: string | null
  service_fee_percentage: number
  service_fee_minimum_amount: number
  service_fee_rule_version: number
  estimated_pix_processing_percentage: number | null
  estimated_card_processing_percentage_by_installment: CardInstallmentProcessingMap | null
}

/** `PUT` é sempre completo — todos os campos obrigatórios exceto os 3 nullable. */
export type PlatformFinanceSettingsPayload = Omit<PlatformFinanceSettings, 'uuid' | 'service_fee_rule_version'>

export const SETTLEMENT_REFERENCE_OPTIONS: { value: SettlementReference; label: string }[] = [
  { value: 'event_end', label: 'Fim do evento' },
  { value: 'sale_date', label: 'Data da venda' },
]
