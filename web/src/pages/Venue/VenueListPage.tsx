import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import PlaceOutlinedIcon from '@mui/icons-material/PlaceOutlined'
import ViewModuleOutlinedIcon from '@mui/icons-material/ViewModuleOutlined'
import { Avatar, Box, Button, Chip, IconButton, Stack, Tooltip } from '@mui/material'
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
import * as venueService from '../../services/venueService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import type { Venue } from '../../types/venue'

export function VenueListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Venue | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const fetchPage = useCallback(
    async ({ page, perPage, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Venue>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await venueService.listVenues({
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
      await venueService.deleteVenue(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o local agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const columns = useMemo<ServerGridColumn<Venue>[]>(
    () => [
      {
        field: 'background_image_url',
        headerName: '',
        width: 64,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Avatar variant="rounded" src={row.background_image_url ?? undefined} sx={{ width: 32, height: 32, ...SOFT_PANEL_SX }}>
            <PlaceOutlinedIcon fontSize="small" sx={{ color: 'var(--pt-muted)' }} />
          </Avatar>
        ),
      },
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      {
        field: 'dimensions',
        headerName: 'Mapa',
        width: 160,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => (row.width && row.height ? `${row.width} x ${row.height}` : 'Sem dimensão'),
        exportValue: (row) => (row.width && row.height ? `${row.width} x ${row.height}` : 'Sem dimensão'),
      },
      {
        field: 'is_active',
        headerName: 'Status',
        width: 180,
        filterType: 'none',
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.75} sx={{ alignItems: 'center' }}>
            <Chip size="small" label={row.is_active ? 'Ativo' : 'Inativo'} color={row.is_active ? 'success' : 'default'} />
            {row.published_map_version ? <Chip size="small" variant="outlined" label={`Mapa v${row.published_map_version.version_number}`} /> : null}
          </Stack>
        ),
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
            {can(ACCESS.seatsRead) ? (
              <Tooltip title="Gerenciar mapa" arrow>
                <IconButton
                  size="small"
                  aria-label={`Gerenciar mapa de ${row.name}`}
                  onClick={() => navigate(`/locais/${row.uuid}/assentos`)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <ViewModuleOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.venuesUpdate) ? (
              <Tooltip title="Editar local" arrow>
                <IconButton
                  size="small"
                  aria-label={`Editar ${row.name}`}
                  onClick={() => navigate(`/locais/${row.uuid}/editar`)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--pt-muted)', '&:hover': { color: 'var(--pt-primary)' } }}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.venuesDelete) ? (
              <Tooltip title="Excluir local" arrow>
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
    [can, navigate],
  )

  return (
    <>
      <CrudListPage
        title="Locais"
        subtitle="Gerencie locais e mapas usados nos eventos."
        createLabel="Novo local"
        canCreate={can(ACCESS.venuesCreate)}
        onCreate={() => navigate('/locais/novo')}
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 760 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="locais"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <PlaceOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                title: 'Nenhum local cadastrado ainda',
                description: 'Comece cadastrando o primeiro local para seus eventos.',
                action: can(ACCESS.venuesCreate) ? (
                  <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/locais/novo')}>
                    Cadastrar primeiro local
                  </Button>
                ) : undefined,
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir local"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
