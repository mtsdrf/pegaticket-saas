import type { PdvPaymentMethod } from '../types/pdv'

/**
 * Formas de pagamento do PDV — enum próprio do backend
 * (`CreatePdvSaleRequest`), distinto de `constants/paymentMethods.ts` (loja
 * online). Ordem canônica de exibição no modal de finalização.
 */
export const PDV_PAYMENT_METHODS: PdvPaymentMethod[] = ['cash', 'pix', 'credit', 'debit']

export const PDV_PAYMENT_METHOD_LABELS: Record<PdvPaymentMethod, string> = {
  cash: 'Dinheiro',
  pix: 'Pix',
  credit: 'Cartão de crédito',
  debit: 'Cartão de débito',
}
