import { listPaginated } from './crudService'
import type { FinalCustomerCrmEntry, FinalCustomerCrmFilters } from '../types/finalCustomerCrm'
import type { PaginatedResult } from '../types/pagination'

export function listFinalCustomersCrm(filters: FinalCustomerCrmFilters): Promise<PaginatedResult<FinalCustomerCrmEntry>> {
  return listPaginated<FinalCustomerCrmEntry>('/final-customers/crm', filters)
}
