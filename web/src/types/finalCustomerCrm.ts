/**
 * CRM básico do comprador (roadmap Fase 6) — agregação de dados já
 * existentes (FinalCustomer/FinalCustomerTenantLink/Sale), não um CRM
 * completo: sem nota/tags customizadas (não existem no schema). Ver
 * `FinalCustomerCrmResource` no backend.
 */
export interface FinalCustomerCrmEntry {
  uuid: string
  final_customer_uuid: string
  name: string | null
  last_name: string | null
  email: string
  phone_primary: string | null
  total_spent: number
  purchase_count: number
  last_purchase_at: string | null
}

/** Filtros de segmentação básica — combináveis entre si, aplicados no backend. */
export interface FinalCustomerCrmFilters {
  search?: string
  min_spent?: number
  min_purchases?: number
  inactive_days?: number
  per_page?: number
  page?: number
}
