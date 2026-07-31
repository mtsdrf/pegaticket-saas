import PaidOutlinedIcon from '@mui/icons-material/PaidOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import WhatsAppIcon from '@mui/icons-material/WhatsApp'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import {
  Alert,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Paper,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { MetricCard } from '../../components/dashboard/MetricCard'
import { useAuth } from '../../hooks/useAuth'
import * as orderService from '../../services/orderService'
import * as reportDetailService from '../../services/reportDetailService'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { ApiRequestError, getApiErrorMessage } from '../../types/api'
import type { ReceivableInteraction, ReceivableReportItem, ReceivableSummary } from '../../types/reportDetail'
import { formatCurrency, formatDateBR, toDateOnly } from '../../utils/format'
import { buildReceivableWhatsAppMessage, buildWhatsAppUrl, isDigitsOnlyPhone } from '../../utils/whatsApp'

type ReceivableAgingBucketFilter = 'all' | 'current' | 'overdue_1_30' | 'overdue_31_60' | 'overdue_61_90' | 'overdue_90_plus'

export function ReceivableReportListPage() {
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [processingRowKey, setProcessingRowKey] = useState<string | null>(null)
  const [summary, setSummary] = useState<ReceivableSummary | null>(null)
  const [isLoadingSummary, setIsLoadingSummary] = useState(true)
  const [agingBucket, setAgingBucket] = useState<ReceivableAgingBucketFilter>('all')
  const [selectedReceivable, setSelectedReceivable] = useState<ReceivableReportItem | null>(null)
  const [interactions, setInteractions] = useState<ReceivableInteraction[]>([])
  const [isLoadingInteractions, setIsLoadingInteractions] = useState(false)
  const [interactionError, setInteractionError] = useState<string | null>(null)
  const [interactionFieldErrors, setInteractionFieldErrors] = useState<Record<string, string[]>>({})
  const [isSavingInteraction, setIsSavingInteraction] = useState(false)
  const [interactionType, setInteractionType] = useState<'note' | 'promise'>('note')
  const [interactionNotes, setInteractionNotes] = useState('')
  const [promiseAmount, setPromiseAmount] = useState('')
  const [promiseDueDate, setPromiseDueDate] = useState('')

  useEffect(() => {
    if (!activeTenantUuid) return
    setIsLoadingSummary(true)
    reportDetailService
      .getReceivableSummary()
      .then(setSummary)
      .catch((error) => setActionError(getApiErrorMessage(error, 'Não foi possível carregar o resumo dos recebíveis agora.')))
      .finally(() => setIsLoadingSummary(false))
  }, [activeTenantUuid])

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<ReceivableReportItem>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await reportDetailService.listReceivableReports({
        ...filters,
        ...(agingBucket !== 'all' ? { aging_bucket: agingBucket } : {}),
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, agingBucket],
  )

  const payReceivable = useCallback(async (row: ReceivableReportItem) => {
    setActionError(null)
    setProcessingRowKey(row.row_key)
    try {
      if (row.source === 'installment' && row.installment_uuid) {
        await orderService.payInstallment(row.order_uuid, row.installment_uuid)
      } else {
        await orderService.payOrder(row.order_uuid)
      }
      const refreshedSummary = await reportDetailService.getReceivableSummary()
      setSummary(refreshedSummary)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (error) {
      setActionError(getApiErrorMessage(error, 'Não foi possível quitar este recebível agora.'))
    } finally {
      setProcessingRowKey(null)
    }
  }, [])

  const loadInteractions = useCallback(async (row: ReceivableReportItem) => {
    setIsLoadingInteractions(true)
    setInteractionError(null)
    try {
      const items = await reportDetailService.listReceivableInteractions(row.order_uuid, row.installment_uuid ?? undefined)
      setInteractions(items)
    } catch (error) {
      setInteractionError(getApiErrorMessage(error, 'Não foi possível carregar o histórico deste recebível agora.'))
    } finally {
      setIsLoadingInteractions(false)
    }
  }, [])

  const openInteractionDialog = useCallback((row: ReceivableReportItem) => {
    setSelectedReceivable(row)
    setInteractionType('note')
    setInteractionNotes('')
    setPromiseAmount('')
    setPromiseDueDate('')
    setInteractionFieldErrors({})
    void loadInteractions(row)
  }, [loadInteractions])

  function closeInteractionDialog() {
    if (isSavingInteraction) return
    setSelectedReceivable(null)
    setInteractions([])
    setInteractionError(null)
    setInteractionFieldErrors({})
  }

  async function saveInteraction() {
    if (!selectedReceivable) return
    setIsSavingInteraction(true)
    setInteractionError(null)
    setInteractionFieldErrors({})
    try {
      await reportDetailService.createReceivableInteraction(selectedReceivable.order_uuid, {
        installment_uuid: selectedReceivable.installment_uuid ?? undefined,
        interaction_type: interactionType,
        channel: 'manual',
        notes: interactionNotes.trim() || undefined,
        promised_amount: interactionType === 'promise' && promiseAmount.trim() ? Number(promiseAmount) : undefined,
        promised_due_date: interactionType === 'promise' ? promiseDueDate || undefined : undefined,
      })
      await loadInteractions(selectedReceivable)
      setInteractionType('note')
      setInteractionNotes('')
      setPromiseAmount('')
      setPromiseDueDate('')
    } catch (error) {
      if (error instanceof ApiRequestError) {
        setInteractionFieldErrors(error.errors)
      }
      setInteractionError(getApiErrorMessage(error, 'Não foi possível registrar esta interação agora.'))
    } finally {
      setIsSavingInteraction(false)
    }
  }

  const columns = useMemo<ServerGridColumn<ReceivableReportItem>[]>(
    () => [
      { field: 'client_name', headerName: 'Cliente', filterType: 'text' },
      { field: 'source_label', headerName: 'Origem', width: 120, filterType: 'none', sortable: false, cellRenderer: (row) => row.source_label },
      {
        field: 'amount',
        headerName: 'Valor',
        width: 130,
        filterType: 'number',
        cellRenderer: (row) => formatCurrency(row.amount),
        exportValue: (row) => formatCurrency(row.amount),
      },
      {
        field: 'is_overdue',
        headerName: 'Status',
        width: 120,
        filterType: 'boolean',
        cellRenderer: (row) => <ActiveChip isActive={row.is_overdue} activeLabel="Vencido" inactiveLabel="A vencer" />,
        exportValue: (row) => (row.is_overdue ? 'Vencido' : 'A vencer'),
      },
      {
        field: 'due_date',
        headerName: 'Vencimento',
        width: 130,
        filterType: 'none',
        cellRenderer: (row) => formatDateBR(row.due_date),
        exportValue: (row) => formatDateBR(row.due_date),
      },
      {
        field: 'days_overdue',
        headerName: 'Dias em atraso',
        width: 130,
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => (row.is_overdue ? row.days_overdue : '0'),
        exportValue: (row) => (row.is_overdue ? row.days_overdue : '0'),
      },
      {
        field: 'row_key',
        headerName: 'Ações',
        width: 220,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
            <Button
              size="small"
              variant="outlined"
              startIcon={<PaidOutlinedIcon fontSize="small" />}
              onClick={() => void payReceivable(row)}
              disabled={processingRowKey === row.row_key}
              sx={{ minHeight: 36 }}
            >
              {processingRowKey === row.row_key ? 'Quitando...' : 'Quitar'}
            </Button>
            <Button
              size="small"
              variant="text"
              color="success"
              startIcon={<WhatsAppIcon fontSize="small" />}
              disabled={!isDigitsOnlyPhone(row.client_phone_primary)}
              onClick={() => {
                if (!isDigitsOnlyPhone(row.client_phone_primary)) return
                const message = buildReceivableWhatsAppMessage({
                  clientName: row.client_name,
                  sourceLabel: row.source_label,
                  amount: row.amount,
                  dueDate: row.due_date,
                  daysOverdue: row.days_overdue,
                })
                void reportDetailService.createReceivableInteraction(row.order_uuid, {
                  installment_uuid: row.installment_uuid ?? undefined,
                  interaction_type: 'whatsapp',
                  channel: 'whatsapp',
                  notes: 'Cobrança enviada via WhatsApp.',
                }).catch(() => undefined)
                const url = buildWhatsAppUrl(row.client_phone_primary, message)
                window.open(url, '_blank')
              }}
              sx={{ minHeight: 36 }}
            >
              Cobrar
            </Button>
            <Button
              size="small"
              variant="text"
              startIcon={<HistoryOutlinedIcon fontSize="small" />}
              onClick={() => openInteractionDialog(row)}
              sx={{ minHeight: 36 }}
            >
              Histórico
            </Button>
          </Box>
        ),
      },
    ],
    [openInteractionDialog, payReceivable, processingRowKey],
  )

  return (
    <CrudListPage
      title="Relatório de recebíveis"
      subtitle="Acompanhe valores em aberto, quite títulos e inicie cobranças por WhatsApp."
      breadcrumbs={[{ label: 'Relatórios', to: '/relatorios/recebiveis' }, { label: 'Recebíveis' }]}
      toolbar={
        <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
          {[
            { key: 'all', label: 'Todos' },
            { key: 'current', label: 'A vencer' },
            { key: 'overdue_1_30', label: '1-30 dias' },
            { key: 'overdue_31_60', label: '31-60 dias' },
            { key: 'overdue_61_90', label: '61-90 dias' },
            { key: 'overdue_90_plus', label: '90+ dias' },
          ].map((item) => (
            <Button
              key={item.key}
              variant={agingBucket === item.key ? 'contained' : 'outlined'}
              color={item.key === 'all' || item.key === 'current' ? 'primary' : 'warning'}
              onClick={() => {
                setAgingBucket(item.key as typeof agingBucket)
                gridApiRef.current?.refreshInfiniteCache()
              }}
              sx={{ minHeight: 40 }}
            >
              {item.label}
            </Button>
          ))}
        </Box>
      }
      error={actionError}
      onRetry={() => gridApiRef.current?.refreshInfiniteCache()}
      isLoading={!activeTenantUuid}
      isEmpty={false}
    >
      <Box
        sx={{
          display: 'grid',
          // auto-fit: KPI com valor grande (R$) tem min-content ~220px —
          // colunas por breakpoint estouram quando o drawer reduz a área útil.
          gridTemplateColumns: 'repeat(auto-fit, minmax(min(100%, 260px), 1fr))',
          gap: 1.5,
          mb: 2,
        }}
      >
        <MetricCard
          icon={ReceiptLongOutlinedIcon}
          label="Em aberto"
          value={summary ? formatCurrency(summary.open_amount) : null}
          caption={summary ? `${summary.open_count} título${summary.open_count === 1 ? '' : 's'}` : null}
          tone="primary"
          isLoading={isLoadingSummary}
          index={0}
        />
        <MetricCard
          icon={PaidOutlinedIcon}
          label="Vencido"
          value={summary ? formatCurrency(summary.overdue_amount) : null}
          caption={summary ? `${summary.overdue_count} título${summary.overdue_count === 1 ? '' : 's'} em atraso` : null}
          tone="warning"
          isLoading={isLoadingSummary}
          index={1}
        />
        <Paper
          variant="outlined"
          sx={{ p: 2, ...ELEVATED_SURFACE_SX }}
        >
          {isLoadingSummary ? (
            <Skeleton variant="rounded" height={80} sx={{ borderRadius: 'var(--mk-radius-md)' }} />
          ) : (
            <Stack spacing={0.75}>
              <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--mk-muted)' }}>Faixa mais crítica</Typography>
              <Typography sx={{ fontSize: 18, fontWeight: 700, color: 'var(--mk-text)' }}>
                {summary?.aging_buckets.find((bucket) => bucket.bucket === 'overdue_90_plus')?.count
                  ? '90+ dias'
                  : summary?.aging_buckets.find((bucket) => bucket.bucket === 'overdue_61_90')?.count
                    ? '61-90 dias'
                    : summary?.aging_buckets.find((bucket) => bucket.bucket === 'overdue_31_60')?.count
                      ? '31-60 dias'
                      : summary?.aging_buckets.find((bucket) => bucket.bucket === 'overdue_1_30')?.count
                        ? '1-30 dias'
                        : 'Sem atraso'}
              </Typography>
              <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                Priorização rápida para cobrança operacional.
              </Typography>
            </Stack>
          )}
        </Paper>
        <Paper
          variant="outlined"
          sx={{ p: 2, ...ELEVATED_SURFACE_SX }}
        >
          {isLoadingSummary ? (
            <Skeleton variant="rounded" height={80} sx={{ borderRadius: 'var(--mk-radius-md)' }} />
          ) : (
            <Stack spacing={0.75}>
              <Typography sx={{ fontSize: 13, fontWeight: 600, color: 'var(--mk-muted)' }}>Distribuição atual</Typography>
              {(summary?.aging_buckets ?? []).slice(0, 3).map((bucket) => (
                <Typography key={bucket.bucket} sx={{ fontSize: 13, color: 'var(--mk-text)' }}>
                  {bucket.label}: {bucket.count}
                </Typography>
              ))}
            </Stack>
          )}
        </Paper>
      </Box>

      <Box sx={{ overflowX: 'auto' }}>
        <Box sx={{ minWidth: 920 }}>
          <ServerDataGrid
            columns={columns}
            fetchPage={fetchPage}
            rowIdField="row_key"
            exportFileName="relatorio-recebiveis"
            onGridReady={(api) => {
              gridApiRef.current = api
            }}
            emptyState={{
              icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
              title: 'Nenhum recebível em aberto',
              description: 'Quando houver pedidos ou parcelas pendentes, eles aparecerão aqui.',
            }}
          />
        </Box>
      </Box>

      <Dialog open={Boolean(selectedReceivable)} onClose={closeInteractionDialog} maxWidth="md" fullWidth>
        <DialogTitle>Histórico de cobrança</DialogTitle>
        <DialogContent dividers>
          {selectedReceivable && (
            <Stack spacing={2}>
              <Box>
                <Typography sx={{ fontWeight: 700, color: 'var(--mk-text)' }}>{selectedReceivable.client_name}</Typography>
                <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
                  {selectedReceivable.source_label} • {formatCurrency(selectedReceivable.amount)} • vence {formatDateBR(selectedReceivable.due_date)}
                </Typography>
              </Box>

              {interactionError && <Alert severity="error">{interactionError}</Alert>}

              <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' }, gap: 1.5 }}>
                <Button
                  variant={interactionType === 'note' ? 'contained' : 'outlined'}
                  onClick={() => setInteractionType('note')}
                  sx={{ minHeight: 42 }}
                >
                  Registrar anotação
                </Button>
                <Button
                  variant={interactionType === 'promise' ? 'contained' : 'outlined'}
                  color="warning"
                  onClick={() => setInteractionType('promise')}
                  sx={{ minHeight: 42 }}
                >
                  Registrar promessa
                </Button>
              </Box>

              <TextField
                label={interactionType === 'promise' ? 'Observação da promessa' : 'Anotação'}
                value={interactionNotes}
                onChange={(event) => setInteractionNotes(event.target.value)}
                multiline
                minRows={3}
                error={Boolean(interactionFieldErrors.notes)}
                helperText={interactionFieldErrors.notes?.[0]}
              />

              {interactionType === 'promise' && (
                <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' }, gap: 1.5 }}>
                  <TextField
                    label="Valor prometido"
                    value={promiseAmount}
                    onChange={(event) => setPromiseAmount(event.target.value)}
                    error={Boolean(interactionFieldErrors.promised_amount)}
                    helperText={interactionFieldErrors.promised_amount?.[0]}
                  />
                  <TextField
                    label="Data prometida"
                    type="date"
                    value={promiseDueDate}
                    onChange={(event) => setPromiseDueDate(event.target.value)}
                    slotProps={{ inputLabel: { shrink: true } }}
                    error={Boolean(interactionFieldErrors.promised_due_date)}
                    helperText={interactionFieldErrors.promised_due_date?.[0]}
                  />
                </Box>
              )}

              <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                <Button onClick={() => void saveInteraction()} variant="contained" disabled={isSavingInteraction}>
                  {isSavingInteraction ? 'Salvando...' : 'Salvar interação'}
                </Button>
              </Box>

              <Box sx={{ borderTop: '1px solid var(--mk-border)', pt: 2 }}>
                <Typography sx={{ fontWeight: 600, color: 'var(--mk-text)', mb: 1.5 }}>Histórico</Typography>

                {isLoadingInteractions ? (
                  <Stack spacing={1}>
                    {Array.from({ length: 3 }).map((_, index) => (
                      <Skeleton key={index} variant="rounded" height={56} sx={{ borderRadius: 'var(--mk-radius-md)' }} />
                    ))}
                  </Stack>
                ) : interactions.length === 0 ? (
                  <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>
                    Nenhuma interação registrada ainda para este recebível.
                  </Typography>
                ) : (
                  <Stack spacing={1}>
                    {interactions.map((item) => (
                      <Paper key={item.uuid} variant="outlined" sx={{ p: 1.5, ...SOFT_PANEL_SX }}>
                        <Typography sx={{ fontWeight: 600, fontSize: 14, color: 'var(--mk-text)' }}>
                          {item.interaction_type_label}
                          {item.created_by_name ? ` • ${item.created_by_name}` : ''}
                        </Typography>
                        <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)' }}>
                          {new Date(item.contacted_at).toLocaleString('pt-BR')}
                        </Typography>
                        {item.notes ? (
                          <Typography sx={{ fontSize: 13, color: 'var(--mk-text)', mt: 0.75 }}>{item.notes}</Typography>
                        ) : null}
                        {item.interaction_type === 'promise' ? (
                          <Typography sx={{ fontSize: 12.5, color: 'var(--mk-muted)', mt: 0.5 }}>
                            Promessa: {item.promised_amount ? formatCurrency(item.promised_amount) : 'sem valor'} até {item.promised_due_date ? formatDateBR(toDateOnly(item.promised_due_date)) : 'sem data'}
                          </Typography>
                        ) : null}
                      </Paper>
                    ))}
                  </Stack>
                )}
              </Box>
            </Stack>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={closeInteractionDialog} color="inherit" disabled={isSavingInteraction}>
            Fechar
          </Button>
        </DialogActions>
      </Dialog>
    </CrudListPage>
  )
}
