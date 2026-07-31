import { apiClient, unwrap } from './apiClient'
import { listPaginated } from './crudService'
import type { ApiSuccess } from '../types/api'
import type {
  StockAdjustmentPayload,
  StockBalance,
  StockBalanceFilters,
  StockMovement,
  StockMovementActionType,
  StockMovementBasePayload,
  StockMovementFilters,
  StockReserveCancelPayload,
  StockTransferPayload,
} from '../types/stock'
import type { PaginatedResult } from '../types/pagination'

export function listStockBalances(filters: StockBalanceFilters): Promise<PaginatedResult<StockBalance>> {
  return listPaginated<StockBalance>('/stock/balances', filters)
}

export function listStockMovements(filters: StockMovementFilters): Promise<PaginatedResult<StockMovement>> {
  return listPaginated<StockMovement>('/stock/movements', filters)
}

export function createStockMovement(
  type: Exclude<StockMovementActionType, 'adjustment' | 'transfer' | 'reserve_cancel'>,
  payload: StockMovementBasePayload,
): Promise<StockMovement> {
  return unwrap(apiClient.post<ApiSuccess<StockMovement>>(`/stock/movements/${type}`, payload))
}

export function createStockAdjustment(payload: StockAdjustmentPayload): Promise<StockMovement> {
  return unwrap(apiClient.post<ApiSuccess<StockMovement>>('/stock/movements/adjustment', payload))
}

export function createStockTransfer(payload: StockTransferPayload): Promise<StockMovement> {
  return unwrap(apiClient.post<ApiSuccess<StockMovement>>('/stock/movements/transfer', payload))
}

export function createReserveCancel(payload: StockReserveCancelPayload): Promise<StockMovement> {
  return unwrap(apiClient.post<ApiSuccess<StockMovement>>('/stock/movements/reserve-cancel', payload))
}
