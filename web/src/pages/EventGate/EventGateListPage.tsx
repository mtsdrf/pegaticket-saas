import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import MeetingRoomOutlinedIcon from '@mui/icons-material/MeetingRoomOutlined'
import { Button, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as eventService from '../../services/eventService'
import * as eventGateService from '../../services/eventGateService'
import type { EventGate } from '../../types/eventGate'
import { getApiErrorMessage } from '../../types/api'

export function EventGateListPage() {
  const navigate = useNavigate()
  const { eventUuid = '' } = useParams<{ eventUuid: string }>()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const [eventName, setEventName] = useState('Evento')
  const [headerError, setHeaderError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<EventGate | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  useEffect(() => {
    eventService
      .getEvent(eventUuid)
      .then((event) => setEventName(event.name))
      .catch((error) => setHeaderError(getApiErrorMessage(error, 'Não foi possível carregar o evento agora.')))
  }, [eventUuid])

  const fetchPage = useCallback(
    async ({ page, perPage, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<EventGate>> => {
      if (!activeTenantUuid || !eventUuid) return { rows: [], total: 0 }

      const result = await eventGateService.listEventGates(eventUuid, {
        ...filters,
        page,
        per_page: perPage,
      })

      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, eventUuid],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await eventGateService.deleteEventGate(eventUuid, deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir a portaria agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const columns = useMemo<ServerGridColumn<EventGate>[]>(
    () => [
      { field: 'name', headerName: 'Portaria', filterType: 'text' },
      {
        field: 'allowed_ticket_types',
        headerName: 'Tipos permitidos',
        minWidth: 260,
        filterType: 'text',
        cellRenderer: (row) =>
          row.allowed_ticket_types.length === 0
            ? 'Aceita qualquer tipo'
            : row.allowed_ticket_types.map((ticketType) => ticketType.name).join(', '),
        exportValue: (row) =>
          row.allowed_ticket_types.length === 0
            ? 'Aceita qualquer tipo'
            : row.allowed_ticket_types.map((ticketType) => ticketType.name).join(', '),
      },
      {
        field: 'is_active',
        headerName: 'Ativo',
        width: 120,
        filterType: 'boolean',
        cellRenderer: (row) => <ActiveChip isActive={row.is_active} />,
        exportValue: (row) => (row.is_active ? 'Ativo' : 'Inativo'),
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
            {can(ACCESS.eventGatesUpdate) ? (
              <Tooltip title="Editar portaria" arrow>
                <IconButton
                  size="small"
                  aria-label={`Editar portaria ${row.name}`}
                  onClick={() => navigate(`/eventos/${eventUuid}/portarias/${row.uuid}/editar`)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.eventGatesDelete) ? (
              <Tooltip title="Excluir portaria" arrow>
                <IconButton
                  size="small"
                  aria-label={`Excluir portaria ${row.name}`}
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
    [can, eventUuid, navigate],
  )

  return (
    <>
      <CrudListPage
        title={`Portarias de ${eventName}`}
        subtitle="Gerencie os pontos de acesso do evento e os tipos de ingresso aceitos por cada um."
        createLabel="Nova portaria"
        canCreate={can(ACCESS.eventGatesCreate)}
        onCreate={() => navigate(`/eventos/${eventUuid}/portarias/nova`)}
        error={headerError}
        onRetry={() => navigate(0)}
        isLoading={!activeTenantUuid}
        isEmpty={false}
        breadcrumbs={[{ label: 'Eventos', to: '/eventos' }, { label: eventName }]}
      >
        <ServerDataGrid
          columns={columns}
          fetchPage={fetchPage}
          rowIdField="uuid"
          exportFileName="portarias-evento"
          onGridReady={(api) => {
            gridApiRef.current = api
          }}
          emptyState={{
            icon: <MeetingRoomOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
            title: 'Nenhuma portaria cadastrada ainda',
            description: 'Comece adicionando o primeiro ponto de acesso deste evento.',
            action: can(ACCESS.eventGatesCreate) ? (
              <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate(`/eventos/${eventUuid}/portarias/nova`)}>
                Cadastrar primeira portaria
              </Button>
            ) : undefined,
          }}
        />
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir portaria"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
