/**
 * Formas de pagamento aceitas pela loja — espelha o enum do backend
 * (`accepted_payment_methods` em `tenant_settings`, `cash|pix|credit_card|
 * debit_card`). Fonte única compartilhada entre a configuração (checkboxes em
 * `PaymentBlock`, hub `/configuracoes`) e a exibição pública (chips em `StorefrontProfilePage`).
 */
export type PaymentMethod = 'cash' | 'pix' | 'credit_card' | 'debit_card'

/** Ordem canônica de exibição (checkboxes e chips seguem esta ordem). */
export const PAYMENT_METHODS: PaymentMethod[] = ['cash', 'pix', 'credit_card', 'debit_card']

/** Rótulo curto — usado nos chips do perfil público da loja. */
export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
  cash: 'Dinheiro',
  pix: 'Pix',
  credit_card: 'Cartão de crédito',
  debit_card: 'Cartão de débito',
}

/** Rótulo longo — usado nos checkboxes de configuração (deixa claro "na entrega"). */
export const PAYMENT_METHOD_SETTINGS_LABELS: Record<PaymentMethod, string> = {
  cash: 'Dinheiro',
  pix: 'Pix',
  credit_card: 'Cartão de crédito na entrega',
  debit_card: 'Cartão de débito na entrega',
}
