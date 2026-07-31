import { useCallback, useEffect, useMemo, useState } from 'react'
import { useNetworkStatus } from './useNetworkStatus'
import * as pdvService from '../services/pdvService'
import {
  getPdvOfflineSnapshot,
  listPdvOfflineQueuedSales,
  listPdvOfflineUnsyncedSales,
  markPdvOfflineSaleError,
  markPdvOfflineSaleSynced,
  markPdvOfflineSaleSyncing,
  queuePdvOfflineSale,
  savePdvOfflineSnapshot,
} from '../services/pdvOfflineStore'
import { getApiErrorMessage } from '../types/api'
import type { Order } from '../types/order'
import type { CreatePdvSalePayload, PdvOfflineQueuedSale, PdvOfflineSnapshot } from '../types/pdv'

interface QueueOfflineSaleInput {
  payload: CreatePdvSalePayload
  clientName: string | null
  receiptOrder: PdvOfflineQueuedSale['receipt_order']
}

export function usePdvOffline(tenantUuid: string | null, fallbackSessionUuid: string | null) {
  const isOffline = useNetworkStatus()
  const [snapshot, setSnapshot] = useState<PdvOfflineSnapshot | null>(null)
  const [snapshotSyncedAt, setSnapshotSyncedAt] = useState<string | null>(null)
  const [queuedSales, setQueuedSales] = useState<PdvOfflineQueuedSale[]>([])
  const [isRefreshingSnapshot, setIsRefreshingSnapshot] = useState(false)
  const [isSyncing, setIsSyncing] = useState(false)
  const [snapshotError, setSnapshotError] = useState<string | null>(null)

  const loadLocalState = useCallback(async () => {
    if (!tenantUuid) {
      setSnapshot(null)
      setSnapshotSyncedAt(null)
      setQueuedSales([])
      return
    }

    const [snapshotRecord, sales] = await Promise.all([
      getPdvOfflineSnapshot(tenantUuid),
      listPdvOfflineQueuedSales(tenantUuid),
    ])

    setSnapshot(snapshotRecord?.data ?? null)
    setSnapshotSyncedAt(snapshotRecord?.synced_at ?? null)
    setQueuedSales(sales)
  }, [tenantUuid])

  useEffect(() => {
    void loadLocalState()
  }, [loadLocalState])

  const refreshSnapshot = useCallback(async () => {
    if (!tenantUuid || isOffline) {
      return null
    }

    setIsRefreshingSnapshot(true)
    setSnapshotError(null)
    try {
      const nextSnapshot = await pdvService.getOfflineSnapshot()
      await savePdvOfflineSnapshot(tenantUuid, nextSnapshot)
      await loadLocalState()
      return nextSnapshot
    } catch (error) {
      setSnapshotError(getApiErrorMessage(error, 'Não foi possível atualizar a base offline do PDV agora.'))
      return null
    } finally {
      setIsRefreshingSnapshot(false)
    }
  }, [isOffline, loadLocalState, tenantUuid])

  const syncQueuedSales = useCallback(async () => {
    if (!tenantUuid || isOffline) {
      return { synced: 0, failed: 0 }
    }

    const unsyncedSales = await listPdvOfflineUnsyncedSales(tenantUuid)
    if (unsyncedSales.length === 0) {
      await loadLocalState()
      return { synced: 0, failed: 0 }
    }

    setIsSyncing(true)
    let synced = 0
    let failed = 0

    try {
      for (const sale of unsyncedSales) {
        await markPdvOfflineSaleSyncing(sale.local_sale_uuid)

        try {
          const order = await pdvService.createPdvSale({
            ...sale.payload,
            cash_session_uuid: sale.payload.cash_session_uuid ?? fallbackSessionUuid ?? sale.cash_session_uuid,
            client_sale_uuid: sale.payload.client_sale_uuid ?? sale.local_sale_uuid,
          })
          await markPdvOfflineSaleSynced(sale.local_sale_uuid, order.uuid)
          synced += 1
        } catch (error) {
          await markPdvOfflineSaleError(
            sale.local_sale_uuid,
            getApiErrorMessage(error, 'Não foi possível sincronizar esta venda agora.'),
          )
          failed += 1
        }
      }
    } finally {
      await loadLocalState()
      setIsSyncing(false)
    }

    return { synced, failed }
  }, [fallbackSessionUuid, isOffline, loadLocalState, tenantUuid])

  useEffect(() => {
    if (!tenantUuid || isOffline) return
    void syncQueuedSales()
  }, [isOffline, syncQueuedSales, tenantUuid])

  const queueSale = useCallback(async ({ payload, clientName, receiptOrder }: QueueOfflineSaleInput) => {
    if (!tenantUuid) {
      throw new Error('Empresa ativa não encontrada para registrar venda offline.')
    }

    const localSaleUuid = payload.client_sale_uuid ?? crypto.randomUUID()
    const queuedSale: PdvOfflineQueuedSale = {
      local_sale_uuid: localSaleUuid,
      tenant_uuid: tenantUuid,
      cash_session_uuid: payload.cash_session_uuid ?? fallbackSessionUuid ?? '',
      client_name: clientName,
      status: 'pending',
      payload: {
        ...payload,
        client_sale_uuid: localSaleUuid,
        cash_session_uuid: payload.cash_session_uuid ?? fallbackSessionUuid ?? undefined,
      },
      receipt_order: receiptOrder,
      created_at: new Date().toISOString(),
      synced_at: null,
      synced_order_uuid: null,
      last_error: null,
    }

    await queuePdvOfflineSale(queuedSale)
    await loadLocalState()

    return queuedSale
  }, [fallbackSessionUuid, loadLocalState, tenantUuid])

  const unsyncedSales = useMemo(
    () => queuedSales.filter((sale) => sale.status === 'pending' || sale.status === 'error'),
    [queuedSales],
  )

  const latestQueueError = useMemo(
    () => queuedSales.find((sale) => sale.status === 'error' && sale.last_error)?.last_error ?? null,
    [queuedSales],
  )

  const pendingQueueCount = useMemo(
    () => queuedSales.filter((sale) => sale.status === 'pending' || sale.status === 'syncing').length,
    [queuedSales],
  )

  const queueErrorCount = useMemo(
    () => queuedSales.filter((sale) => sale.status === 'error').length,
    [queuedSales],
  )

  const findSnapshotProduct = useCallback(
    (predicate: (product: PdvOfflineSnapshot['products'][number]) => boolean) => snapshot?.products.find(predicate) ?? null,
    [snapshot],
  )

  const buildQueuedOrderForReceipt = useCallback((sale: PdvOfflineQueuedSale): Order => ({
    uuid: sale.local_sale_uuid,
    codigo: sale.receipt_order.codigo,
    is_installment: false,
    total_amount: sale.receipt_order.total_amount,
    delivery_fee: 0,
    service_fee: 0,
    discount_amount: 0,
    coupon_code: null,
    is_paid: true,
    paid_amount: sale.receipt_order.total_amount,
    paid_at: sale.created_at,
    is_delivered: true,
    delivered_at: sale.created_at,
    due_date: null,
    expected_delivery_date: null,
    cancelled_at: null,
    cancellation_reason: null,
    notes: sale.payload.notes ?? null,
    status: 'confirmed',
    origin: 'pdv',
    is_out_for_delivery: false,
    out_for_delivery_at: null,
    items: sale.receipt_order.items,
    created_at: sale.receipt_order.created_at,
  }), [])

  return {
    isOffline,
    snapshot,
    snapshotSyncedAt,
    queuedSales,
    unsyncedSales,
    unsyncedCount: unsyncedSales.length,
    pendingQueueCount,
    queueErrorCount,
    latestQueueError,
    snapshotError,
    isRefreshingSnapshot,
    isSyncing,
    loadLocalState,
    refreshSnapshot,
    syncQueuedSales,
    queueSale,
    findSnapshotProduct,
    buildQueuedOrderForReceipt,
  }
}
