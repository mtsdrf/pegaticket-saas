import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { ChannelResultPoint, OperationHealthSummary, ReportCharts, ReportIndicators } from '../types/report'

export interface ReportPeriodParams {
  date_from?: string
  date_to?: string
}

export function getIndicators(params: ReportPeriodParams = {}): Promise<ReportIndicators> {
  return unwrap(apiClient.get<ApiSuccess<ReportIndicators>>('/reports/indicators', { params }))
}

export function getCharts(params: ReportPeriodParams = {}): Promise<ReportCharts> {
  return unwrap(apiClient.get<ApiSuccess<ReportCharts>>('/reports/charts', { params }))
}

export function getOperationHealth(): Promise<OperationHealthSummary> {
  return unwrap(apiClient.get<ApiSuccess<OperationHealthSummary>>('/reports/operation-health'))
}

/** Resultado por canal (roadmap A1.3) — agregação por `orders.origin`. */
export function getByChannel(params: ReportPeriodParams = {}): Promise<ChannelResultPoint[]> {
  return unwrap(apiClient.get<ApiSuccess<ChannelResultPoint[]>>('/reports/by-channel', { params }))
}
