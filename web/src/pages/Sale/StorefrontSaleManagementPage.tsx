import CancelOutlinedIcon from '@mui/icons-material/CancelOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined'
import { Chip, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { StorefrontSaleActionDialog } from '../../components/sale/StorefrontSaleActionDialog'
import { WorkflowTimelineDialog } from '../../components/workflow/WorkflowTimelineDialog'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as storefrontSaleService from '../../services/storefrontSaleService'
import * as workflowService from '../../services/workflowService'
import { UI_RADIUS } from '../../styles/layoutStandards'
import type { Sale, SaleOperationStage } from '../../types/sale'

const STAGE_META: Record<SaleOperationStage, { label: string }> = {
  approval: { label: 'Aguardando aprovação' },
  confirmed: { label: 'Confirmado' },
  financial_pending: { label: 'Financeiro pendente' },
}

const POLL_PER_PAGE = 20
const REFRESH_INTERVAL_MS = 30000

const STAGE_FILTER_OPTIONS: Array<{ value: 'all' | SaleOperationStage; label: string }> = [
  { value: 'all', label: 'Todas' },
  { value: 'approval', label: 'Aguardando aprovação' },
  { value: 'confirmed', label: 'Confirmado' },
  { value: 'financial_pending', label: 'Financeiro pendente' },
]

/**
 * Gestão de pedidos vindos do canal público (`/vendas-online`, permissão própria
 * storefront-sales,*). Lista simples (código/cliente/status/ação); toda a
 * gestão fica no modal por venda (`StorefrontSaleActionDialog`), que mostra
 * sempre 2 botões de ação escolhidos pelo status atual — não existe etapa
 * operacional intermediária de "preparo"/"despacho" pra ingresso.
 *
 * Polling de 30s toca `notificacao.mp3` quando um pedido inédito aparece com
 * a aba visível e recarrega o grid (sem exibir board — só sinaliza pedido novo).
 */
export function StorefrontSaleManagementPage() {
  const { activeTenantUuid } = useAuth()
  const { can } = useAccessControl()
  const gridApiRef = useRef<GridApi | null>(null)
  const canManageCancellation = can(ACCESS.salesUpdate)

  const [selectedSaleUuid, setSelectedSaleUuid] = useState<string | null>(null)
  const [selectedTimelineSaleUuid, setSelectedTimelineSaleUuid] = useState<string | null>(null)
  const [stageFilter, setStageFilter] = useState<'all' | SaleOperationStage>('all')
  const [onlyCancellationRequested, setOnlyCancellationRequested] = useState(false)

  // UUIDs já vistos — populado sem tocar som no primeiro polling; só um UUID
  // inédito num polling posterior dispara o som de pedido novo.
  const seenOrderUuidsRef = useRef<Set<string>>(new Set())
  const seededRef = useRef(false)

  const fetchPage = useCallback(
    async ({ page, perPage }: ServerGridFetchParams): Promise<ServerGridFetchResult<Sale>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await storefrontSaleService.listStorefrontSales({
        active_only: true,
        ...(stageFilter !== 'all' ? { stage: stageFilter } : {}),
        ...(onlyCancellationRequested ? { status: 'cancellation_requested' } : {}),
        page,
        per_page: perPage,
      })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, stageFilter, onlyCancellationRequested],
  )

  useEffect(() => {
    if (!activeTenantUuid) return
    seenOrderUuidsRef.current = new Set()
    seededRef.current = false

    const poll = async () => {
      try {
        const result = await storefrontSaleService.listStorefrontSales({ active_only: true, per_page: POLL_PER_PAGE })
        const incomingUuids = result.items.map((item) => item.uuid)
        if (seededRef.current) {
          const hasNew = incomingUuids.some((uuid) => !seenOrderUuidsRef.current.has(uuid))
          if (hasNew) {
            if (document.visibilityState === 'visible') {
              new Audio('/notificacao.mp3').play().catch(() => undefined)
            }
            gridApiRef.current?.refreshInfiniteCache()
          }
        }
        seenOrderUuidsRef.current = new Set(incomingUuids)
        seededRef.current = true
      } catch {
        // Polling silencioso — o grid já trata seu próprio erro de carga.
      }
    }

    void poll()
    const interval = setInterval(() => void poll(), REFRESH_INTERVAL_MS)
    return () => clearInterval(interval)
  }, [activeTenantUuid])

  const columns = useMemo<ServerGridColumn<Sale>[]>(
    () => [
      {
        field: 'codigo',
        headerName: 'Código',
        width: 120,
        filterType: 'text',
        cellRenderer: (row) => row.codigo,
        exportValue: (row) => row.codigo,
      },
      {
        field: 'client_name',
        headerName: 'Cliente',
        filterType: 'text',
        cellRenderer: (row) => `${row.final_customer?.name ?? ''} ${row.final_customer?.last_name ?? ''}`.trim(),
        exportValue: (row) => `${row.final_customer?.name ?? ''} ${row.final_customer?.last_name ?? ''}`.trim(),
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 210,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) =>
          row.status === 'cancellation_requested' ? (
            <Chip
              size="small"
              icon={<CancelOutlinedIcon fontSize="small" />}
              label="Cancelamento solicitado"
              color="warning"
              sx={{ fontWeight: 600 }}
            />
          ) : (
            <Chip size="small" label={STAGE_META[deriveStage(row) ?? 'confirmed'].label} variant="outlined" />
          ),
        exportValue: (row) => (row.status === 'cancellation_requested' ? 'Cancelamento solicitado' : STAGE_META[deriveStage(row) ?? 'confirmed'].label),
      },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 140,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
            <Tooltip title="Gerenciar venda" arrow>
              <IconButton
                size="small"
                aria-label={`Gerenciar venda do cliente ${`${row.final_customer?.name ?? ''} ${row.final_customer?.last_name ?? ''}`.trim()}`}
                onClick={() => setSelectedSaleUuid(row.uuid)}
                sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
              >
                <VisibilityOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title="Histórico" arrow>
              <IconButton
                size="small"
                aria-label={`Ver histórico da venda ${row.codigo}`}
                onClick={() => setSelectedTimelineSaleUuid(row.uuid)}
                sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
              >
                <HistoryOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </Stack>
        ),
      },
    ],
    [],
  )

  return (
    <>
      <CrudListPage
        title="Vendas online"
        subtitle="Acompanhe e gerencie as vendas recebidas pelo canal público."
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
        toolbar={
          <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 1, alignItems: 'center' }}>
            {STAGE_FILTER_OPTIONS.map((option) => (
              <Chip
                key={option.value}
                clickable
                label={option.label}
                color={stageFilter === option.value ? 'primary' : 'default'}
                variant={stageFilter === option.value ? 'filled' : 'outlined'}
                onClick={() => setStageFilter(option.value)}
                sx={{ fontWeight: 600, height: 32, borderRadius: UI_RADIUS.pill }}
              />
            ))}
            {canManageCancellation ? (
              <Chip
                clickable
                icon={<CancelOutlinedIcon fontSize="small" />}
                label="Só cancelamento pendente"
                color={onlyCancellationRequested ? 'warning' : 'default'}
                variant={onlyCancellationRequested ? 'filled' : 'outlined'}
                onClick={() => setOnlyCancellationRequested((current) => !current)}
                sx={{ fontWeight: 600, height: 32, borderRadius: UI_RADIUS.pill }}
              />
            ) : null}
          </Stack>
        }
      >
        <ServerDataGrid
          columns={columns}
          fetchPage={fetchPage}
          rowIdField="uuid"
          exportFileName="vendas-online"
          onGridReady={(api) => {
            gridApiRef.current = api
          }}
          emptyState={
            onlyCancellationRequested
              ? {
                  icon: <CancelOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                  title: 'Nenhum cancelamento pendente no momento',
                  description: 'Solicitações de cancelamento feitas pelos clientes aparecerão aqui.',
                }
              : {
                  icon: <ReceiptLongOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                  title: 'Nenhuma venda pendente de ação no momento',
                  description: 'Vendas concluídas, recusadas ou canceladas não aparecem aqui.',
                }
          }
        />
      </CrudListPage>

      <StorefrontSaleActionDialog
        saleUuid={selectedSaleUuid}
        canManageCancellation={canManageCancellation}
        open={selectedSaleUuid !== null}
        onClose={() => setSelectedSaleUuid(null)}
        onChanged={() => gridApiRef.current?.refreshInfiniteCache()}
      />

      <WorkflowTimelineDialog
        open={selectedTimelineSaleUuid !== null}
        title="Histórico da venda"
        subjectLabel={selectedTimelineSaleUuid ? `venda ${selectedTimelineSaleUuid}` : 'venda'}
        loader={() =>
          selectedTimelineSaleUuid
            ? workflowService.getStorefrontSaleWorkflowTimeline(selectedTimelineSaleUuid)
            : Promise.resolve([])
        }
        stageLabel={(stage) => {
          if (!stage) return 'Sem etapa'
          return STAGE_META[stage as SaleOperationStage]?.label ?? stage
        }}
        onClose={() => setSelectedTimelineSaleUuid(null)}
      />
    </>
  )
}

function deriveStage(order: Sale): SaleOperationStage | null {
  if (order.cancelled_at || order.status === 'rejected') return null
  if (order.status === 'pending_approval') return 'approval'
  if (order.is_completed && !order.is_paid) return 'financial_pending'
  if (order.status === 'confirmed' && !order.is_completed) return 'confirmed'
  return null
}
