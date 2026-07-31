import { unwrap } from './apiClient'
import { portalApiClient } from './portalApiClient'
import type { ApiSuccess } from '../types/api'
import type { CashbackBalance, CashbackBalanceByStore } from '../types/cashback'

/** Saldo de UMA loja — usado no checkout, que já sabe seu próprio slug. */
export function getCashbackBalance(tenantSlug: string): Promise<CashbackBalance> {
  return unwrap(
    portalApiClient.get<ApiSuccess<CashbackBalance>>('/portal/cashback', { params: { tenant_slug: tenantSlug } }),
  )
}

/** Saldo de TODAS as lojas com vínculo confirmado — extrato do Portal. */
export function listCashbackBalances(): Promise<CashbackBalanceByStore[]> {
  return unwrap(portalApiClient.get<ApiSuccess<CashbackBalanceByStore[]>>('/portal/cashback'))
}
