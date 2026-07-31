import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { Order } from '../types/order'
import type {
  CashMovement,
  CashSession,
  CloseCashSessionPayload,
  CreatePdvSalePayload,
  Operator,
  PdvOfflineSnapshot,
  OpenCashSessionPayload,
  OperatorSessionPayload,
  RegisterCashMovementPayload,
  SetOperatorPinPayload,
} from '../types/pdv'

/** Histórico de sessões de caixa do tenant (mais recentes primeiro no backend). */
export function listCashSessions(): Promise<CashSession[]> {
  return unwrap(apiClient.get<ApiSuccess<CashSession[]>>('/pdv/cash-sessions'))
}

/** Sessão de caixa aberta atual — `null` quando não há caixa aberto. */
export function getCurrentCashSession(): Promise<CashSession | null> {
  return unwrap(apiClient.get<ApiSuccess<CashSession | null>>('/pdv/cash-sessions/current'))
}

/** Abre o caixa. Sem `cash_register_uuid`, o backend resolve/cria o caixa default do tenant. */
export function openCashSession(payload: OpenCashSessionPayload): Promise<CashSession> {
  return unwrap(apiClient.post<ApiSuccess<CashSession>>('/pdv/cash-sessions', payload))
}

/** Sangria (`withdrawal`) ou suprimento (`supply`) numa sessão aberta. */
export function registerCashMovement(
  sessionUuid: string,
  payload: RegisterCashMovementPayload,
): Promise<CashMovement> {
  return unwrap(
    apiClient.post<ApiSuccess<CashMovement>>(`/pdv/cash-sessions/${sessionUuid}/movements`, payload),
  )
}

/**
 * Fecha o caixa com o valor contado (`closing_amount_declared`). O backend
 * calcula `closing_amount_expected` e a `difference` — não há preview
 * client-side do esperado.
 */
export function closeCashSession(
  sessionUuid: string,
  payload: CloseCashSessionPayload,
): Promise<CashSession> {
  return unwrap(apiClient.post<ApiSuccess<CashSession>>(`/pdv/cash-sessions/${sessionUuid}/close`, payload))
}

/**
 * Cria a venda completa (Order + Payments declarados). Usa a sessão aberta
 * atual quando `cash_session_uuid` é omitido. Erros a tratar no chamador:
 * `CASH_SESSION_ERROR` (422, sem caixa) e `PAYMENT_AMOUNT_MISMATCH` (422, soma
 * dos pagamentos ≠ total). Devolve o `OrderResource` completo.
 */
export function createPdvSale(payload: CreatePdvSalePayload): Promise<Order> {
  return unwrap(apiClient.post<ApiSuccess<Order>>('/pdv/sales', payload))
}

/** Snapshot dedicado do PDV para operação offline controlada. */
export function getOfflineSnapshot(): Promise<PdvOfflineSnapshot> {
  return unwrap(apiClient.get<ApiSuccess<PdvOfflineSnapshot>>('/pdv/offline-snapshot'))
}

/** Cadastra/troca o PIN (4-6 dígitos) do próprio operador logado, no tenant atual. Erro `INVALID_PIN` (422) quando já em uso por outro operador. */
export function setOperatorPin(payload: SetOperatorPinPayload): Promise<void> {
  return apiClient.put('/pdv/operator-pin', payload).then(() => undefined)
}

/** Resolve qual operador do tenant tem este PIN — usado pelo diálogo "Quem está operando?". Erro `INVALID_PIN` (422) quando o PIN não bate com nenhum operador. */
export function resolveOperator(payload: OperatorSessionPayload): Promise<Operator> {
  return unwrap(apiClient.post<ApiSuccess<Operator>>('/pdv/operator-session', payload))
}
