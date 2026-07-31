import { listPaginated } from './crudService'
import type { PaginatedResult } from '../types/pagination'
import type { FinalCustomerSearchResult } from '../types/finalCustomer'

/** Busca staff-facing de compradores (`GET /final-customers`) — nome/sobrenome/email/cpf_cnpj/phone_primary, tenant-scoped via middleware `tenant`. */
export function searchFinalCustomers(search: string, perPage = 20): Promise<PaginatedResult<FinalCustomerSearchResult>> {
  return listPaginated<FinalCustomerSearchResult>('/final-customers', { search: search || undefined, per_page: perPage })
}
