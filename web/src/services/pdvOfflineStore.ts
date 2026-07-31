import Dexie, { type Table } from 'dexie'
import type { PdvOfflineQueuedSale, PdvOfflineSnapshot } from '../types/pdv'

interface PdvOfflineSnapshotRecord {
  tenant_uuid: string
  data: PdvOfflineSnapshot
  synced_at: string
}

class PdvOfflineDatabase extends Dexie {
  snapshots!: Table<PdvOfflineSnapshotRecord, string>
  queuedSales!: Table<PdvOfflineQueuedSale, string>

  constructor() {
    super('maskats-pdv-offline')

    this.version(1).stores({
      snapshots: 'tenant_uuid,synced_at',
      queuedSales: 'local_sale_uuid,tenant_uuid,status,created_at,synced_at',
    })
  }
}

const db = new PdvOfflineDatabase()

export async function getPdvOfflineSnapshot(tenantUuid: string): Promise<PdvOfflineSnapshotRecord | null> {
  return (await db.snapshots.get(tenantUuid)) ?? null
}

export async function savePdvOfflineSnapshot(tenantUuid: string, data: PdvOfflineSnapshot): Promise<void> {
  await db.snapshots.put({
    tenant_uuid: tenantUuid,
    data,
    synced_at: new Date().toISOString(),
  })
}

export async function queuePdvOfflineSale(sale: PdvOfflineQueuedSale): Promise<void> {
  await db.queuedSales.put(sale)
}

export async function getPdvOfflineQueuedSale(localSaleUuid: string): Promise<PdvOfflineQueuedSale | null> {
  return (await db.queuedSales.get(localSaleUuid)) ?? null
}

export async function listPdvOfflineQueuedSales(tenantUuid: string): Promise<PdvOfflineQueuedSale[]> {
  return db.queuedSales.where('tenant_uuid').equals(tenantUuid).sortBy('created_at')
}

export async function listPdvOfflineUnsyncedSales(tenantUuid: string): Promise<PdvOfflineQueuedSale[]> {
  const sales = await listPdvOfflineQueuedSales(tenantUuid)
  return sales.filter((sale) => sale.status === 'pending' || sale.status === 'error')
}

export async function markPdvOfflineSaleSyncing(localSaleUuid: string): Promise<void> {
  await db.queuedSales.update(localSaleUuid, {
    status: 'syncing',
    last_error: null,
  })
}

export async function markPdvOfflineSaleSynced(localSaleUuid: string, orderUuid: string): Promise<void> {
  await db.queuedSales.update(localSaleUuid, {
    status: 'synced',
    synced_order_uuid: orderUuid,
    synced_at: new Date().toISOString(),
    last_error: null,
  })
}

export async function markPdvOfflineSaleError(localSaleUuid: string, message: string): Promise<void> {
  await db.queuedSales.update(localSaleUuid, {
    status: 'error',
    last_error: message,
  })
}
