import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import LocalMallOutlinedIcon from '@mui/icons-material/LocalMallOutlined'
import { Button, Chip, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import { StatusChip, type StatusChipTone } from '../../components/crud/StatusChip'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as eventProductService from '../../services/eventProductService'
import { EVENT_PRODUCT_KIND_OPTIONS, EVENT_PRODUCT_STATUS_OPTIONS, type EventProduct } from '../../types/eventProduct'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency } from '../../utils/format'

const KIND_LABELS = Object.fromEntries(EVENT_PRODUCT_KIND_OPTIONS.map((option) => [option.value, option.label]))
const STATUS_LABELS = Object.fromEntries(EVENT_PRODUCT_STATUS_OPTIONS.map((option) => [option.value, option.label]))
const STATUS_TONES: Record<EventProduct['status'], StatusChipTone> = {
  rascunho: 'neutral',
  ativo: 'success',
  pausado: 'info',
  esgotado: 'warning',
  encerrado: 'neutral',
}

export function EventProductListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)

  const [deleteTarget, setDeleteTarget] = useState<EventProduct | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback(
    (eventProduct: EventProduct) => navigate(`/adicionais/${eventProduct.uuid}/editar`),
    [navigate],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await eventProductService.deleteEventProduct(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o adicional agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const fetchPage = useCallback(
    async ({
      page,
      perPage,
      sortBy,
      sortDir,
      filters,
    }: ServerGridFetchParams): Promise<ServerGridFetchResult<EventProduct>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await eventProductService.listEventProducts({
        ...filters,
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      })

      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid],
  )

  const columns = useMemo<ServerGridColumn<EventProduct>[]>(
    () => [
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      {
        field: 'event_name',
        headerName: 'Evento',
        filterType: 'text',
        cellRenderer: (row) => row.event?.name ?? '',
        exportValue: (row) => row.event?.name ?? '',
      },
      {
        field: 'kind',
        headerName: 'Tipo',
        width: 140,
        filterType: 'text',
        cellRenderer: (row) => <Chip size="small" label={KIND_LABELS[row.kind] ?? row.kind} />,
        exportValue: (row) => KIND_LABELS[row.kind] ?? row.kind,
      },
      {
        field: 'price',
        headerName: 'Preço',
        width: 140,
        filterType: 'number',
        cellRenderer: (row) => formatCurrency(row.price),
        exportValue: (row) => formatCurrency(row.price),
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 130,
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
            {can(ACCESS.eventProductsUpdate) ? (
              <Tooltip title="Editar adicional" arrow>
                <IconButton
                  size="small"
                  aria-label={`Editar ${row.name}`}
                  onClick={() => handleEdit(row)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.eventProductsDelete) ? (
              <Tooltip title="Excluir adicional" arrow>
                <IconButton
                  size="small"
                  aria-label={`Excluir ${row.name}`}
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
    [handleEdit, can],
  )

  return (
    <>
      <CrudListPage
        title="Adicionais"
        subtitle="Gerencie os adicionais e estacionamentos vendidos junto aos eventos."
        createLabel="Novo adicional"
        canCreate={can(ACCESS.eventProductsCreate)}
        onCreate={() => navigate('/adicionais/novo')}
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <ServerDataGrid
          columns={columns}
          fetchPage={fetchPage}
          rowIdField="uuid"
          exportFileName="adicionais"
          onGridReady={(api) => {
            gridApiRef.current = api
          }}
          emptyState={{
            icon: <LocalMallOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
            title: 'Nenhum adicional cadastrado ainda',
            description: 'Comece cadastrando o primeiro adicional de um evento.',
            action: can(ACCESS.eventProductsCreate) ? (
              <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/adicionais/novo')}>
                Cadastrar primeiro adicional
              </Button>
            ) : undefined,
          }}
        />
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir adicional"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
