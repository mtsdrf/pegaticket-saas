import AddIcon from '@mui/icons-material/Add'
import ConfirmationNumberOutlinedIcon from '@mui/icons-material/ConfirmationNumberOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import { Avatar, Box, Button, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { TicketTypeStatusToggle } from '../../components/ticketType/TicketTypeStatusToggle'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as ticketTypeService from '../../services/ticketTypeService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import type { TicketType } from '../../types/ticketType'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency } from '../../utils/format'

export function TicketTypeListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)

  const [deleteTarget, setDeleteTarget] = useState<TicketType | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback(
    (ticketType: TicketType) => navigate(`/tipos-de-ingresso/${ticketType.uuid}/editar`),
    [navigate],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await ticketTypeService.deleteTicketType(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o tipo de ingresso agora.'))
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
    }: ServerGridFetchParams): Promise<ServerGridFetchResult<TicketType>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await ticketTypeService.listTicketTypes({
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

  const columns = useMemo<ServerGridColumn<TicketType>[]>(
    () => [
      {
        field: 'image_url',
        headerName: '',
        width: 64,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Avatar
            variant="rounded"
            src={row.image_url ?? undefined}
            sx={{ width: 32, height: 32, ...SOFT_PANEL_SX }}
          >
            <ConfirmationNumberOutlinedIcon fontSize="small" sx={{ color: 'var(--pt-muted)' }} />
          </Avatar>
        ),
      },
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      {
        field: 'event_name',
        headerName: 'Evento',
        filterType: 'text',
        cellRenderer: (row) => row.event?.name ?? '',
        exportValue: (row) => row.event?.name ?? '',
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
        headerName: 'Ativo',
        width: 110,
        filterType: 'none',
        cellRenderer: (row) =>
          can(ACCESS.ticketTypesUpdate) ? (
            <TicketTypeStatusToggle ticketType={row} onToggled={() => gridApiRef.current?.refreshInfiniteCache()} />
          ) : (
            row.status
          ),
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
            {can(ACCESS.ticketTypesUpdate) ? (
              <Tooltip title="Editar tipo de ingresso" arrow>
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
            {can(ACCESS.ticketTypesDelete) ? (
              <Tooltip title="Excluir tipo de ingresso" arrow>
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
        title="Tipos de ingresso"
        subtitle="Gerencie os tipos de ingresso vendidos nos eventos."
        createLabel="Novo tipo de ingresso"
        canCreate={can(ACCESS.ticketTypesCreate)}
        onCreate={() => navigate('/tipos-de-ingresso/novo')}
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 720 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="tipos-de-ingresso"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <ConfirmationNumberOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                title: 'Nenhum tipo de ingresso cadastrado ainda',
                description: 'Comece cadastrando o primeiro tipo de ingresso de um evento.',
                action: can(ACCESS.ticketTypesCreate) ? (
                  <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/tipos-de-ingresso/novo')}>
                    Cadastrar primeiro tipo de ingresso
                  </Button>
                ) : undefined,
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir tipo de ingresso"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
