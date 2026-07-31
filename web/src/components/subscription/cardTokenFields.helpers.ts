import type { MercadoPagoInstance } from '../../hooks/useMercadoPagoSdk'

export interface CardTokenFormState {
  cardNumber: string
  cardholderName: string
  expirationMonth: string
  expirationYear: string
  securityCode: string
  identificationType: string
  identificationNumber: string
}

export const EMPTY_CARD_TOKEN_FORM: CardTokenFormState = {
  cardNumber: '',
  cardholderName: '',
  expirationMonth: '',
  expirationYear: '',
  securityCode: '',
  identificationType: 'CPF',
  identificationNumber: '',
}

/** Monta o payload esperado por `MercadoPagoInstance.createCardToken` a partir do formulário controlado. */
export function buildCardTokenPayload(
  form: CardTokenFormState,
): Parameters<MercadoPagoInstance['createCardToken']>[0] {
  return {
    cardNumber: form.cardNumber.replaceAll(/\s/g, ''),
    cardholderName: form.cardholderName.trim(),
    cardExpirationMonth: form.expirationMonth,
    cardExpirationYear: form.expirationYear,
    securityCode: form.securityCode,
    identificationType: form.identificationType,
    identificationNumber: form.identificationNumber.replaceAll(/\D/g, ''),
  }
}
