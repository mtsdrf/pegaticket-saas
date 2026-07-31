import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import PointOfSaleOutlinedIcon from '@mui/icons-material/PointOfSaleOutlined'
import SearchIcon from '@mui/icons-material/Search'
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  IconButton,
  InputAdornment,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { useCallback, useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { AsyncAutocomplete } from '../../components/crud/AsyncAutocomplete'
import { OfflineContingencyGuide } from '../../components/shared/OfflineContingencyGuide'
import { OfflineSyncActivityPanel, type OfflineActivityEntry } from '../../components/shared/OfflineSyncActivityPanel'
import { OperatorSessionBadge } from '../../components/pdv/OperatorSessionBadge'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { usePdvOffline } from '../../hooks/usePdvOffline'
import { useOperatorSession } from '../../hooks/useOperatorSession'
import { usePdvHotkeys } from '../../hooks/usePdvHotkeys'
import * as clientService from '../../services/clientService'
import * as pdvService from '../../services/pdvService'
import * as productService from '../../services/productService'
import { FORM_GRID_2_SX, PAGE_CONTAINER_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { Client } from '../../types/client'
import type { Order } from '../../types/order'
import type { Product } from '../../types/product'
import type { CreatePdvSalePayload, PdvPaymentMethod, PdvSalePaymentPayload } from '../../types/pdv'
import { PDV_PAYMENT_METHODS, PDV_PAYMENT_METHOD_LABELS } from '../../constants/pdvPaymentMethods'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'
import { usePdvSession } from './PdvSessionContext'

interface CartLine {
  id: string
  product: Product
  quantity: number
  unitPrice: number
}

interface PaymentLine {
  id: string
  method: PdvPaymentMethod
  amount: string
}

function toCents(value: number): number {
  return Math.round(value * 100)
}

function snapshotProductToProduct(product: {
  uuid: string
  name: string
  sku: string | null
  barcode: string | null
  unit: string | null
  price: number
  stock_quantity: number
}): Product {
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
    stock_quantity: product.stock_quantity,
    surcharge_rate: null,
    is_lot_controlled: false,
    is_expiry_controlled: false,
    is_serial_controlled: false,
    min_stock: null,
    max_stock: null,
    reorder_point: null,
    reorder_qty: null,
    product_type: null,
    created_at: new Date().toISOString(),
  }
}

export function PdvSalePage() {
  const navigate = useNavigate()
  const { activeTenantUuid } = useAuth()
  const { session, isOfflineSnapshot } = usePdvSession()
  const { can } = useAccessControl()
  const canSell = can(ACCESS.pdvSell)
  const [offlineFeedback, setOfflineFeedback] = useState<string | null>(null)
  const {
    isOffline,
    snapshot,
    snapshotSyncedAt,
    queuedSales,
    unsyncedCount,
    latestQueueError,
    snapshotError,
    isRefreshingSnapshot,
    isSyncing,
    refreshSnapshot,
    syncQueuedSales,
    queueSale,
    findSnapshotProduct,
    buildQueuedOrderForReceipt,
  } = usePdvOffline(activeTenantUuid, session.uuid)

  const searchRef = useRef<HTMLInputElement>(null)
  const [query, setQuery] = useState('')
  const debouncedQuery = useDebouncedValue(query, 300)
  const [results, setResults] = useState<Product[]>([])
  const [isSearching, setIsSearching] = useState(false)
  const [searchNotice, setSearchNotice] = useState<string | null>(null)

  const [cart, setCart] = useState<CartLine[]>([])
  const [selectedId, setSelectedId] = useState<string | null>(null)

  const [isCheckoutOpen, setCheckoutOpen] = useState(false)

  const focusSearch = useCallback(() => {
    searchRef.current?.focus()
    searchRef.current?.select()
  }, [])

  useEffect(() => {
    focusSearch()
  }, [focusSearch])

  useEffect(() => {
    if (isOffline || !activeTenantUuid) return
    if (!snapshot || snapshot.cash_session?.uuid !== session.uuid) {
      void refreshSnapshot()
    }
  }, [activeTenantUuid, isOffline, refreshSnapshot, session.uuid, snapshot])

  const total = useMemo(() => cart.reduce((sum, line) => sum + line.quantity * line.unitPrice, 0), [cart])
  const hasOfflineSnapshot = Boolean(snapshot)
  const canOperateOffline = Boolean(snapshot?.cash_session?.uuid && snapshot.cash_session.uuid === session.uuid)
  const searchDisabled = isOffline && !hasOfflineSnapshot
  const canFinalizeInCurrentMode = canSell && cart.length > 0 && (!isOffline || canOperateOffline)
  const offlineActivityEntries = useMemo<OfflineActivityEntry[]>(
    () =>
      [...queuedSales]
        .sort((a, b) => b.created_at.localeCompare(a.created_at))
        .slice(0, 8)
        .map((sale) => ({
          id: sale.local_sale_uuid,
          title: `Venda ${sale.receipt_order.codigo}`,
          subtitle: sale.client_name ? `Cliente: ${sale.client_name}` : 'Consumidor final',
          description: `${sale.receipt_order.items.length} item${sale.receipt_order.items.length === 1 ? '' : 's'} · ${formatCurrency(sale.receipt_order.total_amount)}`,
          status: sale.status,
          meta:
            sale.status === 'synced' && sale.synced_at
              ? `Sincronizada em ${formatDateTimeBR(sale.synced_at)}.`
              : sale.status === 'error' && sale.last_error
                ? sale.last_error
                : `Registrada em ${formatDateTimeBR(sale.created_at)}.`,
        })),
    [queuedSales],
  )

  const addProduct = useCallback((product: Product) => {
    setCart((current) => {
      const existing = current.find((line) => line.product.uuid === product.uuid)
      if (existing) {
        return current.map((line) =>
          line.product.uuid === product.uuid ? { ...line, quantity: line.quantity + 1 } : line,
        )
      }
      return [...current, { id: crypto.randomUUID(), product, quantity: 1, unitPrice: product.price }]
    })
    setQuery('')
    setResults([])
    setSearchNotice(null)
    focusSearch()
  }, [focusSearch])

  // Busca por nome/sku (debounced) — lista de resultados para clique/seta.
  useEffect(() => {
    const term = debouncedQuery.trim()
    if (term.length < 2) {
      setResults([])
      return
    }

    const applySnapshotSearch = () => {
      const normalized = term.toLowerCase()
      const matches = (snapshot?.products ?? [])
        .filter((product) =>
          product.name.toLowerCase().includes(normalized)
          || product.sku?.toLowerCase().includes(normalized)
          || product.barcode?.includes(term),
        )
        .slice(0, 8)
        .map(snapshotProductToProduct)
      setResults(matches)
      setIsSearching(false)
    }

    if (isOffline) {
      applySnapshotSearch()
      return
    }

    let cancelled = false
    setIsSearching(true)
    productService
      .listProducts({ q: term, is_available: true, per_page: 8, sort_by: 'name', sort_dir: 'asc' })
      .then((page) => {
        if (!cancelled) setResults(page.items)
      })
      .catch(() => {
        if (!cancelled) {
          applySnapshotSearch()
        }
      })
      .finally(() => {
        if (!cancelled) setIsSearching(false)
      })
    return () => {
      cancelled = true
    }
  }, [debouncedQuery, isOffline, snapshot])

  // Enter no campo: primeiro tenta match EXATO de código de barras (leitor de
  // código digita os dígitos + Enter); se não bater, adiciona o 1º resultado
  // da busca por nome; senão avisa "não encontrado".
  async function handleSearchKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key !== 'Enter') return
    event.preventDefault()
    const term = query.trim()
    if (!term) return

    if (isOffline) {
      const byBarcode = findSnapshotProduct((product) => product.barcode === term)
      if (byBarcode) {
        addProduct(snapshotProductToProduct(byBarcode))
        return
      }

      if (results.length > 0) {
        addProduct(results[0])
        return
      }

      setSearchNotice(`Nenhum produto da base offline encontrado para "${term}".`)
      return
    }

    try {
      const byBarcode = await productService.listProducts({ barcode: term, per_page: 1 })
      if (byBarcode.items.length === 1) {
        addProduct(byBarcode.items[0])
        return
      }
    } catch {
      // Ignora falha de barcode e cai pro fallback por nome abaixo.
    }

    if (results.length > 0) {
      addProduct(results[0])
      return
    }
    setSearchNotice(`Nenhum produto encontrado para "${term}".`)
  }

  function updateQuantity(id: string, raw: string) {
    const next = Number(raw)
    setCart((current) =>
      current.map((line) =>
        line.id === id ? { ...line, quantity: Number.isFinite(next) && next > 0 ? next : line.quantity } : line,
      ),
    )
  }

  const removeLine = useCallback((id: string) => {
    setCart((current) => current.filter((line) => line.id !== id))
    setSelectedId((current) => (current === id ? null : current))
  }, [])

  const removeSelected = useCallback(() => {
    if (selectedId) removeLine(selectedId)
  }, [selectedId, removeLine])

  const openCheckout = useCallback(() => {
    if (cart.length === 0 || !canFinalizeInCurrentMode) return
    setCheckoutOpen(true)
  }, [canFinalizeInCurrentMode, cart.length])

  async function handleRefreshSnapshot() {
    setOfflineFeedback(null)
    const refreshed = await refreshSnapshot()
    if (refreshed) {
      setOfflineFeedback('Base offline do PDV atualizada para este caixa.')
    }
  }

  async function handleSyncQueuedSales() {
    setOfflineFeedback(null)
    const result = await syncQueuedSales()
    if (result.synced > 0 && result.failed === 0) {
      setOfflineFeedback(`Pendências sincronizadas com sucesso: ${result.synced}.`)
      return
    }
    if (result.synced > 0 || result.failed > 0) {
      setOfflineFeedback(`Sincronização concluída: ${result.synced} enviadas, ${result.failed} com pendência.`)
    }
  }

  usePdvHotkeys({
    onFocusSearch: focusSearch,
    onFinalize: openCheckout,
    onRemoveSelected: removeSelected,
  })

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1280 }}>
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        spacing={1.5}
        sx={{ justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, mb: 2 }}
      >
        <Box>
          <Typography variant="h5" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
            <PointOfSaleOutlinedIcon /> Ponto de venda
          </Typography>
          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            Caixa aberto{session.cash_register ? ` — ${session.cash_register.name}` : ''}. Bipe ou busque um produto (F2),
            finalize com F4.
          </Typography>
        </Box>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
          <OperatorSessionBadge />
          <Button variant="outlined" onClick={() => navigate('/pdv/fechar')} sx={{ whiteSpace: 'nowrap', minHeight: UI_SIZE.control, borderRadius: UI_RADIUS.md }}>
            Caixa / Fechamento
          </Button>
        </Stack>
      </Stack>

      <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2, mb: 2 }}>
        <Stack spacing={1.25}>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', alignItems: { xs: 'stretch', md: 'center' } }}>
            <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
              <Chip color={isOffline ? 'warning' : 'success'} label={isOffline ? 'Modo offline' : 'Modo online'} />
              <Chip
                variant="outlined"
                color={canOperateOffline || !isOffline ? 'success' : 'warning'}
                label={hasOfflineSnapshot ? 'Base offline pronta' : 'Base offline indisponível'}
              />
              <Chip variant="outlined" label={`${unsyncedCount} pendência${unsyncedCount === 1 ? '' : 's'}`} />
            </Stack>

            <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap' }}>
              <Button variant="outlined" onClick={() => void handleRefreshSnapshot()} disabled={isOffline || isRefreshingSnapshot}>
                {isRefreshingSnapshot ? 'Atualizando base…' : 'Atualizar base offline'}
              </Button>
              <Button variant="contained" onClick={() => void handleSyncQueuedSales()} disabled={isOffline || unsyncedCount === 0 || isSyncing}>
                {isSyncing ? 'Sincronizando…' : 'Sincronizar pendências'}
              </Button>
            </Stack>
          </Stack>

          <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
            {snapshotSyncedAt
              ? `Última base offline salva em ${formatDateTimeBR(snapshotSyncedAt)}.`
              : 'Nenhuma base offline salva ainda para este PDV.'}
            {' '}
            Offline, o PDV aceita somente pagamentos em dinheiro e registra as vendas para sincronizar depois.
          </Typography>

          {isOfflineSnapshot ? (
            <Alert severity="info">
              O caixa foi reaberto pelo snapshot offline salvo localmente. Sangria, suprimento e fechamento continuam bloqueados até a conexão voltar.
            </Alert>
          ) : null}

          {isOffline && !canOperateOffline ? (
            <Alert severity="warning">
              Este PDV está sem conexão e não possui uma base offline válida para o caixa atual. Conecte-se e atualize a base antes de operar offline.
            </Alert>
          ) : null}

          {snapshotError ? <Alert severity="error">{snapshotError}</Alert> : null}
          {latestQueueError ? <Alert severity="warning">{latestQueueError}</Alert> : null}
          {offlineFeedback ? <Alert severity="success">{offlineFeedback}</Alert> : null}
        </Stack>
      </Paper>

      {isOffline && !canOperateOffline ? (
        <Box sx={{ mb: 2 }}>
          <OfflineContingencyGuide context="pdv-no-snapshot" />
        </Box>
      ) : null}

      <OfflineSyncActivityPanel
        title="Atividade offline do PDV"
        description="Acompanhe aqui as vendas locais deste caixa até que todas sejam confirmadas no servidor."
        entries={offlineActivityEntries}
        emptyMessage="Nenhuma venda offline registrada neste PDV até agora."
      />

      <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: '1fr', md: '1fr 360px' }, alignItems: 'start' }}>
        {/* Coluna esquerda: busca + carrinho */}
        <Stack spacing={2}>
          <Paper sx={{ ...ELEVATED_SURFACE_SX, p: 2.25 }}>
            <TextField
              inputRef={searchRef}
              fullWidth
              autoFocus
              value={query}
              disabled={searchDisabled}
              onChange={(event) => {
                setQuery(event.target.value)
                setSearchNotice(null)
              }}
              onKeyDown={handleSearchKeyDown}
              placeholder="Código de barras, nome ou SKU — Enter para adicionar"
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

            {searchNotice ? (
              <Alert severity="warning" sx={{ mt: 1.5 }}>
                {searchNotice}
              </Alert>
            ) : null}

            {results.length > 0 ? (
              <Stack spacing={0.5} sx={{ mt: 1.5 }}>
                {results.map((product, index) => (
                  <Box
                    key={product.uuid}
                    role="button"
                    tabIndex={0}
                    onClick={() => addProduct(product)}
                    onKeyDown={(event) => {
                      if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault()
                        addProduct(product)
                      }
                    }}
                    sx={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      gap: 1,
                      px: 1.5,
                      py: 1,
                      borderRadius: 'var(--mk-radius-md)',
                      cursor: 'pointer',
                      border: '1px solid transparent',
                      '&:hover, &:focus-visible': {
                        background: 'var(--mk-surface-soft)',
                        borderColor: 'var(--mk-border)',
                        outline: 'none',
                      },
                    }}
                  >
                    <Box sx={{ minWidth: 0 }}>
                      <Typography variant="body2" sx={{ fontWeight: 600 }} noWrap>
                        {index === 0 ? '↵ ' : ''}
                        {product.name}
                      </Typography>
                      <Typography variant="caption" sx={{ color: 'var(--mk-muted)' }}>
                        {product.sku ? `SKU ${product.sku}` : 'Sem SKU'}
                        {product.barcode ? ` · ${product.barcode}` : ''}
                      </Typography>
                    </Box>
                    <Typography variant="body2" sx={{ fontWeight: 600, whiteSpace: 'nowrap' }}>
                      {formatCurrency(product.price)}
                    </Typography>
                  </Box>
                ))}
              </Stack>
            ) : null}
          </Paper>

          <Paper sx={{ ...ELEVATED_SURFACE_SX, overflow: 'hidden' }}>
            <Box sx={{ px: 2, py: 1.5, borderBottom: '1px solid var(--mk-border)' }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                Itens ({cart.length})
              </Typography>
            </Box>

            {cart.length === 0 ? (
              <Box sx={{ px: 3, py: 6, textAlign: 'center', color: 'var(--mk-muted)' }}>
                <Typography variant="body2">Nenhum item ainda. Bipe um produto para começar a venda.</Typography>
              </Box>
            ) : (
              <Stack divider={<Divider />}>
                {cart.map((line) => {
                  const selected = line.id === selectedId
                  return (
                    <Box
                      key={line.id}
                      onClick={() => setSelectedId(line.id)}
                      sx={{
                        display: 'grid',
                        gridTemplateColumns: '1fr auto',
                        gap: 1,
                        px: 2,
                        py: 1.5,
                        cursor: 'pointer',
                        background: selected ? 'var(--mk-surface-soft)' : 'transparent',
                      }}
                    >
                      <Box sx={{ minWidth: 0 }}>
                        <Typography variant="body2" sx={{ fontWeight: 600 }} noWrap>
                          {line.product.name}
                        </Typography>
                        <Typography variant="caption" sx={{ color: 'var(--mk-muted)' }}>
                          {formatCurrency(line.unitPrice)} un.
                          {line.product.unit ? ` · ${line.product.unit}` : ''}
                        </Typography>
                      </Box>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        <TextField
                          size="small"
                          type="number"
                          value={line.quantity}
                          onClick={(event) => event.stopPropagation()}
                          onChange={(event) => updateQuantity(line.id, event.target.value)}
                          slotProps={{ htmlInput: { min: 1, step: '1', style: { width: 56, textAlign: 'center' } } }}
                        />
                        <Typography variant="body2" sx={{ fontWeight: 700, minWidth: 84, textAlign: 'right' }}>
                          {formatCurrency(line.quantity * line.unitPrice)}
                        </Typography>
                        <IconButton
                          size="small"
                          aria-label={`Remover ${line.product.name}`}
                          onClick={(event) => {
                            event.stopPropagation()
                            removeLine(line.id)
                          }}
                        >
                          <DeleteOutlineIcon fontSize="small" />
                        </IconButton>
                      </Stack>
                    </Box>
                  )
                })}
              </Stack>
            )}
          </Paper>
        </Stack>

        {/* Coluna direita: resumo + finalizar (sticky no desktop) */}
        <Paper
          sx={{
            p: 2.5,
            ...ELEVATED_SURFACE_SX,
            position: { md: 'sticky' },
            top: { md: 88 },
          }}
        >
          <Stack spacing={2}>
            <Box>
              <Typography variant="caption" sx={{ color: 'var(--mk-muted)', textTransform: 'uppercase', letterSpacing: 0.6 }}>
                Total da venda
              </Typography>
              <Typography variant="h4" sx={{ fontWeight: 800 }}>
                {formatCurrency(total)}
              </Typography>
            </Box>

            {!canSell ? (
              <Alert severity="warning">Você não tem permissão para registrar vendas.</Alert>
            ) : null}
            {isOffline ? (
              <Alert severity={canOperateOffline ? 'info' : 'warning'}>
                {canOperateOffline
                  ? 'PDV em modo offline controlado. Somente dinheiro pode ser registrado até a sincronização.'
                  : 'O PDV está offline e sem base válida para este caixa. A finalização da venda foi bloqueada.'}
              </Alert>
            ) : null}

            <Button
              variant="contained"
              size="large"
              disabled={!canFinalizeInCurrentMode}
              onClick={openCheckout}
            >
              Finalizar venda (F4)
            </Button>
            <Typography variant="caption" sx={{ color: 'var(--mk-muted)', textAlign: 'center' }}>
              F2 busca · F4 finaliza · Delete remove o item selecionado
            </Typography>
          </Stack>
        </Paper>
      </Box>

      {isCheckoutOpen ? (
        <PdvCheckoutModal
          total={total}
          cart={cart}
          sessionUuid={session.uuid}
          isOfflineMode={isOffline}
          offlinePaymentMethods={snapshot?.offline_payment_methods ?? ['cash']}
          onClose={() => setCheckoutOpen(false)}
          onCompleted={(order, payments, clientName) => {
            navigate('/pdv/recibo', { state: { order, payments, clientName } })
          }}
          onQueueOfflineSale={async (payload, payments, clientName) => {
            const queued = await queueSale({
              payload,
              clientName,
              receiptOrder: {
                codigo: `OFF-${new Date().getTime().toString().slice(-6)}`,
                total_amount: total,
                created_at: new Date().toISOString(),
                items: cart.map((line) => ({
                  uuid: line.id,
                  product: {
                    uuid: line.product.uuid,
                    name: line.product.name,
                    unit: line.product.unit,
                  },
                  quantity: line.quantity,
                  unit_price: line.unitPrice,
                  line_total: line.quantity * line.unitPrice,
                })),
              },
            })

            navigate('/pdv/recibo', {
              state: {
                order: buildQueuedOrderForReceipt(queued),
                payments,
                clientName,
                isOfflinePending: true,
                localSaleUuid: queued.local_sale_uuid,
              },
            })
          }}
        />
      ) : null}
    </Box>
  )
}

/* ------------------------------------------------------------------ */
/* Modal de finalização — split de pagamento + cliente opcional        */
/* ------------------------------------------------------------------ */

interface PdvCheckoutModalProps {
  total: number
  cart: CartLine[]
  sessionUuid: string
  isOfflineMode: boolean
  offlinePaymentMethods: PdvPaymentMethod[]
  onClose: () => void
  onCompleted: (order: Order, payments: PdvSalePaymentPayload[], clientName: string | null) => void
  onQueueOfflineSale: (
    payload: CreatePdvSalePayload,
    payments: PdvSalePaymentPayload[],
    clientName: string | null,
  ) => Promise<void>
}

function PdvCheckoutModal({
  total,
  cart,
  sessionUuid,
  isOfflineMode,
  offlinePaymentMethods,
  onClose,
  onCompleted,
  onQueueOfflineSale,
}: PdvCheckoutModalProps) {
  const { operator } = useOperatorSession()
  const [client, setClient] = useState<Client | null>(null)
  const [payments, setPayments] = useState<PaymentLine[]>(() => [
    { id: crypto.randomUUID(), method: 'cash', amount: total.toFixed(2) },
  ])
  const [isSubmitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const fetchClients = useCallback(async (queryText: string) => {
    const result = await clientService.listClients({
      name: queryText || undefined,
      per_page: 20,
      sort_by: 'name',
      sort_dir: 'asc',
    })
    return result.clients
  }, [])

  const paidTotal = useMemo(
    () => payments.reduce((sum, line) => sum + (Number.isFinite(Number(line.amount)) ? Number(line.amount) : 0), 0),
    [payments],
  )
  const remaining = total - paidTotal
  const matches = toCents(paidTotal) === toCents(total)

  function addPaymentLine() {
    const suggested = remaining > 0 ? remaining.toFixed(2) : '0.00'
    setPayments((current) => [...current, { id: crypto.randomUUID(), method: 'cash', amount: suggested }])
  }

  function updatePayment(id: string, patch: Partial<Pick<PaymentLine, 'method' | 'amount'>>) {
    setPayments((current) => current.map((line) => (line.id === id ? { ...line, ...patch } : line)))
  }

  function removePayment(id: string) {
    setPayments((current) => (current.length > 1 ? current.filter((line) => line.id !== id) : current))
  }

  async function handleConfirm() {
    if (!matches) return
    setSubmitting(true)
    setError(null)

    const payload: CreatePdvSalePayload = {
      cash_session_uuid: sessionUuid,
      client_uuid: client?.uuid,
      items: cart.map((line) => ({
        product_uuid: line.product.uuid,
        quantity: line.quantity,
        unit_price: line.unitPrice,
      })),
      payments: payments.map((line) => ({ method: line.method, amount: Number(line.amount) })),
      operator_uuid: operator?.uuid,
    }

    if (isOfflineMode) {
      const hasBlockedMethod = payload.payments.some((payment) => !offlinePaymentMethods.includes(payment.method))
      if (hasBlockedMethod) {
        setError('Offline, o PDV aceita somente as formas de pagamento liberadas na base local. Use dinheiro para concluir.')
        setSubmitting(false)
        return
      }

      try {
        await onQueueOfflineSale(payload, payload.payments, null)
        return
      } catch (err) {
        setError(getApiErrorMessage(err, 'Não foi possível salvar esta venda offline agora.'))
        setSubmitting(false)
        return
      }
    }

    try {
      const order = await pdvService.createPdvSale(payload)
      onCompleted(
        order,
        payload.payments,
        client?.name ?? null,
      )
    } catch (err) {
      if (err instanceof ApiRequestError && err.code === 'PAYMENT_AMOUNT_MISMATCH') {
        setError('A soma das formas de pagamento não confere com o total da venda. Revise os valores.')
      } else if (err instanceof ApiRequestError && err.code === 'CASH_SESSION_ERROR') {
        setError('O caixa não está mais aberto. Reabra o caixa para registrar a venda.')
      } else {
        setError(getApiErrorMessage(err, 'Não foi possível concluir a venda agora.'))
      }
      setSubmitting(false)
    }
  }

  return (
    <Dialog open onClose={isSubmitting ? undefined : onClose} fullWidth maxWidth="sm">
      <DialogTitle sx={{ fontWeight: 700 }}>Finalizar venda</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2.5}>
          <Box
            sx={{
              display: 'flex',
              justifyContent: 'space-between',
              p: 1.5,
              ...SOFT_PANEL_SX,
            }}
          >
            <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
              Total
            </Typography>
            <Typography variant="subtitle1" sx={{ fontWeight: 800 }}>
              {formatCurrency(total)}
            </Typography>
          </Box>

          <AsyncAutocomplete<Client>
            label="Cliente (opcional)"
            value={client}
            onChange={setClient}
            fetchOptions={fetchClients}
            getOptionLabel={(option) => option.name}
            getOptionKey={(option) => option.uuid}
            placeholder="Consumidor final"
            disabled={isOfflineMode}
          />
          {isOfflineMode ? (
            <Alert severity="info" variant="outlined">
              A identificação de cliente fica limitada no modo offline. Esta venda será registrada como consumidor final e sincronizada depois.
            </Alert>
          ) : null}

          <Box>
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                Pagamentos
              </Typography>
              <Button size="small" startIcon={<AddIcon />} onClick={addPaymentLine}>
                Adicionar forma
              </Button>
            </Stack>

            <Stack spacing={1.25}>
              {payments.map((line) => (
                <Stack key={line.id} direction="row" spacing={1}>
                  <TextField
                    select
                    size="small"
                    label="Forma"
                    value={line.method}
                    onChange={(event) => updatePayment(line.id, { method: event.target.value as PdvPaymentMethod })}
                    sx={{ flex: 1 }}
                  >
                    {(isOfflineMode ? PDV_PAYMENT_METHODS.filter((method) => offlinePaymentMethods.includes(method)) : PDV_PAYMENT_METHODS).map((method) => (
                      <MenuItem key={method} value={method}>
                        {PDV_PAYMENT_METHOD_LABELS[method]}
                      </MenuItem>
                    ))}
                  </TextField>
                  <TextField
                    size="small"
                    label="Valor"
                    type="number"
                    value={line.amount}
                    onChange={(event) => updatePayment(line.id, { amount: event.target.value })}
                    sx={{ width: 130 }}
                    slotProps={{
                      htmlInput: { min: 0, step: '0.01' },
                      input: { startAdornment: <InputAdornment position="start">R$</InputAdornment> },
                    }}
                  />
                  <IconButton
                    aria-label="Remover forma de pagamento"
                    onClick={() => removePayment(line.id)}
                    disabled={payments.length <= 1}
                  >
                    <DeleteOutlineIcon fontSize="small" />
                  </IconButton>
                </Stack>
              ))}
            </Stack>
          </Box>

          <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
            <Typography variant="body2" sx={{ color: 'var(--mk-muted)' }}>
              Pago {formatCurrency(paidTotal)}
            </Typography>
            {matches ? (
              <Chip size="small" color="success" label="Confere" />
            ) : (
              <Chip
                size="small"
                color={remaining > 0 ? 'warning' : 'error'}
                label={remaining > 0 ? `Falta ${formatCurrency(remaining)}` : `Sobra ${formatCurrency(-remaining)}`}
              />
            )}
          </Stack>

          {error ? <Alert severity="error">{error}</Alert> : null}
        </Stack>
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={isSubmitting}>
          Cancelar
        </Button>
        <Button variant="contained" onClick={handleConfirm} disabled={!matches || isSubmitting}>
          {isSubmitting ? (isOfflineMode ? 'Salvando…' : 'Concluindo…') : isOfflineMode ? 'Salvar venda offline' : 'Confirmar venda'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
