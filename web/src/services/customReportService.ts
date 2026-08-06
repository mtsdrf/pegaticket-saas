import { apiClient } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type {
  CustomReportBuilderState,
  CustomReportDefinition,
  CustomReportExecutionResult,
  CustomReportPagination,
  CustomReportResultRow,
  CustomReportSchemaEntry,
} from '../types/customReports'

const BASE = '/reports/custom-report-definitions'

export async function getSchema(): Promise<CustomReportSchemaEntry[]> {
  const response = await apiClient.get<ApiSuccess<CustomReportSchemaEntry[]>>(`${BASE}/schema`)
  return response.data.data
}

export async function listDefinitions(perPage = 15): Promise<{ items: CustomReportDefinition[]; pagination: CustomReportPagination }> {
  const response = await apiClient.get<ApiSuccess<CustomReportDefinition[]>>(BASE, { params: { per_page: perPage } })
  return {
    items: response.data.data,
    pagination: response.data.meta.pagination as CustomReportPagination,
  }
}

export async function createDefinition(name: string, builder: CustomReportBuilderState): Promise<CustomReportDefinition> {
  const response = await apiClient.post<ApiSuccess<CustomReportDefinition>>(BASE, {
    name,
    data_source: builder.data_source,
    dimensions: builder.dimensions,
    metrics: builder.metrics,
    calculated_metrics: builder.calculated_metrics,
    filters: builder.filters,
  })
  return response.data.data
}

export async function deleteDefinition(uuid: string): Promise<void> {
  await apiClient.delete(`${BASE}/${uuid}`)
}

export async function executeDefinition(uuid: string, page: number, perPage: number): Promise<CustomReportExecutionResult> {
  const response = await apiClient.get<ApiSuccess<CustomReportResultRow[]>>(`${BASE}/${uuid}/execute`, {
    params: { page, per_page: perPage },
  })
  return {
    rows: response.data.data,
    pagination: response.data.meta.pagination as CustomReportPagination,
  }
}

export async function previewReport(
  builder: CustomReportBuilderState,
  page: number,
  perPage: number,
): Promise<CustomReportExecutionResult> {
  const response = await apiClient.post<ApiSuccess<CustomReportResultRow[]>>(`${BASE}/preview`, {
    data_source: builder.data_source,
    dimensions: builder.dimensions,
    metrics: builder.metrics,
    calculated_metrics: builder.calculated_metrics,
    filters: builder.filters,
    page,
    per_page: perPage,
  })
  return {
    rows: response.data.data,
    pagination: response.data.meta.pagination as CustomReportPagination,
  }
}
