import { unwrap } from './apiClient'
import { accountingApiClient } from './accountingApiClient'
import type { ApiSuccess } from '../types/api'
import type {
  AccountingCashFlowReport,
  AccountingDreReport,
  AccountingMessage,
  AccountingOfficeTenantLink,
  AccountingReportKind,
  AccountingReportPeriod,
  AccountingSalesReport,
  CreateAccessRequestPayload,
  CreateAccountingMessagePayload,
} from '../types/accounting'

/** `POST /accounting/access-requests` — contador solicita acesso a um tenant por CNPJ. */
export function requestAccess(payload: CreateAccessRequestPayload): Promise<AccountingOfficeTenantLink> {
  return unwrap(
    accountingApiClient.post<ApiSuccess<AccountingOfficeTenantLink>>('/accounting/access-requests', payload),
  )
}

/** `GET /accounting/access-requests` — lista os vínculos do contador (todos os status). */
export function listMyLinks(): Promise<AccountingOfficeTenantLink[]> {
  return unwrap(
    accountingApiClient.get<ApiSuccess<AccountingOfficeTenantLink[]>>('/accounting/access-requests'),
  )
}

function buildPeriodParams(period: AccountingReportPeriod): Record<string, string> {
  const params: Record<string, string> = {}
  if (period.from) params.from = period.from
  if (period.to) params.to = period.to
  return params
}

/** `GET /accounting/tenants/{uuid}/reports/sales`. */
export function getSalesReport(
  tenantUuid: string,
  period: AccountingReportPeriod,
): Promise<AccountingSalesReport> {
  return unwrap(
    accountingApiClient.get<ApiSuccess<AccountingSalesReport>>(
      `/accounting/tenants/${tenantUuid}/reports/sales`,
      { params: buildPeriodParams(period) },
    ),
  )
}

/** `GET /accounting/tenants/{uuid}/reports/cash-flow`. */
export function getCashFlowReport(
  tenantUuid: string,
  period: AccountingReportPeriod,
): Promise<AccountingCashFlowReport> {
  return unwrap(
    accountingApiClient.get<ApiSuccess<AccountingCashFlowReport>>(
      `/accounting/tenants/${tenantUuid}/reports/cash-flow`,
      { params: buildPeriodParams(period) },
    ),
  )
}

/** `GET /accounting/tenants/{uuid}/reports/dre`. */
export function getDreReport(
  tenantUuid: string,
  period: AccountingReportPeriod,
): Promise<AccountingDreReport> {
  return unwrap(
    accountingApiClient.get<ApiSuccess<AccountingDreReport>>(
      `/accounting/tenants/${tenantUuid}/reports/dre`,
      { params: buildPeriodParams(period) },
    ),
  )
}

/**
 * Baixa o relatório em CSV (`?format=csv`, o backend transmite via
 * `streamDownload`). Como é um binário (não o envelope `{success,...}`), NÃO
 * passa por `unwrap`: busca como blob e dispara o download no navegador,
 * lendo o nome do arquivo do header `Content-Disposition` quando presente.
 */
export async function downloadReportCsv(
  tenantUuid: string,
  kind: AccountingReportKind,
  period: AccountingReportPeriod,
): Promise<void> {
  const response = await accountingApiClient.get<Blob>(
    `/accounting/tenants/${tenantUuid}/reports/${kind}`,
    { params: { ...buildPeriodParams(period), format: 'csv' }, responseType: 'blob' },
  )

  const disposition = response.headers['content-disposition'] as string | undefined
  const match = disposition?.match(/filename="?([^"]+)"?/)
  const filename = match?.[1] ?? `${kind}-${new Date().toISOString().slice(0, 10)}.csv`

  const url = window.URL.createObjectURL(response.data)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  window.URL.revokeObjectURL(url)
}

/** `GET /accounting/tenants/{uuid}/messages` — central de pendências (lado contador). */
export function listMessages(tenantUuid: string): Promise<AccountingMessage[]> {
  return unwrap(
    accountingApiClient.get<ApiSuccess<AccountingMessage[]>>(`/accounting/tenants/${tenantUuid}/messages`),
  )
}

/** `POST /accounting/tenants/{uuid}/messages` — envia mensagem (com anexo opcional). */
export function sendMessage(
  tenantUuid: string,
  payload: CreateAccountingMessagePayload,
): Promise<AccountingMessage> {
  const form = new FormData()
  form.append('body', payload.body)
  if (payload.due_date) form.append('due_date', payload.due_date)
  if (payload.attachment) form.append('attachment', payload.attachment)

  return unwrap(
    accountingApiClient.post<ApiSuccess<AccountingMessage>>(
      `/accounting/tenants/${tenantUuid}/messages`,
      form,
    ),
  )
}
