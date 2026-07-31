import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import ScheduleOutlinedIcon from '@mui/icons-material/ScheduleOutlined'
import { Box, Button, Chip, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as eventService from '../../services/eventService'
import * as eventSessionService from '../../services/eventSessionService'
import { getApiErrorMessage } from '../../types/api'
import { EVENT_SESSION_STATUS_OPTIONS, type EventSession } from '../../types/eventSession'
import { formatDateFromDateTimeBR } from '../../utils/format'

const STATUS_LABELS = Object.fromEntries(EVENT_SESSION_STATUS_OPTIONS.map((option) => [option.value, option.label]))

export function EventSessionListPage() {
  const navigate = useNavigate()
  const { eventUuid = '' } = useParams<{ eventUuid: string }>()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const [eventName, setEventName] = useState('Evento')
  const [headerError, setHeaderError] = useState<string | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<EventSession | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  useEffect(() => {
    eventService
      .getEvent(eventUuid)
      .then((event) => setEventName(event.name))
      .catch((error) => setHeaderError(getApiErrorMessage(error, 'Não foi possível carregar o evento agora.')))
  }, [eventUuid])

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<EventSession>> => {
      if (!activeTenantUuid || !eventUuid) return { rows: [], total: 0 }

      const result = await eventSessionService.listEventSessions(eventUuid, {
        ...filters,
        page,
        per_page: perPage,
        sort_by: sortBy as 'starts_at' | 'status' | undefined,
        sort_dir: sortDir,
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
      await eventSessionService.deleteEventSession(eventUuid, deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir a sessão agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const columns = useMemo<ServerGridColumn<EventSession>[]>(
    () => [
      {
        field: 'name',
        headerName: 'Sessão',
        filterType: 'none',
        cellRenderer: (row) => row.name || 'Sessão sem nome',
        exportValue: (row) => row.name || 'Sessão sem nome',
      },
      {
        field: 'starts_at',
        headerName: 'Início',
        width: 170,
        filterType: 'none',
        cellRenderer: (row) => formatDateFromDateTimeBR(row.starts_at),
        exportValue: (row) => formatDateFromDateTimeBR(row.starts_at),
      },
      {
        field: 'ends_at',
        headerName: 'Término',
        width: 170,
        filterType: 'none',
        cellRenderer: (row) => formatDateFromDateTimeBR(row.ends_at),
        exportValue: (row) => formatDateFromDateTimeBR(row.ends_at),
      },
      {
        field: 'capacity',
        headerName: 'Capacidade',
        width: 130,
        filterType: 'none',
        cellRenderer: (row) => (row.capacity ?? 'Livre'),
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 170,
        filterType: 'none',
        cellRenderer: (row) => <Chip size="small" label={STATUS_LABELS[row.status] ?? row.status} />,
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
            {can(ACCESS.eventSessionsUpdate) ? (
              <Tooltip title="Editar sessão" arrow>
                <IconButton
                  size="small"
                  aria-label={`Editar sessão ${row.name ?? row.uuid}`}
                  onClick={() => navigate(`/eventos/${eventUuid}/sessoes/${row.uuid}/editar`)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.eventSessionsDelete) ? (
              <Tooltip title="Excluir sessão" arrow>
                <IconButton
                  size="small"
                  aria-label={`Excluir sessão ${row.name ?? row.uuid}`}
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
        title={`Sessões de ${eventName}`}
        subtitle="Gerencie datas, janelas de venda e capacidade do evento."
        createLabel="Nova sessão"
        canCreate={can(ACCESS.eventSessionsCreate)}
        onCreate={() => navigate(`/eventos/${eventUuid}/sessoes/nova`)}
        error={headerError}
        onRetry={() => navigate(0)}
        isLoading={!activeTenantUuid}
        isEmpty={false}
        breadcrumbs={[{ label: 'Eventos', to: '/eventos' }, { label: eventName }]}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 820 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="sessoes-evento"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <ScheduleOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                title: 'Nenhuma sessão cadastrada ainda',
                description: 'Comece adicionando a primeira data operacional deste evento.',
                action: can(ACCESS.eventSessionsCreate) ? (
                  <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate(`/eventos/${eventUuid}/sessoes/nova`)}>
                    Cadastrar primeira sessão
                  </Button>
                ) : undefined,
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir sessão"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
