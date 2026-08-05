import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type {
  CreateScheduledReportSubscriptionPayload,
  ScheduledReportSubscription,
} from '../types/scheduledReportSubscription'

export function listScheduledReportSubscriptions(): Promise<ScheduledReportSubscription[]> {
  return unwrap(apiClient.get<ApiSuccess<ScheduledReportSubscription[]>>('/reports/scheduled-report-subscriptions'))
}

export function createScheduledReportSubscription(
  payload: CreateScheduledReportSubscriptionPayload,
): Promise<ScheduledReportSubscription> {
  return unwrap(apiClient.post<ApiSuccess<ScheduledReportSubscription>>('/reports/scheduled-report-subscriptions', payload))
}

export function cancelScheduledReportSubscription(uuid: string): Promise<void> {
  return apiClient.delete(`/reports/scheduled-report-subscriptions/${uuid}`).then(() => undefined)
}
