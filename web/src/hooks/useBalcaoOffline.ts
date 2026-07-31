import { useCallback, useEffect, useMemo, useState } from 'react'
import * as balcaoService from '../services/balcaoService'
import {
  deleteBalcaoLocalComanda,
  getBalcaoLocalComanda,
  getBalcaoOfflineSnapshot,
  listBalcaoLocalComandas,
  saveBalcaoLocalComanda,
  saveBalcaoOfflineSnapshot,
} from '../services/balcaoOfflineStore'
import { getApiErrorMessage } from '../types/api'
import type {
  AddComandaItemPayload,
  BalcaoOfflineLocalComanda,
  BalcaoOfflineLocalItem,
  BalcaoOfflineSnapshot,
  Comanda,
  ComandaItemProductRef,
  ComandaTableRef,
  Table,
} from '../types/balcao'
import { getOfflineDeviceId } from '../utils/offlineDevice'
import { useNetworkStatus } from './useNetworkStatus'

interface OpenOfflineComandaInput {
  table: ComandaTableRef | null
  label: string | null
}

interface AddOfflineItemInput {
  comandaUuid: string
  product: ComandaItemProductRef
  qty: number
  unitPrice: number
  notes: string | null
}

function mergeSnapshotWithLocal(
  snapshotComandas: Comanda[],
  localComandas: BalcaoOfflineLocalComanda[],
): Comanda[] {
  const merged = new Map<string, Comanda>()

  for (const comanda of snapshotComandas) {
    merged.set(comanda.uuid, {
      ...comanda,
      items: [...(comanda.items ?? [])],
    })
  }

  for (const local of localComandas) {
    const baseKey = local.server_comanda_uuid ?? local.local_comanda_uuid
    const base = merged.get(baseKey)

    if (base) {
      base.items = [
        ...(base.items ?? []),
        ...local.items.map((item) => ({
          uuid: item.server_item_uuid ?? item.local_item_uuid,
          qty: item.qty,
          unit_price: item.unit_price,
          line_total: item.line_total,
          notes: item.notes,
          prep_status: item.prep_status,
          sent_to_station_at: null,
          preparing_at: null,
          ready_at: null,
          delivered_at: null,
          cancelled_at: null,
          cancelled_reason: null,
          product: item.product,
          station: null,
          updated_at: null,
        })),
      ]

      base.items_subtotal = (base.items ?? []).reduce((sum, item) => sum + item.line_total, 0)
      continue
    }

    merged.set(baseKey, {
      uuid: local.local_comanda_uuid,
      label: local.label,
      status: local.status,
      opened_at: local.opened_at,
      closed_at: null,
      service_fee_percent: null,
      order_uuid: null,
      table: local.table,
      items: local.items.map((item) => ({
        uuid: item.local_item_uuid,
        qty: item.qty,
        unit_price: item.unit_price,
        line_total: item.line_total,
        notes: item.notes,
        prep_status: item.prep_status,
        sent_to_station_at: null,
        preparing_at: null,
        ready_at: null,
        delivered_at: null,
        cancelled_at: null,
        cancelled_reason: null,
        product: item.product,
        station: null,
        updated_at: null,
      })),
      items_subtotal: local.items.reduce((sum, item) => sum + item.line_total, 0),
      created_at: local.opened_at,
      updated_at: local.base_server_updated_at ?? null,
    })
  }

  return Array.from(merged.values())
}

function buildConflictState(comanda: BalcaoOfflineLocalComanda, message: string): BalcaoOfflineLocalComanda {
  return {
    ...comanda,
    sync_status: 'conflict',
    last_error: message,
    conflict_reason: message,
    conflict_detected_at: new Date().toISOString(),
  }
}

export function useBalcaoOffline(tenantUuid: string | null) {
  const isOffline = useNetworkStatus()
  const [snapshot, setSnapshot] = useState<BalcaoOfflineSnapshot | null>(null)
  const [snapshotSyncedAt, setSnapshotSyncedAt] = useState<string | null>(null)
  const [localComandas, setLocalComandas] = useState<BalcaoOfflineLocalComanda[]>([])
  const [isRefreshingSnapshot, setIsRefreshingSnapshot] = useState(false)
  const [isSyncing, setIsSyncing] = useState(false)
  const [snapshotError, setSnapshotError] = useState<string | null>(null)
  const [deviceId] = useState(() => getOfflineDeviceId())

  const loadLocalState = useCallback(async () => {
    if (!tenantUuid) {
      setSnapshot(null)
      setSnapshotSyncedAt(null)
      setLocalComandas([])
      return
    }

    const [snapshotRecord, comandas] = await Promise.all([
      getBalcaoOfflineSnapshot(tenantUuid),
      listBalcaoLocalComandas(tenantUuid),
    ])

    setSnapshot(snapshotRecord?.data ?? null)
    setSnapshotSyncedAt(snapshotRecord?.synced_at ?? null)
    setLocalComandas(comandas)
  }, [tenantUuid])

  useEffect(() => {
    void loadLocalState()
  }, [loadLocalState])

  const refreshSnapshot = useCallback(async () => {
    if (!tenantUuid || isOffline) return null

    setIsRefreshingSnapshot(true)
    setSnapshotError(null)
    try {
      const nextSnapshot = await balcaoService.getOfflineSnapshot()
      await saveBalcaoOfflineSnapshot(tenantUuid, nextSnapshot)
      await loadLocalState()
      return nextSnapshot
    } catch (error) {
      setSnapshotError(getApiErrorMessage(error, 'Não foi possível atualizar a base offline do balcão agora.'))
      return null
    } finally {
      setIsRefreshingSnapshot(false)
    }
  }, [isOffline, loadLocalState, tenantUuid])

  const ensureLocalOverlayForServerComanda = useCallback(async (comanda: Comanda) => {
    if (!tenantUuid) {
      throw new Error('Empresa ativa não encontrada.')
    }

    const existing = localComandas.find(
      (candidate) => candidate.local_comanda_uuid === comanda.uuid || candidate.server_comanda_uuid === comanda.uuid,
    )

    if (existing) {
      const refreshedExisting: BalcaoOfflineLocalComanda = {
        ...existing,
        table: comanda.table ?? existing.table,
        label: comanda.label ?? existing.label,
        status: comanda.status,
        base_snapshot_generated_at: snapshot?.generated_at ?? existing.base_snapshot_generated_at ?? null,
        base_server_updated_at: comanda.updated_at ?? comanda.created_at ?? existing.base_server_updated_at ?? null,
      }
      await saveBalcaoLocalComanda(refreshedExisting)
      await loadLocalState()
      return refreshedExisting
    }

    const overlay: BalcaoOfflineLocalComanda = {
      local_comanda_uuid: comanda.uuid,
      server_comanda_uuid: comanda.uuid,
      tenant_uuid: tenantUuid,
      device_id: deviceId,
      table: comanda.table ?? null,
      label: comanda.label,
      status: comanda.status,
      sync_status: 'synced',
      opened_at: comanda.opened_at ?? comanda.created_at,
      base_snapshot_generated_at: snapshot?.generated_at ?? null,
      base_server_updated_at: comanda.updated_at ?? comanda.created_at ?? null,
      items: [],
      last_error: null,
      conflict_reason: null,
      conflict_detected_at: null,
    }

    await saveBalcaoLocalComanda(overlay)
    await loadLocalState()
    return overlay
  }, [deviceId, loadLocalState, localComandas, snapshot?.generated_at, tenantUuid])

  const openLocalComanda = useCallback(async ({ table, label }: OpenOfflineComandaInput) => {
    if (!tenantUuid) {
      throw new Error('Empresa ativa não encontrada.')
    }

    const comanda: BalcaoOfflineLocalComanda = {
      local_comanda_uuid: crypto.randomUUID(),
      server_comanda_uuid: null,
      tenant_uuid: tenantUuid,
      device_id: deviceId,
      table,
      label,
      status: 'open',
      sync_status: 'pending',
      opened_at: new Date().toISOString(),
      base_snapshot_generated_at: snapshot?.generated_at ?? null,
      base_server_updated_at: null,
      items: [],
      last_error: null,
      conflict_reason: null,
      conflict_detected_at: null,
    }

    await saveBalcaoLocalComanda(comanda)
    await loadLocalState()
    return comanda
  }, [deviceId, loadLocalState, snapshot?.generated_at, tenantUuid])

  const addLocalItem = useCallback(async ({ comandaUuid, product, qty, unitPrice, notes }: AddOfflineItemInput) => {
    const comanda = await getBalcaoLocalComanda(comandaUuid)
    if (!comanda) {
      throw new Error('Comanda local não encontrada.')
    }

    const item: BalcaoOfflineLocalItem = {
      local_item_uuid: crypto.randomUUID(),
      server_item_uuid: null,
      product,
      qty,
      unit_price: unitPrice,
      line_total: qty * unitPrice,
      notes,
      prep_status: 'queued',
      sync_status: 'pending',
      created_at: new Date().toISOString(),
      last_error: null,
    }

    await saveBalcaoLocalComanda({
      ...comanda,
      items: [...comanda.items, item],
      last_error: null,
      conflict_reason: null,
      conflict_detected_at: null,
      sync_status: comanda.server_comanda_uuid ? 'pending' : 'pending',
    })
    await loadLocalState()
    return item
  }, [loadLocalState])

  const removeUnsyncedLocalItem = useCallback(async (comandaUuid: string, localItemUuid: string) => {
    const comanda = await getBalcaoLocalComanda(comandaUuid)
    if (!comanda) return

    const nextItems = comanda.items.filter((item) => item.local_item_uuid !== localItemUuid)

    if (nextItems.length === 0 && !comanda.server_comanda_uuid) {
      await deleteBalcaoLocalComanda(comandaUuid)
    } else {
      await saveBalcaoLocalComanda({
        ...comanda,
        items: nextItems,
      })
    }

    await loadLocalState()
  }, [loadLocalState])

  const discardLocalComanda = useCallback(async (localComandaUuid: string) => {
    await deleteBalcaoLocalComanda(localComandaUuid)
    await loadLocalState()
  }, [loadLocalState])

  const discardConflictingComandas = useCallback(async () => {
    if (!tenantUuid) return 0

    const comandas = await listBalcaoLocalComandas(tenantUuid)
    const conflicting = comandas.filter((comanda) => comanda.sync_status === 'conflict')

    await Promise.all(conflicting.map((comanda) => deleteBalcaoLocalComanda(comanda.local_comanda_uuid)))
    await loadLocalState()

    return conflicting.length
  }, [loadLocalState, tenantUuid])

  const syncComandas = useCallback(async () => {
    if (!tenantUuid || isOffline) {
      return { syncedComandas: 0, syncedItems: 0, failed: 0, conflicts: 0 }
    }

    const comandas = await listBalcaoLocalComandas(tenantUuid)
    if (comandas.length === 0) {
      await loadLocalState()
      return { syncedComandas: 0, syncedItems: 0, failed: 0, conflicts: 0 }
    }

    let syncedComandas = 0
    let syncedItems = 0
    let failed = 0
    let conflicts = 0
    setIsSyncing(true)

    try {
      const liveOpenComandas = await balcaoService.listOpenComandas()
      const liveByUuid = new Map(liveOpenComandas.map((comanda) => [comanda.uuid, comanda]))
      const liveByTable = new Map<string, Comanda[]>()

      for (const comanda of liveOpenComandas) {
        const tableUuid = comanda.table?.uuid
        if (!tableUuid) continue
        const bucket = liveByTable.get(tableUuid) ?? []
        bucket.push(comanda)
        liveByTable.set(tableUuid, bucket)
      }

      for (const local of comandas) {
        let current = local

        if (!current.server_comanda_uuid) {
          if (current.table?.uuid) {
            const sameTableComandas = liveByTable.get(current.table.uuid) ?? []

            if (sameTableComandas.length === 1) {
              const matched = sameTableComandas[0]
              current = {
                ...current,
                server_comanda_uuid: matched.uuid,
                status: matched.status,
                label: current.label ?? matched.label,
                base_server_updated_at: matched.updated_at ?? matched.created_at ?? null,
                last_error: null,
                conflict_reason: null,
                conflict_detected_at: null,
                sync_status: current.items.some((item) => !item.server_item_uuid) ? 'pending' : 'synced',
              }
              await saveBalcaoLocalComanda(current)
            } else if (sameTableComandas.length > 1) {
              conflicts += 1
              await saveBalcaoLocalComanda(
                buildConflictState(
                  current,
                  'Esta mesa já possui múltiplas comandas abertas em outros dispositivos. Revise manualmente antes de sincronizar.',
                ),
              )
              continue
            }
          }

          if (!current.server_comanda_uuid) {
            try {
              const opened = await balcaoService.openComanda({
                table_uuid: current.table?.uuid ?? null,
                label: current.label,
                client_comanda_uuid: current.local_comanda_uuid,
              })

              current = {
                ...current,
                server_comanda_uuid: opened.uuid,
                status: opened.status,
                base_server_updated_at: opened.updated_at ?? opened.created_at ?? null,
                sync_status: current.items.some((item) => !item.server_item_uuid) ? 'pending' : 'synced',
                last_error: null,
                conflict_reason: null,
                conflict_detected_at: null,
              }
              syncedComandas += 1
              liveByUuid.set(opened.uuid, opened)
              if (opened.table?.uuid) {
                liveByTable.set(opened.table.uuid, [...(liveByTable.get(opened.table.uuid) ?? []), opened])
              }
              await saveBalcaoLocalComanda(current)
            } catch (error) {
              failed += 1
              await saveBalcaoLocalComanda({
                ...current,
                sync_status: 'error',
                last_error: getApiErrorMessage(error, 'Não foi possível sincronizar esta comanda agora.'),
              })
              continue
            }
          }
        }

        const liveComanda = liveByUuid.get(current.server_comanda_uuid!)

        if (!liveComanda) {
          conflicts += 1
          await saveBalcaoLocalComanda(
            buildConflictState(
              current,
              'A comanda foi alterada ou fechada em outro dispositivo antes da sincronização. Atualize a base e revise o lançamento local.',
            ),
          )
          continue
        }

        let nextItems = [...current.items]
        let detectedConflictMessage: string | null = null
        for (const item of current.items.filter((candidate) => !candidate.server_item_uuid)) {
          try {
            const created = await balcaoService.addComandaItem(current.server_comanda_uuid!, {
              product_uuid: item.product.uuid,
              qty: item.qty,
              notes: item.notes,
              client_item_uuid: item.local_item_uuid,
            } as AddComandaItemPayload)

            nextItems = nextItems.map((candidate) =>
              candidate.local_item_uuid === item.local_item_uuid
                ? {
                    ...candidate,
                    server_item_uuid: created.uuid,
                    sync_status: 'synced',
                    last_error: null,
                  }
                : candidate,
            )
            syncedItems += 1
          } catch (error) {
            const message = getApiErrorMessage(error, 'Não foi possível sincronizar este item agora.')
            const isConflictMessage =
              message.includes('não está aberta') ||
              message.includes('já fechada') ||
              message.includes('já cancelada')

            nextItems = nextItems.map((candidate) =>
              candidate.local_item_uuid === item.local_item_uuid
                ? {
                    ...candidate,
                    sync_status: isConflictMessage ? 'error' : 'error',
                    last_error: message,
                  }
                : candidate,
            )

            if (isConflictMessage) {
              detectedConflictMessage ??= message
              conflicts += 1
            } else {
              failed += 1
            }
          }
        }

        const refreshedServerComanda = await balcaoService.findOpenComanda(current.server_comanda_uuid!)

        if (detectedConflictMessage) {
          await saveBalcaoLocalComanda(
            buildConflictState(
              {
                ...current,
                items: nextItems,
              },
              detectedConflictMessage,
            ),
          )
          continue
        }

        if (!refreshedServerComanda && nextItems.some((item) => !item.server_item_uuid)) {
          conflicts += 1
          await saveBalcaoLocalComanda(
            buildConflictState(
              {
                ...current,
                items: nextItems,
              },
              'A comanda deixou de ficar aberta durante a sincronização. Revise manualmente os itens pendentes neste dispositivo.',
            ),
          )
          continue
        }

        const hasItemErrors = nextItems.some((item) => item.sync_status === 'error')

        await saveBalcaoLocalComanda({
          ...current,
          status: refreshedServerComanda?.status ?? current.status,
          items: nextItems,
          base_server_updated_at:
            refreshedServerComanda?.updated_at ?? refreshedServerComanda?.created_at ?? liveComanda.updated_at ?? liveComanda.created_at ?? current.base_server_updated_at,
          last_error: nextItems.find((item) => item.sync_status === 'error')?.last_error ?? null,
          conflict_reason: null,
          conflict_detected_at: null,
          sync_status: hasItemErrors ? 'error' : 'synced',
        })
      }
    } finally {
      await loadLocalState()
      setIsSyncing(false)
    }

    return { syncedComandas, syncedItems, failed, conflicts }
  }, [isOffline, loadLocalState, tenantUuid])

  useEffect(() => {
    if (!tenantUuid || isOffline) return
    void syncComandas()
  }, [isOffline, syncComandas, tenantUuid])

  const effectiveComandas = useMemo(
    () => mergeSnapshotWithLocal(snapshot?.comandas ?? [], localComandas),
    [localComandas, snapshot?.comandas],
  )

  const effectiveTables = useMemo<Table[]>(() => {
    if (!snapshot?.tables) return []
    const occupiedByLocal = new Set(
      localComandas
        .filter((comanda) => comanda.status === 'open' && comanda.table?.uuid)
        .map((comanda) => comanda.table!.uuid),
    )

    return snapshot.tables.map((table) =>
      occupiedByLocal.has(table.uuid)
        ? { ...table, status: 'occupied' }
        : table,
    )
  }, [localComandas, snapshot?.tables])

  const pendingCount = useMemo(
    () => localComandas.reduce((sum, comanda) => sum + (comanda.sync_status !== 'synced' ? 1 : 0), 0)
      + localComandas.reduce((sum, comanda) => sum + comanda.items.filter((item) => item.sync_status !== 'synced').length, 0),
    [localComandas],
  )

  const conflictCount = useMemo(
    () => localComandas.filter((comanda) => comanda.sync_status === 'conflict').length,
    [localComandas],
  )

  const conflictingComandas = useMemo(
    () => localComandas.filter((comanda) => comanda.sync_status === 'conflict'),
    [localComandas],
  )

  const findSnapshotProduct = useCallback(
    (predicate: (product: BalcaoOfflineSnapshot['products'][number]) => boolean) => snapshot?.products.find(predicate) ?? null,
    [snapshot],
  )

  return {
    isOffline,
    snapshot,
    snapshotSyncedAt,
    effectiveTables,
    effectiveComandas,
    localComandas,
    pendingCount,
    conflictCount,
    conflictingComandas,
    snapshotError,
    isRefreshingSnapshot,
    isSyncing,
    refreshSnapshot,
    syncComandas,
    openLocalComanda,
    ensureLocalOverlayForServerComanda,
    addLocalItem,
    removeUnsyncedLocalItem,
    discardLocalComanda,
    discardConflictingComandas,
    findSnapshotProduct,
  }
}
