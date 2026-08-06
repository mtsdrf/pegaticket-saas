import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import LayersOutlinedIcon from '@mui/icons-material/LayersOutlined'
import { Button, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import { StatusChip, type StatusChipTone } from '../../components/crud/StatusChip'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as ticketBatchService from '../../services/ticketBatchService'
import * as ticketTypeService from '../../services/ticketTypeService'
import { getApiErrorMessage } from '../../types/api'
import { TICKET_BATCH_STATUS_OPTIONS, type TicketBatch } from '../../types/ticketBatch'
import { formatCurrency, formatDateFromDateTimeBR } from '../../utils/format'

const STATUS_LABELS = Object.fromEntries(TICKET_BATCH_STATUS_OPTIONS.map((option) => [option.value, option.label]))
const STATUS_TONES: Record<TicketBatch['status'], StatusChipTone> = {
  rascunho: 'neutral',
  ativo: 'success',
  pausado: 'info',
  esgotado: 'warning',
  encerrado: 'neutral',
}

export function TicketBatchListPage() {
  const navigate = useNavigate()
  const { ticketTypeUuid = '' } = useParams<{ ticketTypeUuid: string }>()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const [ticketTypeName, setTicketTypeName] = useState('Tipo de ingresso')
  const [headerError, setHeaderError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<TicketBatch | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  useEffect(() => {
    ticketTypeService
      .getTicketType(ticketTypeUuid)
      .then((ticketType) => setTicketTypeName(ticketType.name))
      .catch((error) => setHeaderError(getApiErrorMessage(error, 'Não foi possível carregar o tipo de ingresso agora.')))
  }, [ticketTypeUuid])

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<TicketBatch>> => {
      if (!activeTenantUuid || !ticketTypeUuid) return { rows: [], total: 0 }

      const result = await ticketBatchService.listTicketBatches(ticketTypeUuid, {
        ...filters,
        page,
        per_page: perPage,
        sort_by: sortBy as 'name' | 'priority' | 'status' | undefined,
        sort_dir: sortDir,
      })

      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, ticketTypeUuid],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await ticketBatchService.deleteTicketBatch(ticketTypeUuid, deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o lote agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const columns = useMemo<ServerGridColumn<TicketBatch>[]>(
    () => [
      { field: 'name', headerName: 'Lote', filterType: 'text' },
      {
        field: 'price',
        headerName: 'Preço',
        width: 130,
        filterType: 'number',
        cellRenderer: (row) => formatCurrency(row.price),
        exportValue: (row) => formatCurrency(row.price),
      },
      {
        field: 'quantity_available',
        headerName: 'Saldo',
        width: 120,
        filterType: 'text',
        cellRenderer: (row) => `${row.quantity_available}/${row.quantity}`,
        exportValue: (row) => `${row.quantity_available}/${row.quantity}`,
      },
      {
        field: 'starts_at',
        headerName: 'Início',
        width: 160,
        filterType: 'text',
        cellRenderer: (row) => (row.starts_at ? formatDateFromDateTimeBR(row.starts_at) : 'Livre'),
        exportValue: (row) => (row.starts_at ? formatDateFromDateTimeBR(row.starts_at) : 'Livre'),
      },
      {
        field: 'priority',
        headerName: 'Prioridade',
        width: 110,
        filterType: 'number',
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 140,
        filterType: 'text',
        cellRenderer: (row) => <StatusChip status={row.status} label={STATUS_LABELS[row.status] ?? row.status} tone={STATUS_TONES[row.status]} />,
        exportValue: (row) => STATUS_LABELS[row.status] ?? row.status,
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
            {can(ACCESS.ticketBatchesUpdate) ? (
              <Tooltip title="Editar lote" arrow>
                <IconButton
                  size="small"
                  aria-label={`Editar lote ${row.name}`}
                  onClick={() => navigate(`/tipos-de-ingresso/${ticketTypeUuid}/lotes/${row.uuid}/editar`)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.ticketBatchesDelete) ? (
              <Tooltip title="Excluir lote" arrow>
                <IconButton
                  size="small"
                  aria-label={`Excluir lote ${row.name}`}
                  onClick={() => {
                    setDeleteError(null)
                    setDeleteTarget(row)
                  }}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-danger)' } }}
                >
                  <DeleteOutlineIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
          </Stack>
        ),
      },
    ],
    [can, navigate, ticketTypeUuid],
  )

  return (
    <>
      <CrudListPage
        title={`Lotes de ${ticketTypeName}`}
        subtitle="Gerencie preços, janelas e ordem de avanço do tipo de ingresso."
        createLabel="Novo lote"
        canCreate={can(ACCESS.ticketBatchesCreate)}
        onCreate={() => navigate(`/tipos-de-ingresso/${ticketTypeUuid}/lotes/novo`)}
        error={headerError}
        onRetry={() => navigate(0)}
        isLoading={!activeTenantUuid}
        isEmpty={false}
        breadcrumbs={[{ label: 'Tipos de ingresso', to: '/tipos-de-ingresso' }, { label: ticketTypeName }]}
      >
        <ServerDataGrid
          columns={columns}
          fetchPage={fetchPage}
          rowIdField="uuid"
          exportFileName="lotes"
          onGridReady={(api) => {
            gridApiRef.current = api
          }}
          emptyState={{
            icon: <LayersOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
            title: 'Nenhum lote cadastrado ainda',
            description: 'Comece configurando o primeiro lote deste tipo de ingresso.',
            action: can(ACCESS.ticketBatchesCreate) ? (
              <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate(`/tipos-de-ingresso/${ticketTypeUuid}/lotes/novo`)}>
                Cadastrar primeiro lote
              </Button>
            ) : undefined,
          }}
        />
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir lote"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
