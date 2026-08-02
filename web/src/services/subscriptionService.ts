import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type { PaginatedResult } from '../types/pagination'
import type {
  CancelSubscriptionPayload,
  ChangePlanPayload,
  CreateSubscriptionPayload,
  PlanPricing,
  RefundItem,
  Subscription,
  SubscriptionHistoryItem,
  SubscriptionInvoice,
  SubscriptionInvoicePayment,
  UpdatePaymentMethodPayload,
  WithdrawalResult,
} from '../types/subscription'

/**
 * Assinatura atual do tenant + faturas. O backend responde `data: null`
 * (200) quando o tenant ainda não tem assinatura — `unwrap` devolve `null`,
 * a tela trata o estado vazio.
 */
export function getSubscription(): Promise<Subscription | null> {
  return unwrap(apiClient.get<ApiSuccess<Subscription | null>>('/subscription'))
}

/**
 * 422 `SUBSCRIPTION_CARD_TOKEN_REQUIRED` quando o plano escolhido é pago e
 * `card_token` não foi enviado — o chamador deve então pedir o cartão
 * (`SubscriptionCardTokenDialog`) e reenviar com o token. Sem redirecionamento
 * a checkout do Mercado Pago: a cobrança recorrente já sai autorizada daqui.
 */
export function createSubscription(payload: CreateSubscriptionPayload): Promise<Subscription> {
  return unwrap(apiClient.post<ApiSuccess<Subscription>>('/subscription', payload))
}

export function cancelSubscription(payload: CancelSubscriptionPayload): Promise<void> {
  return apiClient.post('/subscription/cancel', payload).then(() => undefined)
}

/** Só funciona nos primeiros 7 dias (backend: `WITHDRAWAL_WINDOW_EXPIRED` fora da janela). */
export function requestWithdrawal(): Promise<WithdrawalResult> {
  return unwrap(apiClient.post<ApiSuccess<WithdrawalResult>>('/subscription/withdrawal', {}))
}

/** Reverte um cancelamento agendado (`cancel_scheduled` -> `active`). 422 `INVALID_SUBSCRIPTION_TRANSITION` se não aplicável. */
export function renewSubscription(): Promise<Subscription> {
  return unwrap(apiClient.post<ApiSuccess<Subscription>>('/subscription/renew', {}))
}

/** 422 `SUBSCRIPTION_NO_ACTIVE_PREAPPROVAL` quando não há cobrança recorrente automática (plano free/manual). */
export function updatePaymentMethod(payload: UpdatePaymentMethodPayload): Promise<Subscription> {
  return unwrap(apiClient.put<ApiSuccess<Subscription>>('/subscription/payment-method', payload))
}

/** Histórico paginado de faturas do tenant (todas as assinaturas ao longo do tempo, não só a atual). */
export function getInvoices(page: number, perPage = 15): Promise<PaginatedResult<SubscriptionInvoice>> {
  return listPaginated<SubscriptionInvoice>('/subscription/invoices', { page, per_page: perPage })
}

export function createInvoicePixCharge(invoiceUuid: string): Promise<SubscriptionInvoicePayment> {
  return unwrap(apiClient.post<ApiSuccess<SubscriptionInvoicePayment>>(`/subscription/invoices/${invoiceUuid}/pix-charge`, {}))
}

/**
 * Preço real por período de cobrança do plano ATUAL do tenant + funcionalidades
 * incluídas — fonte única de preço para a tela de contratação (substitui o
 * espelhamento manual que existia em `utils/subscriptionPricing.ts`/`data/plans.ts`).
 * 422 `SUBSCRIPTION_PLAN_REQUIRED` quando a empresa ainda não tem plano definido.
 */
export function getPlanPricing(): Promise<PlanPricing> {
  return unwrap(apiClient.get<ApiSuccess<PlanPricing>>('/subscription/plan-pricing'))
}

/**
 * Planos ativos disponíveis para troca (todos exceto o atual do tenant),
 * mesmo formato de preço/desconto de `getPlanPricing` — usado pela tela de
 * mudança de plano. 422 `SUBSCRIPTION_PLAN_REQUIRED` sem plano definido.
 */
export function getAvailablePlans(): Promise<PlanPricing[]> {
  return unwrap(apiClient.get<ApiSuccess<PlanPricing[]>>('/subscription/available-plans'))
}

/**
 * Troca o plano de uma assinatura já ativa. 422 `INVALID_SUBSCRIPTION_TRANSITION`
 * fora de `trialing`/`active`/`past_due`, `plan_unchanged` para o mesmo plano+período,
 * `SUBSCRIPTION_CARD_TOKEN_REQUIRED` quando o novo plano é pago sem `card_token`.
 */
export function changePlan(payload: ChangePlanPayload): Promise<Subscription> {
  return unwrap(apiClient.post<ApiSuccess<Subscription>>('/subscription/change-plan', payload))
}

/** Histórico paginado de TODAS as assinaturas do tenant ao longo do tempo (não só a atual). */
export function getSubscriptionHistory(page: number, perPage = 10): Promise<PaginatedResult<SubscriptionHistoryItem>> {
  return listPaginated<SubscriptionHistoryItem>('/subscription/history', { page, per_page: perPage })
}

/**
 * Histórico unificado de estornos do tenant (venda paga cancelada,
 * arrependimento de assinatura, contestação/chargeback) — visão só de
 * leitura, sem ação de solicitar novo estorno aqui.
 */
export function listRefunds(page: number, perPage = 10): Promise<PaginatedResult<RefundItem>> {
  return listPaginated<RefundItem>('/subscription/refunds', { page, per_page: perPage })
}
