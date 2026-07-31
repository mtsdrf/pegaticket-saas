import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type {
  WebhookDelivery,
  WebhookSubscription,
  WebhookSubscriptionCreateResult,
  WebhookSubscriptionPayload,
} from '../types/webhookSubscription'

export function listWebhookSubscriptions(): Promise<WebhookSubscription[]> {
  return unwrap(apiClient.get<ApiSuccess<WebhookSubscription[]>>('/webhook-subscriptions'))
}

/** `secret` só vem nesta resposta — nunca mais recuperável depois (recriar a assinatura se perder). */
export function createWebhookSubscription(payload: WebhookSubscriptionPayload): Promise<WebhookSubscriptionCreateResult> {
  return unwrap(apiClient.post<ApiSuccess<WebhookSubscriptionCreateResult>>('/webhook-subscriptions', payload))
}

export function updateWebhookSubscription(uuid: string, payload: WebhookSubscriptionPayload): Promise<WebhookSubscription> {
  return unwrap(apiClient.put<ApiSuccess<WebhookSubscription>>(`/webhook-subscriptions/${uuid}`, payload))
}

export function deleteWebhookSubscription(uuid: string): Promise<void> {
  return apiClient.delete(`/webhook-subscriptions/${uuid}`).then(() => undefined)
}

export function listWebhookDeliveries(uuid: string): Promise<WebhookDelivery[]> {
  return unwrap(apiClient.get<ApiSuccess<WebhookDelivery[]>>(`/webhook-subscriptions/${uuid}/deliveries`))
}
