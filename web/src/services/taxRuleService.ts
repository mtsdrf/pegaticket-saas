import { apiClient, unwrap } from './apiClient'
import type { ApiSuccess } from '../types/api'
import type { TaxRule, TaxRulePayload } from '../types/taxRule'

export function listTaxRules(): Promise<TaxRule[]> {
  return unwrap(apiClient.get<ApiSuccess<TaxRule[]>>('/tax-rules'))
}

export async function getTaxRule(uuid: string): Promise<TaxRule> {
  const rules = await listTaxRules()
  const found = rules.find((rule) => rule.uuid === uuid)
  if (!found) throw new Error('Regra tributária não encontrada.')
  return found
}

export function createTaxRule(payload: TaxRulePayload): Promise<TaxRule> {
  return unwrap(apiClient.post<ApiSuccess<TaxRule>>('/tax-rules', payload))
}

export function updateTaxRule(uuid: string, payload: TaxRulePayload): Promise<TaxRule> {
  return unwrap(apiClient.put<ApiSuccess<TaxRule>>(`/tax-rules/${uuid}`, payload))
}

export function deleteTaxRule(uuid: string): Promise<void> {
  return apiClient.delete(`/tax-rules/${uuid}`).then(() => undefined)
}
