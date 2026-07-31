import { unwrap } from './apiClient'
import { accountingApiClient } from './accountingApiClient'
import type { ApiSuccess } from '../types/api'
import type {
  AccountingAuthTokens,
  AccountingLoginPayload,
  AccountingOffice,
  ConfirmTotpPayload,
  RegisterAccountingPayload,
  RegisterAccountingResult,
} from '../types/accounting'

/** `POST /accounting/register` — cria o escritório e devolve o secret TOTP pra confirmar. */
export function register(payload: RegisterAccountingPayload): Promise<RegisterAccountingResult> {
  return unwrap(
    accountingApiClient.post<ApiSuccess<RegisterAccountingResult>>('/accounting/register', payload),
  )
}

/** `POST /accounting/totp/confirm` — valida o 1º código e habilita o login. */
export function confirmTotp(payload: ConfirmTotpPayload): Promise<void> {
  return unwrap(
    accountingApiClient.post<ApiSuccess<null>>('/accounting/totp/confirm', payload),
  ).then(() => undefined)
}

/** `POST /accounting/login` — email + senha + código TOTP atual. */
export function login(payload: AccountingLoginPayload): Promise<AccountingAuthTokens> {
  return unwrap(
    accountingApiClient.post<ApiSuccess<AccountingAuthTokens>>('/accounting/login', payload),
  )
}

/** `GET /accounting/me`. */
export function me(): Promise<AccountingOffice> {
  return unwrap(accountingApiClient.get<ApiSuccess<AccountingOffice>>('/accounting/me'))
}
