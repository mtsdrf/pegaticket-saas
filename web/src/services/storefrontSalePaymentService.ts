import { unwrap } from './apiClient'
import { publicApiClient } from './publicApiClient'
import type { ApiSuccess } from '../types/api'
import type {
  SalePayment,
  SalePaymentChargePayload,
  SalePaymentCheckoutConfig,
  SalePaymentInstallmentOptions,
} from '../types/sale'

export function createSalePixCharge(saleUuid: string): Promise<SalePayment> {
  return createSalePaymentCharge(saleUuid, { method: 'pix' })
}

export function getSalePaymentCheckoutConfig(saleUuid: string): Promise<SalePaymentCheckoutConfig> {
  return unwrap(
    publicApiClient.get<ApiSuccess<SalePaymentCheckoutConfig>>(`/rastreio/${saleUuid}/payment-checkout-config`),
  )
}

export function getSalePaymentInstallmentOptions(
  saleUuid: string,
  creditCardBin: string,
): Promise<SalePaymentInstallmentOptions> {
  return unwrap(
    publicApiClient.get<ApiSuccess<SalePaymentInstallmentOptions>>(`/rastreio/${saleUuid}/payment-installment-options`, {
      params: {
        credit_card_bin: creditCardBin,
        max_installments: 12,
        max_installments_no_interest: 1,
      },
    }),
  )
}

export function createSalePaymentCharge(saleUuid: string, payload: SalePaymentChargePayload): Promise<SalePayment> {
  return unwrap(publicApiClient.post<ApiSuccess<SalePayment>>(`/rastreio/${saleUuid}/payment-charge`, payload))
}
