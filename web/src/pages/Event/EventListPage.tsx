import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import EventOutlinedIcon from '@mui/icons-material/EventOutlined'
import MeetingRoomOutlinedIcon from '@mui/icons-material/MeetingRoomOutlined'
import ScheduleOutlinedIcon from '@mui/icons-material/ScheduleOutlined'
import { Avatar, Button, IconButton, Stack, Tooltip } from '@mui/material'
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
import * as eventService from '../../services/eventService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { EVENT_STATUS_OPTIONS, type Event } from '../../types/event'
import { getApiErrorMessage } from '../../types/api'
import { resolveEventCoverImageUrl } from '../../utils/eventCover'
import { formatDateBR } from '../../utils/format'

const STATUS_LABELS = Object.fromEntries(EVENT_STATUS_OPTIONS.map((option) => [option.value, option.label]))
const STATUS_TONES: Record<Event['status'], StatusChipTone> = {
  rascunho: 'neutral',
  agendado: 'warning',
  publicado: 'success',
  vendas_pausadas: 'info',
  esgotado: 'warning',
  encerrado: 'neutral',
  cancelado: 'danger',
  arquivado: 'neutral',
}

export function EventListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)

  const [deleteTarget, setDeleteTarget] = useState<Event | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback(
    (event: Event) => navigate(`/eventos/${event.uuid}/editar`),
    [navigate],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await eventService.deleteEvent(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o evento agora.'))
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
    }: ServerGridFetchParams): Promise<ServerGridFetchResult<Event>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await eventService.listEvents({
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

  const columns = useMemo<ServerGridColumn<Event>[]>(
    () => [
      {
        field: 'cover_image_url',
        headerName: '',
        width: 64,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Avatar variant="rounded" src={resolveEventCoverImageUrl(row.cover_image_url)} sx={{ width: 32, height: 32, ...SOFT_PANEL_SX }}>
            <EventOutlinedIcon fontSize="small" sx={{ color: 'var(--pt-muted)' }} />
          </Avatar>
        ),
      },
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      {
        field: 'category_name',
        headerName: 'Categoria',
        filterType: 'text',
        cellRenderer: (row) => row.category?.name ?? '',
        exportValue: (row) => row.category?.name ?? '',
      },
      {
        field: 'starts_at',
        headerName: 'Início',
        width: 160,
        filterType: 'text',
        cellRenderer: (row) => formatDateBR(row.starts_at),
        exportValue: (row) => formatDateBR(row.starts_at),
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 150,
        filterType: 'text',
        cellRenderer: (row) => <StatusChip status={row.status} label={STATUS_LABELS[row.status] ?? row.status} tone={STATUS_TONES[row.status]} />,
        exportValue: (row) => STATUS_LABELS[row.status] ?? row.status,
      },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 240,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
            {can(ACCESS.eventSessionsRead) ? (
              <Tooltip title="Gerenciar sessões" arrow>
                <IconButton
                  size="small"
                  aria-label={`Gerenciar sessões de ${row.name}`}
                  onClick={() => navigate(`/eventos/${row.uuid}/sessoes`)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <ScheduleOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.eventGatesRead) ? (
              <Tooltip title="Gerenciar portarias" arrow>
                <IconButton
                  size="small"
                  aria-label={`Gerenciar portarias de ${row.name}`}
                  onClick={() => navigate(`/eventos/${row.uuid}/portarias`)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <MeetingRoomOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.eventsUpdate) ? (
              <Tooltip title="Editar evento" arrow>
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
            {can(ACCESS.eventsDelete) ? (
              <Tooltip title="Excluir evento" arrow>
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
    [handleEdit, can, navigate],
  )

  return (
    <>
      <CrudListPage
        title="Eventos"
        subtitle="Gerencie os eventos da empresa."
        createLabel="Novo evento"
        canCreate={can(ACCESS.eventsCreate)}
        onCreate={() => navigate('/eventos/novo')}
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <ServerDataGrid
          columns={columns}
          fetchPage={fetchPage}
          rowIdField="uuid"
          exportFileName="eventos"
          onGridReady={(api) => {
            gridApiRef.current = api
          }}
          emptyState={{
            icon: <EventOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
            title: 'Nenhum evento cadastrado ainda',
            description: 'Comece cadastrando o primeiro evento.',
            action: can(ACCESS.eventsCreate) ? (
              <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/eventos/novo')}>
                Cadastrar primeiro evento
              </Button>
            ) : undefined,
          }}
        />
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir evento"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
