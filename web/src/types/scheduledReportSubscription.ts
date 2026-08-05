export type ScheduledReportFrequency = 'daily' | 'weekly'

export interface ScheduledReportSubscription {
  uuid: string
  recipient_email: string
  frequency: ScheduledReportFrequency
  last_sent_at: string | null
  created_at: string
}

export interface CreateScheduledReportSubscriptionPayload {
  recipient_email: string
  frequency: ScheduledReportFrequency
}
