/**
 * Tipos do módulo Balcão (Fases 1+2) — espelham exatamente os Resources/Requests
 * do backend em `api/app/Http/{Resources,Requests}/Balcao/*`. A comanda vive
 * aberta e só materializa um `Order` (`origin: 'counter'`) no fechamento
 * (reaproveita `OrderResource` — ver `types/order.ts`).
 */

export type StationType = 'kitchen' | 'bar' | 'grill' | 'other'
export type TableStatus = 'free' | 'occupied' | 'reserved' | 'closing'
export type ComandaStatus = 'open' | 'closing' | 'closed' | 'cancelled'

/**
 * Máquina de estados do preparo (ver `ComandaItemService`):
 * `queued → sent_to_station → preparing → ready → delivered_to_table`;
 * `cancelled` de qualquer estado não-terminal com motivo obrigatório.
 * `sent_to_station` é a ÚNICA transição que baixa estoque.
 */
export type PrepStatus =
  | 'queued'
  | 'sent_to_station'
  | 'preparing'
  | 'ready'
  | 'delivered_to_table'
  | 'cancelled'

/**
 * Formas de pagamento aceitas no fechamento da comanda — enum PRÓPRIO do
 * backend (`CloseComandaRequest`: cash|pix|card|debit|credit). Note o `card`
 * genérico, que NÃO existe no enum do PDV (`types/pdv.ts`).
 */
export type BalcaoPaymentMethod = 'cash' | 'pix' | 'card' | 'debit' | 'credit'

export interface Station {
  uuid: string
  name: string
  type: StationType | null
  is_active: boolean
  created_at: string
}

export interface Table {
  uuid: string
  label: string
  area: string | null
  seats: number | null
  status: TableStatus
  created_at: string
}

export type TableReservationStatus = 'confirmed' | 'seated' | 'cancelled' | 'no_show'
export type TableReservationSource = 'internal' | 'online'

export interface TableReservation {
  uuid: string
  customer_name: string
  customer_phone: string | null
  customer_email: string | null
  party_size: number
  scheduled_for: string
  duration_minutes: number
  status: TableReservationStatus
  source: TableReservationSource
  notes: string | null
  cancelled_reason: string | null
  confirmed_at: string | null
  seated_at: string | null
  cancelled_at: string | null
  no_show_at: string | null
  table: { uuid: string; label: string; seats: number | null } | null
  seated_comanda_uuid: string | null
  created_at: string
  updated_at: string | null
}

export interface TableWaitlistEntry {
  uuid: string
  customer_name: string
  customer_phone: string | null
  party_size: number
  quoted_wait_minutes: number | null
  status: 'waiting' | 'called' | 'seated' | 'cancelled'
  notes: string | null
  cancelled_reason: string | null
  called_at: string | null
  seated_at: string | null
  cancelled_at: string | null
  table: { uuid: string; label: string; seats: number | null } | null
  seated_comanda_uuid: string | null
  created_at: string
  updated_at: string | null
}

export interface CreateTableReservationPayload {
  table_uuid?: string | null
  customer_name: string
  customer_phone?: string | null
  customer_email?: string | null
  party_size: number
  scheduled_for: string
  duration_minutes?: number
  notes?: string | null
}

export interface SeatTableReservationPayload {
  label?: string | null
}

export interface CreateTableWaitlistPayload {
  customer_name: string
  customer_phone?: string | null
  party_size: number
  quoted_wait_minutes?: number | null
  notes?: string | null
}

export interface SeatTableWaitlistPayload {
  table_uuid: string
  label?: string | null
}

export interface ComandaItemProductRef {
  uuid: string
  name: string
  unit: string | null
}

export interface ComandaItemStationRef {
  uuid: string
  name: string
  type: StationType | null
}

/** Contexto da comanda/mesa embutido no item — usado pela fila do KDS. */
export interface ComandaItemComandaRef {
  uuid: string
  label: string | null
  table_label: string | null
}

export interface ComandaItem {
  uuid: string
  qty: number
  unit_price: number
  line_total: number
  notes: string | null
  prep_status: PrepStatus
  sent_to_station_at: string | null
  preparing_at: string | null
  ready_at: string | null
  delivered_at: string | null
  cancelled_at: string | null
  cancelled_reason: string | null
  product?: ComandaItemProductRef
  station?: ComandaItemStationRef | null
  comanda?: ComandaItemComandaRef
  updated_at?: string | null
}

export interface ComandaTableRef {
  uuid: string
  label: string
}

export interface Comanda {
  uuid: string
  label: string | null
  status: ComandaStatus
  opened_at: string | null
  closed_at: string | null
  /** Congelada na abertura a partir de `tenant_settings.service_fee_percent`. `null`/0 = sem taxa. */
  service_fee_percent: number | null
  order_uuid?: string | null
  table?: ComandaTableRef | null
  items?: ComandaItem[]
  /** Subtotal dos itens não cancelados (conveniência) — total final resolvido no fechamento. */
  items_subtotal?: number
  created_at: string
  updated_at?: string | null
}

export interface OpenComandaPayload {
  /** `null`/omitido = comanda avulsa (balcão, sem mesa). */
  table_uuid?: string | null
  label?: string | null
  /** Chave idempotente local para abertura offline/sincronização posterior. */
  client_comanda_uuid?: string
}

export interface AddComandaItemPayload {
  product_uuid: string
  qty: number
  notes?: string | null
  /** Chave idempotente local do item para sincronização posterior. */
  client_item_uuid?: string
}

export interface UpdatePrepStatusPayload {
  prep_status: Exclude<PrepStatus, 'queued'>
  /** Obrigatório quando `prep_status === 'cancelled'`. */
  cancelled_reason?: string | null
}

export interface CloseComandaPaymentPayload {
  method: BalcaoPaymentMethod
  amount: number
}

export interface CloseComandaPayload {
  /** Aceitar/recusar a taxa de serviço. Backend força se o tenant a marcou obrigatória. */
  apply_service_fee?: boolean
  payments: CloseComandaPaymentPayload[]
}

export interface BalcaoOfflineSnapshotProduct {
  uuid: string
  name: string
  sku: string | null
  barcode: string | null
  unit: string | null
  price: number
  updated_at: string | null
}

export interface BalcaoOfflineSnapshot {
  generated_at: string
  tables: Table[]
  comandas: Comanda[]
  products: BalcaoOfflineSnapshotProduct[]
}

export type BalcaoOfflineSyncStatus = 'pending' | 'syncing' | 'synced' | 'error' | 'conflict'

export interface BalcaoOfflineLocalItem {
  local_item_uuid: string
  server_item_uuid: string | null
  product: ComandaItemProductRef
  qty: number
  unit_price: number
  line_total: number
  notes: string | null
  prep_status: PrepStatus
  sync_status: BalcaoOfflineSyncStatus
  created_at: string
  last_error: string | null
}

export interface BalcaoOfflineLocalComanda {
  local_comanda_uuid: string
  server_comanda_uuid: string | null
  tenant_uuid: string
  device_id: string
  table: ComandaTableRef | null
  label: string | null
  status: ComandaStatus
  sync_status: BalcaoOfflineSyncStatus
  opened_at: string
  base_snapshot_generated_at: string | null
  base_server_updated_at: string | null
  items: BalcaoOfflineLocalItem[]
  last_error: string | null
  conflict_reason: string | null
  conflict_detected_at: string | null
}
