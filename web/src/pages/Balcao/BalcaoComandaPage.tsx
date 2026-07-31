import AddIcon from '@mui/icons-material/Add'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import SearchIcon from '@mui/icons-material/Search'
import SendOutlinedIcon from '@mui/icons-material/SendOutlined'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Divider,
  InputAdornment,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { PREP_STATUS_META } from '../../constants/balcao'
import { OfflineConflictResolutionPanel } from '../../components/shared/OfflineConflictResolutionPanel'
import { OfflineContingencyGuide } from '../../components/shared/OfflineContingencyGuide'
import { OfflineSyncActivityPanel, type OfflineActivityEntry } from '../../components/shared/OfflineSyncActivityPanel'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import { useBalcaoOffline } from '../../hooks/useBalcaoOffline'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import * as balcaoService from '../../services/balcaoService'
import * as productService from '../../services/productService'
import { PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { BalcaoOfflineSnapshotProduct, Comanda, ComandaItem } from '../../types/balcao'
import type { Product } from '../../types/product'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'
import { CancelItemDialog } from './CancelItemDialog'
import { CloseComandaModal } from './CloseComandaModal'

function snapshotProductToProduct(product: BalcaoOfflineSnapshotProduct): Product {
  return {
    uuid: product.uuid,
    name: product.name,
    sku: product.sku,
    barcode: product.barcode,
    brand: null,
    ncm: null,
    cest: null,
    origin: null,
    default_cfop: null,
    csosn_cst: null,
    unit: product.unit,
    price: product.price,
    description: null,
    image_url: null,
    is_available: true,
    stock_quantity: 0,
    surcharge_rate: null,
    is_lot_controlled: false,
    is_expiry_controlled: false,
    is_serial_controlled: false,
    min_stock: null,
    max_stock: null,
    reorder_point: null,
    reorder_qty: null,
    product_type: null,
    created_at: product.updated_at ?? new Date().toISOString(),
  }
}

export function BalcaoComandaPage() {
  const { uuid = '' } = useParams()
  const navigate = useNavigate()
  const { activeTenantUuid } = useAuth()
  const { can } = useAccessControl()
  const canAddItem = can(ACCESS.balcaoAddItem)
  const canPrep = can(ACCESS.balcaoPrep)
  const canClose = can(ACCESS.balcaoClose)
  const {
    isOffline,
    snapshot,
    snapshotSyncedAt,
    effectiveComandas,
    localComandas,
    pendingCount,
    conflictCount,
    snapshotError,
    isRefreshingSnapshot,
    isSyncing,
    refreshSnapshot,
    syncComandas,
    ensureLocalOverlayForServerComanda,
    addLocalItem,
    removeUnsyncedLocalItem,
    discardLocalComanda,
  } = useBalcaoOffline(activeTenantUuid)

  const localComanda = useMemo(
    () =>
      localComandas.find(
        (candidate) => candidate.local_comanda_uuid === uuid || candidate.server_comanda_uuid === uuid,
      ) ?? null,
    [localComandas, uuid],
  )
  const offlineComanda = useMemo(
    () => effectiveComandas.find((candidate) => candidate.uuid === uuid) ?? null,
    [effectiveComandas, uuid],
  )

  const [comanda, setComanda] = useState<Comanda | null>(null)
  const [isLoading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [offlineFeedback, setOfflineFeedback] = useState<string | null>(null)
  const [busyItemUuid, setBusyItemUuid] = useState<string | null>(null)

  const [query, setQuery] = useState('')
  const debouncedQuery = useDebouncedValue(query, 300)
  const [results, setResults] = useState<Product[]>([])
  const [isSearching, setSearching] = useState(false)
  const [selected, setSelected] = useState<Product | null>(null)
  const [qty, setQty] = useState('1')
  const [notes, setNotes] = useState('')
  const [isAdding, setAdding] = useState(false)

  const [cancelTarget, setCancelTarget] = useState<ComandaItem | null>(null)
  const [isCloseOpen, setCloseOpen] = useState(false)

  const load = useCallback(async () => {
    if (isOffline) {
      setComanda(offlineComanda)
      setLoadError(offlineComanda ? null : 'Comanda não encontrada na base offline.')
      setLoading(false)
      return
    }

    setLoading(true)
    setLoadError(null)
    try {
      const result = await balcaoService.findOpenComanda(uuid)
      if (result) {
        setComanda(result)
        return
      }

      if (offlineComanda) {
        setComanda(offlineComanda)
        setLoadError(null)
        return
      }

      setComanda(null)
      setLoadError('Comanda não encontrada ou já fechada.')
    } catch (err) {
      setComanda(null)
      setLoadError(getApiErrorMessage(err, 'Não foi possível carregar a comanda.'))
    } finally {
      setLoading(false)
    }
  }, [isOffline, offlineComanda, uuid])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    const term = debouncedQuery.trim().toLowerCase()
    if (term.length < 2) {
      setResults([])
      return
    }

    if (isOffline) {
      const filtered = (snapshot?.products ?? [])
        .filter((product) => {
          const haystack = [product.name, product.sku, product.barcode].filter(Boolean).join(' ').toLowerCase()
          return haystack.includes(term)
        })
        .slice(0, 8)
        .map(snapshotProductToProduct)
      setResults(filtered)
      setSearching(false)
      return
    }

    let cancelled = false
    setSearching(true)
    productService
      .listProducts({ q: term, is_available: true, per_page: 8, sort_by: 'name', sort_dir: 'asc' })
      .then((page) => {
        if (!cancelled) setResults(page.items)
      })
      .catch(() => {
        if (!cancelled) setResults([])
      })
      .finally(() => {
        if (!cancelled) setSearching(false)
      })

    return () => {
      cancelled = true
    }
  }, [debouncedQuery, isOffline, snapshot?.products])

  const activeItems = useMemo(
    () => (comanda?.items ?? []).filter((item) => item.prep_status !== 'cancelled'),
    [comanda],
  )
  const subtotal = useMemo(
    () => activeItems.reduce((sum, item) => sum + item.line_total, 0),
    [activeItems],
  )

  const pendingLocalItemIds = useMemo(() => {
    const ids = new Set<string>()
    for (const localItem of localComanda?.items ?? []) {
      if (!localItem.server_item_uuid) {
        ids.add(localItem.local_item_uuid)
      }
    }
    return ids
  }, [localComanda?.items])
  const hasUnsyncedLocalItems = pendingLocalItemIds.size > 0
  const hasComandaSyncError = localComanda?.sync_status === 'error'
  const hasComandaConflict = localComanda?.sync_status === 'conflict'
  const closeBlockedByOfflineState = Boolean(hasUnsyncedLocalItems || hasComandaSyncError || hasComandaConflict)
  const offlineActivityEntries = useMemo<OfflineActivityEntry[]>(
    () =>
      (localComanda?.items ?? [])
        .map((item) => ({
          id: item.local_item_uuid,
          title: item.product.name,
          subtitle: `${item.qty}x · ${formatCurrency(item.line_total)}`,
          description: item.notes ? `Observação: ${item.notes}` : 'Item lançado localmente nesta comanda.',
          status:
            localComanda?.sync_status === 'conflict'
              ? 'conflict'
              : item.sync_status,
          meta:
            item.last_error
            ?? (item.server_item_uuid
              ? 'Item já vinculado ao servidor.'
              : `Lançado localmente em ${formatDateTimeBR(item.created_at)}.`),
        })),
    [localComanda?.items, localComanda?.sync_status],
  )

  function pickProduct(product: Product) {
    setSelected(product)
    setQuery(product.name)
    setResults([])
  }

  async function handleAddItem() {
    if (!selected) return

    const qtyValue = Number(qty)
    if (!Number.isFinite(qtyValue) || qtyValue <= 0) {
      setActionError('Informe uma quantidade válida.')
      return
    }

    setAdding(true)
    setActionError(null)
    setOfflineFeedback(null)
    try {
      if (isOffline) {
        if (!comanda) {
          throw new Error('Comanda não encontrada na base offline.')
        }

        const target =
          localComanda ??
          (await ensureLocalOverlayForServerComanda(comanda))

        await addLocalItem({
          comandaUuid: target.local_comanda_uuid,
          product: {
            uuid: selected.uuid,
            name: selected.name,
            unit: selected.unit,
          },
          qty: qtyValue,
          unitPrice: selected.price,
          notes: notes.trim() || null,
        })

        setOfflineFeedback('Item salvo localmente. Ele será sincronizado quando a conexão voltar.')
      } else {
        await balcaoService.addComandaItem(uuid, {
          product_uuid: selected.uuid,
          qty: qtyValue,
          notes: notes.trim() || null,
        })
      }

      setSelected(null)
      setQuery('')
      setQty('1')
      setNotes('')
      setResults([])
      await load()
    } catch (err) {
      if (err instanceof ApiRequestError && err.code === 'COMANDA_ERROR') {
        setActionError(err.message)
      } else {
        setActionError(getApiErrorMessage(err, 'Não foi possível adicionar o item.'))
      }
    } finally {
      setAdding(false)
    }
  }

  async function advanceItem(item: ComandaItem, nextStatus: 'sent_to_station' | 'delivered_to_table') {
    if (isOffline) {
      setActionError('No modo offline, o preparo não pode ser atualizado. Sincronize a comanda quando a conexão voltar.')
      return
    }

    setBusyItemUuid(item.uuid)
    setActionError(null)
    try {
      await balcaoService.updatePrepStatus(uuid, item.uuid, { prep_status: nextStatus })
      await load()
    } catch (err) {
      if (err instanceof ApiRequestError && err.code === 'INSUFFICIENT_STOCK') {
        setActionError('Estoque insuficiente para enviar este item à estação.')
      } else if (err instanceof ApiRequestError && err.code === 'COMANDA_ERROR') {
        setActionError(err.message)
      } else {
        setActionError(getApiErrorMessage(err, 'Não foi possível atualizar o item.'))
      }
    } finally {
      setBusyItemUuid(null)
    }
  }

  async function handleRefreshSnapshot() {
    setOfflineFeedback(null)
    const refreshed = await refreshSnapshot()
    if (refreshed) {
      setOfflineFeedback('Base offline do balcão atualizada com sucesso.')
    }
  }

  async function handleSync() {
    setOfflineFeedback(null)
    const result = await syncComandas()
    if (result.syncedComandas > 0 || result.syncedItems > 0 || result.failed > 0 || result.conflicts > 0) {
      setOfflineFeedback(
        `Sincronização concluída: ${result.syncedComandas} comandas e ${result.syncedItems} itens enviados, ${result.failed} falhas e ${result.conflicts} conflitos para revisão.`,
      )
    }
    await load()
  }

  async function handleRemoveOfflineItem(item: ComandaItem) {
    if (!localComanda) {
      setActionError('Este item já pertence à comanda sincronizada e não pode ser removido offline.')
      return
    }

    if (!pendingLocalItemIds.has(item.uuid)) {
      setActionError('Somente itens ainda não sincronizados podem ser removidos offline.')
      return
    }

    await removeUnsyncedLocalItem(localComanda.local_comanda_uuid, item.uuid)
    setOfflineFeedback('Item removido da fila local da comanda.')
    await load()
  }

  async function handleDiscardCurrentConflict() {
    if (!localComanda) return

    setActionError(null)
    setOfflineFeedback(null)
    await discardLocalComanda(localComanda.local_comanda_uuid)
    setOfflineFeedback('Lançamento local em conflito descartado neste dispositivo.')
    navigate('/balcao/mesas')
  }

  if (isLoading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 10 }}>
        <CircularProgress size={28} />
      </Box>
    )
  }

  if (!comanda) {
    return (
      <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 720 }}>
        <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/balcao/mesas')} sx={{ mb: 2, minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
          Voltar ao balcão
        </Button>
        <Alert severity="warning">{loadError ?? 'Comanda não encontrada.'}</Alert>
      </Box>
    )
  }

  const title = comanda.table?.label ?? comanda.label ?? 'Comanda avulsa'

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1100 }}>
      <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/balcao/mesas')} sx={{ mb: 1.5, minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
        Voltar ao balcão
      </Button>

      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={1.5}
        sx={{ justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, mb: 2 }}
      >
        <Box>
          <Typography variant="h5" sx={{ fontWeight: 700 }}>
            {title}
          </Typography>
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {comanda.table && comanda.label ? `${comanda.label} · ` : ''}
            {activeItems.length} {activeItems.length === 1 ? 'item' : 'itens'} · Subtotal {formatCurrency(subtotal)}
          </Typography>
        </Box>
        <Button
          variant="contained"
          size="large"
          disabled={!canClose || activeItems.length === 0 || isOffline || closeBlockedByOfflineState}
          onClick={() => setCloseOpen(true)}
          sx={{ minHeight: UI_SIZE.controlLarge, borderRadius: UI_RADIUS.md }}
        >
          Fechar conta
        </Button>
      </Stack>

      <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2, mb: 2 }}>
        <Stack spacing={1.25}>
          <Stack
            direction={{ xs: 'column', md: 'row' }}
            spacing={1}
            sx={{ justifyContent: 'space-between', alignItems: { xs: 'stretch', md: 'center' } }}
          >
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
              <Chip color={isOffline ? 'warning' : 'success'} label={isOffline ? 'Modo offline' : 'Modo online'} />
              <Chip
                variant="outlined"
                color={snapshot ? 'success' : 'warning'}
                label={snapshot ? 'Base offline pronta' : 'Base offline indisponível'}
              />
              <Chip variant="outlined" label={`${pendingCount} pendência${pendingCount === 1 ? '' : 's'}`} />
              {conflictCount > 0 ? <Chip color="error" variant="outlined" label={`${conflictCount} conflito${conflictCount === 1 ? '' : 's'}`} /> : null}
            </Stack>

            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
              <Button
                variant="outlined"
                onClick={() => void handleRefreshSnapshot()}
                disabled={isOffline || isRefreshingSnapshot}
              >
                {isRefreshingSnapshot ? 'Atualizando base…' : 'Atualizar base offline'}
              </Button>
              <Button
                variant="contained"
                onClick={() => void handleSync()}
                disabled={isOffline || pendingCount === 0 || isSyncing}
              >
                {isSyncing ? 'Sincronizando…' : 'Sincronizar pendências'}
              </Button>
            </Stack>
          </Stack>

          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {snapshotSyncedAt
              ? `Última base offline salva em ${formatDateTimeBR(snapshotSyncedAt)}.`
              : 'Nenhuma base offline salva ainda para o balcão.'}
            {' '}Offline, você pode lançar itens localmente; preparo e fechamento continuam online por segurança.
          </Typography>

          {isOffline ? (
            <Alert severity="info">
              O balcão está operando offline. Itens novos ficam pendentes de sincronização e a conta só pode ser fechada quando a conexão voltar.
            </Alert>
          ) : null}

          {!isOffline && closeBlockedByOfflineState ? (
            <Alert
              severity="warning"
              action={(
                <Button color="inherit" size="small" onClick={() => void handleSync()} disabled={isSyncing}>
                  {isSyncing ? 'Sincronizando…' : 'Sincronizar agora'}
                </Button>
              )}
            >
              {hasComandaConflict
                ? 'Esta comanda possui conflito de sincronização entre dispositivos. Revise ou descarte o lançamento local antes de fechar a conta.'
                : hasComandaSyncError
                  ? 'Esta comanda ainda possui falhas de sincronização. Corrija ou reenvie os itens pendentes antes de fechar a conta.'
                  : 'Esta comanda ainda possui itens locais aguardando sincronização. Feche a conta somente depois que todos forem confirmados no servidor.'}
            </Alert>
          ) : null}

          {localComanda?.sync_status === 'conflict' ? (
            <Alert severity="warning">
              {localComanda.conflict_reason ?? 'Esta comanda entrou em conflito com outro dispositivo. Atualize a base e revise o lançamento local antes de seguir.'}
            </Alert>
          ) : null}

          {snapshotError ? <Alert severity="error">{snapshotError}</Alert> : null}
          {offlineFeedback ? <Alert severity="success">{offlineFeedback}</Alert> : null}
        </Stack>
      </Paper>

      {actionError ? (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setActionError(null)}>
          {actionError}
        </Alert>
      ) : null}

      {localComanda?.sync_status === 'conflict' ? (
        <Box sx={{ mb: 2 }}>
          <OfflineContingencyGuide context="balcao-conflict" title="Conflito nesta comanda" />
        </Box>
      ) : null}

      {localComanda?.sync_status === 'conflict' ? (
        <OfflineConflictResolutionPanel
          conflicts={[localComanda]}
          isBusy={isSyncing || isRefreshingSnapshot}
          onRefreshSnapshot={() => void handleRefreshSnapshot()}
          onSync={() => void handleSync()}
          onReviewConflict={() => navigate(`/balcao/comandas/${localComanda.local_comanda_uuid}`)}
          onDiscardConflict={() => void handleDiscardCurrentConflict()}
        />
      ) : null}

      {localComanda ? (
        <OfflineSyncActivityPanel
          title="Atividade offline desta comanda"
          description="Use esta visão para garantir que todos os itens locais já foram reconciliados antes do fechamento."
          entries={offlineActivityEntries}
          emptyMessage="Esta comanda não possui itens locais pendentes neste dispositivo."
        />
      ) : null}

      <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: '360px 1fr' }, alignItems: 'start' }}>
        <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5 }}>
            Adicionar item
          </Typography>
          {!canAddItem ? (
            <Alert severity="info">Você não tem permissão para adicionar itens.</Alert>
          ) : (
            <Stack spacing={1.5}>
              <Box sx={{ position: 'relative' }}>
                <TextField
                  fullWidth
                  size="small"
                  value={query}
                  onChange={(event) => {
                    setQuery(event.target.value)
                    setSelected(null)
                  }}
                  placeholder={isOffline ? 'Buscar produto na base offline…' : 'Buscar produto…'}
                  slotProps={{
                    input: {
                      startAdornment: (
                        <InputAdornment position="start">
                          <SearchIcon fontSize="small" />
                        </InputAdornment>
                      ),
                      endAdornment: isSearching ? <CircularProgress size={16} /> : null,
                    },
                  }}
                />
                {results.length > 0 && !selected ? (
                  <Paper
                    sx={{
                      position: 'absolute',
                      zIndex: 5,
                      mt: 0.5,
                      left: 0,
                      right: 0,
                      ...ELEVATED_SURFACE_SX,
                      maxHeight: 280,
                      overflowY: 'auto',
                    }}
                  >
                    <Stack divider={<Divider />}>
                      {results.map((product) => (
                        <Box
                          key={product.uuid}
                          role="button"
                          tabIndex={0}
                          onClick={() => pickProduct(product)}
                          onKeyDown={(event) => {
                            if (event.key === 'Enter' || event.key === ' ') {
                              event.preventDefault()
                              pickProduct(product)
                            }
                          }}
                          sx={{
                            display: 'flex',
                            justifyContent: 'space-between',
                            gap: 1,
                            px: 1.5,
                            py: 1,
                            cursor: 'pointer',
                            '&:hover, &:focus-visible': { background: 'var(--mk-surface-soft)', outline: 'none' },
                          }}
                        >
                          <Typography variant="body2" sx={{ fontWeight: 600 }} noWrap>
                            {product.name}
                          </Typography>
                          <Typography variant="body2" sx={{ fontWeight: 600, whiteSpace: 'nowrap' }}>
                            {formatCurrency(product.price)}
                          </Typography>
                        </Box>
                      ))}
                    </Stack>
                  </Paper>
                ) : null}
              </Box>

              <Stack direction="row" spacing={1}>
                <TextField
                  size="small"
                  type="number"
                  label="Qtd."
                  value={qty}
                  onChange={(event) => setQty(event.target.value)}
                  sx={{ width: 100 }}
                  slotProps={{ htmlInput: { min: 0, step: 'any' } }}
                />
                <TextField
                  size="small"
                  label="Observação"
                  value={notes}
                  onChange={(event) => setNotes(event.target.value)}
                  fullWidth
                  placeholder="Ex.: sem cebola"
                  slotProps={{ htmlInput: { maxLength: 1000 } }}
                />
              </Stack>

              <Button
                variant="contained"
                startIcon={<AddIcon />}
                disabled={!selected || isAdding || (isOffline && !snapshot)}
                onClick={handleAddItem}
              >
                {isAdding ? 'Adicionando…' : isOffline ? 'Salvar item offline' : 'Adicionar à comanda'}
              </Button>
            </Stack>
          )}
        </Paper>

        <Paper sx={{ ...ELEVATED_SURFACE_SX, overflow: 'hidden' }}>
          <Box sx={{ px: 2, py: 1.5, borderBottom: '1px solid var(--mk-border)' }}>
            <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
              Itens da comanda
            </Typography>
          </Box>
          {(comanda.items ?? []).length === 0 ? (
            <Box sx={{ px: 3, py: 6, textAlign: 'center', color: 'var(--mk-muted)' }}>
              <Typography variant="body2">Nenhum item ainda. Adicione o primeiro pela busca ao lado.</Typography>
            </Box>
          ) : (
            <Stack divider={<Divider />}>
              {(comanda.items ?? []).map((item) => {
                const meta = PREP_STATUS_META[item.prep_status]
                const isCancelled = item.prep_status === 'cancelled'
                const busy = busyItemUuid === item.uuid
                const isPendingOffline = pendingLocalItemIds.has(item.uuid)

                return (
                  <Box key={item.uuid} sx={{ px: 2, py: 1.5, opacity: isCancelled ? 0.55 : 1 }}>
                    <Stack direction="row" sx={{ justifyContent: 'space-between', gap: 1 }}>
                      <Box sx={{ minWidth: 0 }}>
                        <Typography
                          variant="body2"
                          sx={{ fontWeight: 600, textDecoration: isCancelled ? 'line-through' : 'none' }}
                          noWrap
                        >
                          {item.qty}× {item.product?.name ?? 'Produto'}
                        </Typography>
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mt: 0.25, flexWrap: 'wrap' }}>
                          <Chip
                            size="small"
                            label={meta.label}
                            sx={{
                              height: 20,
                              fontSize: 11,
                              fontWeight: 600,
                              color: meta.color,
                              borderColor: meta.color,
                              bgcolor: 'transparent',
                              border: '1px solid',
                            }}
                          />
                          {isPendingOffline ? <Chip size="small" color="warning" label="Pendente de sincronização" /> : null}
                          {item.station ? (
                            <Typography variant="caption" sx={{ color: 'var(--mk-muted)' }}>
                              {item.station.name}
                            </Typography>
                          ) : null}
                          {item.notes ? (
                            <Typography variant="caption" sx={{ color: 'var(--mk-muted)' }} noWrap>
                              · {item.notes}
                            </Typography>
                          ) : null}
                        </Stack>
                        {isCancelled && item.cancelled_reason ? (
                          <Typography variant="caption" sx={{ color: 'var(--mk-danger)' }}>
                            Cancelado: {item.cancelled_reason}
                          </Typography>
                        ) : null}
                      </Box>
                      <Typography variant="body2" sx={{ fontWeight: 700, whiteSpace: 'nowrap' }}>
                        {formatCurrency(item.line_total)}
                      </Typography>
                    </Stack>

                    {!isCancelled ? (
                      <Stack direction="row" spacing={1} sx={{ mt: 1, flexWrap: 'wrap' }}>
                        {isOffline ? (
                          <Button
                            size="small"
                            color="error"
                            disabled={!isPendingOffline}
                            onClick={() => void handleRemoveOfflineItem(item)}
                          >
                            Remover item
                          </Button>
                        ) : canPrep ? (
                          <>
                            {item.prep_status === 'queued' ? (
                              <Button
                                size="small"
                                variant="outlined"
                                startIcon={<SendOutlinedIcon />}
                                disabled={busy}
                                onClick={() => void advanceItem(item, 'sent_to_station')}
                              >
                                Enviar à estação
                              </Button>
                            ) : null}
                            {item.prep_status === 'ready' ? (
                              <Button
                                size="small"
                                variant="outlined"
                                color="success"
                                disabled={busy}
                                onClick={() => void advanceItem(item, 'delivered_to_table')}
                              >
                                Entregar na mesa
                              </Button>
                            ) : null}
                            <Button size="small" color="error" disabled={busy} onClick={() => setCancelTarget(item)}>
                              Cancelar
                            </Button>
                          </>
                        ) : null}
                      </Stack>
                    ) : null}
                  </Box>
                )
              })}
            </Stack>
          )}
        </Paper>
      </Box>

      {cancelTarget && !isOffline ? (
        <CancelItemDialog
          comandaUuid={uuid}
          item={cancelTarget}
          onClose={() => setCancelTarget(null)}
          onCancelled={() => {
            setCancelTarget(null)
            void load()
          }}
        />
      ) : null}

      {isCloseOpen && !isOffline ? (
        <CloseComandaModal
          comanda={comanda}
          subtotal={subtotal}
          onClose={() => setCloseOpen(false)}
          onCompleted={(order, payments) => {
            navigate('/balcao/recibo', {
              state: {
                order,
                payments,
                comandaTitle: title,
                tableLabel: comanda.table?.label ?? null,
              },
            })
          }}
        />
      ) : null}
    </Box>
  )
}
