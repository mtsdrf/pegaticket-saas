import type { PaginatedResult } from './pagination'

export interface MarketplaceMerchant {
  uuid: string
  external_id: string
  name: string
  is_active: boolean
  status_payload: Record<string, unknown> | null
  metadata: Record<string, unknown> | null
  last_seen_at: string | null
}

export interface MarketplaceEvent {
  uuid: string
  external_event_id: string | null
  external_order_id: string | null
  event_type: string
  event_full_code: string | null
  status: string
  processing_attempts: number
  occurred_at: string | null
  acknowledged_at: string | null
  processed_at: string | null
  last_attempted_at: string | null
  dead_lettered_at: string | null
  error_message: string | null
  payload: Record<string, unknown>
}

export interface MarketplaceAction {
  uuid: string
  action: string
  status: string
  request_payload: Record<string, unknown> | null
  response_payload: Record<string, unknown> | null
  executed_at: string | null
  error_message: string | null
}

export interface MarketplaceCancellationReason {
  code: string
  description: string
  metadata?: Record<string, unknown>
}

export interface MarketplaceOrder {
  uuid: string
  external_id: string
  display_id: string | null
  order_number: string | null
  status: string | null
  queue_status: 'imported' | 'pending_import' | 'import_error'
  customer_name: string | null
  total_amount: number | null
  raw_updated_at: string | null
  last_synced_at: string | null
  imported_at: string | null
  import_error_message: string | null
  events_count?: number
  latest_event_at?: string | null
  payload: Record<string, unknown>
  merchant?: MarketplaceMerchant | null
  actions?: MarketplaceAction[]
  events?: MarketplaceEvent[]
  internal_order?: {
    uuid: string
    codigo: string | null
    status: string
    origin: string
    is_paid: boolean
    is_delivered: boolean
    client_name: string | null
  } | null
}

export interface MarketplaceIntegration {
  uuid: string
  provider: string
  name: string
  environment: 'sandbox' | 'production'
  auth_mode: string
  status: string
  is_active: boolean
  client_id: string | null
  merchant_id: string | null
  webhook_url: string | null
  generated_webhook_url: string | null
  polling_merchant_ids: string | null
  access_token_expires_at: string | null
  refresh_token_expires_at: string | null
  last_connected_at: string | null
  last_synced_at: string | null
  last_polled_at: string | null
  last_error_at: string | null
  last_error_message: string | null
  settings: Record<string, unknown> | null
  merchants?: MarketplaceMerchant[]
  merchants_count?: number
  events_count?: number
  orders_count?: number
}

export interface MarketplaceIntegrationPayload {
  provider: 'ifood'
  name: string
  environment: 'sandbox' | 'production'
  is_active: boolean
  client_id?: string
  client_secret?: string
  authorization_code?: string
  merchant_id?: string
  webhook_url?: string
  polling_merchant_ids?: string
}

export interface MarketplaceOrderFilters {
  search?: string
  status?: string
  merchant_uuid?: string
  queue_status?: MarketplaceOrder['queue_status'] | ''
  page?: number
  per_page?: number
}

export type MarketplaceOrderPageResult = PaginatedResult<MarketplaceOrder>

export interface MarketplacePollResult {
  integration: MarketplaceIntegration
  processed: number
  acknowledged: number
}

export interface MarketplaceOrderActionPayload {
  action: 'confirm' | 'startPreparation' | 'readyToPickup' | 'dispatch' | 'cancel'
  reason?: string
  code?: string
}

export interface MarketplaceHealthCheck {
  ok: boolean
  provider: string
  merchant_count: number
}

export interface MarketplaceOperationsSummary {
  events_total: number
  events_processed: number
  events_failed: number
  events_dead_letter: number
  events_unacknowledged: number
  orders_total: number
  orders_imported: number
  orders_pending_import: number
  orders_with_import_error: number
  orders_pending_import_attention: number
  orders_pending_import_critical: number
  orders_imported_without_recent_signal: number
  oldest_pending_import_minutes: number | null
  oldest_import_error_minutes: number | null
  oldest_imported_without_signal_minutes: number | null
  last_poll_at: string | null
  last_webhook_received_at: string | null
  last_error_at: string | null
  last_error_message: string | null
  silent_since_minutes?: number | null
  is_stale?: boolean
  needs_attention?: boolean
}

export interface MarketplaceCatalogPreviewCategory {
  id: string
  name: string
  request_payload: Record<string, unknown>
}

export interface MarketplaceCatalogPreviewItem {
  id: string
  product_uuid: string | null
  product_name: string
  category_name: string
  request_payload: Record<string, unknown>
}

export interface MarketplaceCatalogPreview {
  merchant: {
    uuid: string
    external_id: string
    name: string
  }
  supported_features: string[]
  pending_features: string[]
  limitations: string[]
  categories_total: number
  items_total: number
  categories: MarketplaceCatalogPreviewCategory[]
  items: MarketplaceCatalogPreviewItem[]
}

export interface MarketplaceCatalogSyncItem {
  uuid: string
  entity_type: string
  entity_key: string
  external_entity_id: string | null
  batch_id: string | null
  status: string
  processed_at: string | null
  error_message: string | null
  request_payload: Record<string, unknown> | null
  response_payload: Record<string, unknown> | null
  product?: {
    uuid: string
    name: string
    sku: string | null
  } | null
}

export interface MarketplaceCatalogSync {
  uuid: string
  status: string
  categories_total: number
  items_total: number
  processed_count: number
  success_count: number
  failed_count: number
  started_at: string | null
  finished_at: string | null
  error_message: string | null
  request_snapshot: Record<string, unknown> | null
  response_snapshot: Record<string, unknown> | null
  merchant?: {
    uuid: string
    external_id: string
    name: string
  } | null
  items?: MarketplaceCatalogSyncItem[]
}

export interface MarketplaceMerchantStatusEntry {
  operation: string | null
  salesChannel: string | null
  available: boolean
  state: string | null
  message?: string | null
}

export interface MarketplaceMerchantInterruption {
  id: string
  description: string | null
  start: string | null
  end: string | null
}

export interface MarketplaceMerchantStatusSnapshot {
  merchant: {
    uuid: string
    external_id: string
    name: string
  }
  status: MarketplaceMerchantStatusEntry[]
  interruptions: MarketplaceMerchantInterruption[]
  last_opening_hours_sync_at: string | null
  last_opening_hours_shift_count: number | null
}

export interface MarketplaceOpeningHoursSyncResult {
  merchant: {
    uuid: string
    external_id: string
    name: string
  }
  shifts_count: number
  synced_at: string
  shifts: Array<{
    dayOfWeek: string
    start: string
    duration: number
  }>
}
