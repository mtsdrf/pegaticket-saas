import Dexie, { type Table } from 'dexie'
import type { BalcaoOfflineLocalComanda, BalcaoOfflineSnapshot } from '../types/balcao'

interface BalcaoOfflineSnapshotRecord {
  tenant_uuid: string
  data: BalcaoOfflineSnapshot
  synced_at: string
}

class BalcaoOfflineDatabase extends Dexie {
  snapshots!: Table<BalcaoOfflineSnapshotRecord, string>
  localComandas!: Table<BalcaoOfflineLocalComanda, string>

  constructor() {
    super('maskats-balcao-offline')

    this.version(1).stores({
      snapshots: 'tenant_uuid,synced_at',
      localComandas: 'local_comanda_uuid,tenant_uuid,server_comanda_uuid,sync_status,opened_at',
    })
  }
}

const db = new BalcaoOfflineDatabase()

export async function getBalcaoOfflineSnapshot(tenantUuid: string): Promise<BalcaoOfflineSnapshotRecord | null> {
  return (await db.snapshots.get(tenantUuid)) ?? null
}

export async function saveBalcaoOfflineSnapshot(tenantUuid: string, data: BalcaoOfflineSnapshot): Promise<void> {
  await db.snapshots.put({
    tenant_uuid: tenantUuid,
    data,
    synced_at: new Date().toISOString(),
  })
}

export async function listBalcaoLocalComandas(tenantUuid: string): Promise<BalcaoOfflineLocalComanda[]> {
  return db.localComandas.where('tenant_uuid').equals(tenantUuid).sortBy('opened_at')
}

export async function getBalcaoLocalComanda(localComandaUuid: string): Promise<BalcaoOfflineLocalComanda | null> {
  return (await db.localComandas.get(localComandaUuid)) ?? null
}

export async function saveBalcaoLocalComanda(comanda: BalcaoOfflineLocalComanda): Promise<void> {
  await db.localComandas.put(comanda)
}

export async function deleteBalcaoLocalComanda(localComandaUuid: string): Promise<void> {
  await db.localComandas.delete(localComandaUuid)
}
