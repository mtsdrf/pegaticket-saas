import { accountingApiClient } from './accountingApiClient'
import { unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { TaxRule, TaxRulePayload } from '../types/taxRule'

export function listAccountingTaxRules(tenantUuid: string): Promise<TaxRule[]> {
  return unwrap(accountingApiClient.get<ApiSuccess<TaxRule[]>>(`/accounting/tenants/${tenantUuid}/tax-rules`))
}

export function createAccountingTaxRule(tenantUuid: string, payload: TaxRulePayload): Promise<TaxRule> {
  return unwrap(accountingApiClient.post<ApiSuccess<TaxRule>>(`/accounting/tenants/${tenantUuid}/tax-rules`, payload))
}

export function updateAccountingTaxRule(tenantUuid: string, uuid: string, payload: TaxRulePayload): Promise<TaxRule> {
  return unwrap(accountingApiClient.put<ApiSuccess<TaxRule>>(`/accounting/tenants/${tenantUuid}/tax-rules/${uuid}`, payload))
}

export function deleteAccountingTaxRule(tenantUuid: string, uuid: string): Promise<void> {
  return accountingApiClient.delete(`/accounting/tenants/${tenantUuid}/tax-rules/${uuid}`).then(() => undefined)
}
