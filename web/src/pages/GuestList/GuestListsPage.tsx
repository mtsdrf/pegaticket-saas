import AddIcon from '@mui/icons-material/Add'
import CardGiftcardOutlinedIcon from '@mui/icons-material/CardGiftcardOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import GroupsOutlinedIcon from '@mui/icons-material/GroupsOutlined'
import { Button, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as guestListService from '../../services/guestListService'
import type { GuestList } from '../../types/guestList'
import { getApiErrorMessage } from '../../types/api'

export function GuestListsPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<GuestList | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const fetchPage = useCallback(
    async ({ page, perPage, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<GuestList>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await guestListService.listGuestLists({
        ...filters,
        page,
        per_page: perPage,
      })

      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await guestListService.deleteGuestList(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir a lista agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const columns = useMemo<ServerGridColumn<GuestList>[]>(
    () => [
      { field: 'name', headerName: 'Lista', filterType: 'text' },
      {
        field: 'event_name',
        headerName: 'Evento',
        filterType: 'text',
        cellRenderer: (row) => row.event.name,
        exportValue: (row) => row.event.name,
      },
      {
        field: 'ticket_type_name',
        headerName: 'Tipo de ingresso',
        filterType: 'text',
        cellRenderer: (row) => row.ticket_type.name,
        exportValue: (row) => row.ticket_type.name,
      },
      {
        field: 'quantity_per_entry',
        headerName: 'Ingressos por convidado',
        width: 170,
        filterType: 'number',
      },
      {
        field: 'redeemed_entries_count',
        headerName: 'Resgates',
        width: 140,
        filterType: 'number',
        cellRenderer: (row) => `${row.redeemed_entries_count ?? 0}/${row.entries_count ?? 0}`,
        exportValue: (row) => `${row.redeemed_entries_count ?? 0}/${row.entries_count ?? 0}`,
      },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 190,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
            <Tooltip title="Gerenciar convidados" arrow>
              <IconButton
                size="small"
                aria-label={`Gerenciar convidados da lista ${row.name}`}
                onClick={() => navigate(`/listas-de-convidados/${row.uuid}`)}
                sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
              >
                <GroupsOutlinedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            {can(ACCESS.eventsUpdate) ? (
              <Tooltip title="Editar lista" arrow>
                <IconButton
                  size="small"
                  aria-label={`Editar lista ${row.name}`}
                  onClick={() => navigate(`/listas-de-convidados/${row.uuid}/editar`)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.eventsUpdate) ? (
              <Tooltip title="Excluir lista" arrow>
                <IconButton
                  size="small"
                  aria-label={`Excluir lista ${row.name}`}
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
    [can, navigate],
  )

  return (
    <>
      <CrudListPage
        title="Listas de convidados"
        subtitle="Cortesias estruturadas com controle por evento, tipo de ingresso e resgates."
        createLabel="Nova lista"
        canCreate={can(ACCESS.eventsUpdate)}
        onCreate={() => navigate('/listas-de-convidados/nova')}
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <ServerDataGrid
          columns={columns}
          fetchPage={fetchPage}
          rowIdField="uuid"
          exportFileName="listas-de-convidados"
          onGridReady={(api) => {
            gridApiRef.current = api
          }}
          emptyState={{
            icon: <CardGiftcardOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
            title: 'Nenhuma lista de convidados',
            description: 'Crie uma lista para gerar links individuais de cortesia.',
            action: can(ACCESS.eventsUpdate) ? (
              <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/listas-de-convidados/nova')}>
                Cadastrar primeira lista
              </Button>
            ) : undefined,
          }}
        />
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir lista de convidados"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
