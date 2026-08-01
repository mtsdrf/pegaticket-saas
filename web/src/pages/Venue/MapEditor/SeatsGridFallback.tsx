import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import EventSeatOutlinedIcon from '@mui/icons-material/EventSeatOutlined'
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined'
import PublishOutlinedIcon from '@mui/icons-material/PublishOutlined'
import { Alert, Box, Button, Chip, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ACCESS } from '../../../access/requirements'
import { ConfirmDeleteDialog } from '../../../components/crud/ConfirmDeleteDialog'
import { CrudListPage } from '../../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../../components/crud/serverGridTypes'
import { useAccessControl } from '../../../hooks/useAccessControl'
import { useAuth } from '../../../hooks/useAuth'
import * as seatService from '../../../services/seatService'
import * as venueService from '../../../services/venueService'
import { getApiErrorMessage } from '../../../types/api'
import { SEAT_KIND_OPTIONS, SEAT_STATUS_OPTIONS, type Seat } from '../../../types/venue'

const KIND_LABELS = Object.fromEntries(SEAT_KIND_OPTIONS.map((option) => [option.value, option.label]))
const STATUS_LABELS = Object.fromEntries(SEAT_STATUS_OPTIONS.map((option) => [option.value, option.label]))

/**
 * Fallback somente-grid do mapa de assentos para viewport pequeno (< `md`) —
 * o editor visual drag-and-drop (`VenueMapEditor`) exige precisão de mouse
 * impraticável em touch pequeno, então mobile continua usando a grid +
 * formulário (`SeatFormPage`) que já existia antes do editor visual.
 */
export function SeatsGridFallback() {
  const navigate = useNavigate()
  const { venueUuid = '' } = useParams<{ venueUuid: string }>()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const [venueName, setVenueName] = useState('Local')
  const [headerError, setHeaderError] = useState<string | null>(null)
  const [publishError, setPublishError] = useState<string | null>(null)
  const [isPublishing, setIsPublishing] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<Seat | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  useEffect(() => {
    if (!venueUuid) return

    venueService
      .getVenue(venueUuid)
      .then((venue) => setVenueName(venue.name))
      .catch((error) => setHeaderError(getApiErrorMessage(error, 'Não foi possível carregar o local agora.')))
  }, [venueUuid])

  const fetchPage = useCallback(
    async ({ page, perPage, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Seat>> => {
      if (!activeTenantUuid || !venueUuid) return { rows: [], total: 0 }

      const result = await seatService.listSeats(venueUuid, {
        ...filters,
        page,
        per_page: perPage,
      })

      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid, venueUuid],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await seatService.deleteSeat(venueUuid, deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o assento agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  async function handlePublish() {
    setIsPublishing(true)
    setPublishError(null)

    try {
      await venueService.publishVenueMap(venueUuid)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setPublishError(getApiErrorMessage(err, 'Não foi possível publicar o mapa agora.'))
    } finally {
      setIsPublishing(false)
    }
  }

  const columns = useMemo<ServerGridColumn<Seat>[]>(
    () => [
      { field: 'label', headerName: 'Código', filterType: 'text' },
      {
        field: 'sector_name',
        headerName: 'Setor',
        filterType: 'text',
        cellRenderer: (row) => row.sector_name ?? 'Sem setor',
        exportValue: (row) => row.sector_name ?? 'Sem setor',
      },
      {
        field: 'kind',
        headerName: 'Tipo',
        width: 140,
        filterType: 'none',
        cellRenderer: (row) => KIND_LABELS[row.kind] ?? row.kind,
      },
      {
        field: 'capacity',
        headerName: 'Capacidade',
        width: 120,
        filterType: 'none',
      },
      {
        field: 'status',
        headerName: 'Status',
        width: 140,
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
            {can(ACCESS.seatsUpdate) ? (
              <Tooltip title="Editar assento" arrow>
                <IconButton
                  size="small"
                  aria-label={`Editar ${row.label}`}
                  onClick={() => navigate(`/locais/${venueUuid}/assentos/${row.uuid}/editar`)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.seatsDelete) ? (
              <Tooltip title="Excluir assento" arrow>
                <IconButton
                  size="small"
                  aria-label={`Excluir ${row.label}`}
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
    [can, navigate, venueUuid],
  )

  return (
    <>
      <CrudListPage
        title={`Mapa de ${venueName}`}
        subtitle="Gerencie assentos, mesas, áreas e camarotes do rascunho atual."
        createLabel="Novo assento"
        canCreate={can(ACCESS.seatsCreate)}
        onCreate={() => navigate(`/locais/${venueUuid}/assentos/novo`)}
        secondaryAction={
          can(ACCESS.venuesUpdate) ? (
            <Button variant="outlined" startIcon={<PublishOutlinedIcon />} onClick={() => void handlePublish()} disabled={isPublishing}>
              {isPublishing ? 'Publicando…' : 'Publicar mapa'}
            </Button>
          ) : undefined
        }
        error={headerError}
        onRetry={() => navigate(0)}
        isLoading={!activeTenantUuid}
        isEmpty={false}
        breadcrumbs={[{ label: 'Locais', to: '/locais' }, { label: venueName }]}
      >
        <Alert severity="info" icon={<InfoOutlinedIcon fontSize="small" />} sx={{ mb: 2 }}>
          Edite o mapa visualmente numa tela maior. Aqui você ainda pode cadastrar, editar e excluir assentos por formulário.
        </Alert>
        {publishError ? <Alert severity="error" sx={{ mb: 2 }}>{publishError}</Alert> : null}
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 760 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="mapa-local"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <EventSeatOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                title: 'Nenhum ponto cadastrado ainda',
                description: 'Comece adicionando o primeiro assento ou mesa deste local.',
                action: can(ACCESS.seatsCreate) ? (
                  <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate(`/locais/${venueUuid}/assentos/novo`)}>
                    Cadastrar primeiro assento
                  </Button>
                ) : undefined,
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir assento"
        itemLabel={deleteTarget?.label ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
