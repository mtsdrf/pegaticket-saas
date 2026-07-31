import type { PaginationMeta } from './pagination'

export interface StockProductRef {
  uuid: string
  name: string
  sku: string | null
}

export interface StockLocationRef {
  uuid: string
  name: string
}

export interface StockBalance {
  uuid: string
  quantity_on_hand: number
  quantity_reserved: number
  quantity_blocked: number
  quantity_available: number
  product?: StockProductRef
  location?: StockLocationRef
  updated_at: string
}

export interface StockMovement {
  uuid: string
  type: string
  quantity: number
  balance_before: number
  balance_after: number
  reason: string
  notes: string | null
  source_type: string | null
  source_id: number | null
  product?: StockProductRef
  location?: StockLocationRef
  destination_location?: StockLocationRef | null
  created_at: string
}

export interface StockBalanceFilters {
  product_uuid?: string
  location_uuid?: string
  product_name?: string
  location_name?: string
  quantity_on_hand_min?: number
  quantity_on_hand_max?: number
  quantity_reserved_min?: number
  quantity_reserved_max?: number
  quantity_blocked_min?: number
  quantity_blocked_max?: number
  quantity_available_min?: number
  quantity_available_max?: number
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface StockMovementFilters {
  product_uuid?: string
  location_uuid?: string
  product_name?: string
  location_name?: string
  type?: string
  reason?: string
  quantity_min?: number
  quantity_max?: number
  balance_after_min?: number
  balance_after_max?: number
  date_from?: string
  date_to?: string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export type StockMovementActionType =
  | 'entry'
  | 'exit'
  | 'adjustment'
  | 'transfer'
  | 'return'
  | 'loss'
  | 'block'
  | 'unblock'
  | 'reserve'
  | 'reserve_cancel'

export const STOCK_MOVEMENT_TYPE_LABELS: Record<string, string> = {
  entry: 'Entrada',
  exit: 'Saída',
  adjustment_positive: 'Ajuste positivo',
  adjustment_negative: 'Ajuste negativo',
  transfer: 'Transferência',
  return: 'Devolução',
  loss: 'Perda',
  block: 'Bloqueio',
  unblock: 'Desbloqueio',
  reserve: 'Reserva',
  reserve_cancel: 'Cancelamento de reserva',
}

export interface StockMovementBasePayload {
  product_uuid: string
  location_uuid: string
  quantity: number
  reason: string
  notes?: string
}

export interface StockAdjustmentPayload extends StockMovementBasePayload {
  direction: 'increase' | 'decrease'
}

export interface StockTransferPayload extends StockMovementBasePayload {
  destination_location_uuid: string
}

export interface StockReserveCancelPayload {
  movement_uuid: string
  notes?: string
}

export interface StockPaginatedResult<T> {
  items: T[]
  pagination: PaginationMeta
}
