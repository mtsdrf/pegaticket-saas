import { listPaginated } from './crudService'
import type { AuditLog } from '../types/admin'
import type { PaginatedResult } from '../types/pagination'

export function listAuditLogs(params: object): Promise<PaginatedResult<AuditLog>> {
  return listPaginated<AuditLog>('/audit-logs', params)
}
