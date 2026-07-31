import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import WarehouseOutlinedIcon from '@mui/icons-material/WarehouseOutlined'
import { Box, Button, Chip, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as stockLocationService from '../../services/stockLocationService'
import type { StockLocation } from '../../types/stockLocation'
import { getApiErrorMessage } from '../../types/api'

export function StockLocationListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)

  const [deleteTarget, setDeleteTarget] = useState<StockLocation | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback(
    (location: StockLocation) => navigate(`/estoque/locais/${location.uuid}/editar`),
    [navigate],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await stockLocationService.deleteStockLocation(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o local agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<StockLocation>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await stockLocationService.listStockLocations({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid],
  )

  const columns = useMemo<ServerGridColumn<StockLocation>[]>(
    () => [
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      { field: 'type', headerName: 'Tipo', filterType: 'text' },
      { field: 'address', headerName: 'Endereço', filterType: 'text' },
      {
        field: 'is_default',
        headerName: 'Padrão',
        width: 130,
        filterType: 'boolean',
        cellRenderer: (row) => (
          <Chip
            size="small"
            label={row.is_default ? 'Sim' : 'Não'}
            sx={{
              fontWeight: 600,
              bgcolor: row.is_default
                ? 'color-mix(in srgb, var(--mk-info) 14%, transparent)'
                : 'color-mix(in srgb, var(--mk-muted) 14%, transparent)',
              color: row.is_default ? 'var(--mk-info)' : 'var(--mk-muted)',
            }}
          />
        ),
      },
      {
        field: 'is_active',
        headerName: 'Ativo',
        width: 130,
        filterType: 'boolean',
        cellRenderer: (row) => <ActiveChip isActive={row.is_active} />,
      },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 140,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
            {can(ACCESS.stockLocationsUpdate) ? (
              <Tooltip title="Editar local" arrow>
                <IconButton
                  size="small"
                  aria-label={`Editar ${row.name}`}
                  onClick={() => handleEdit(row)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--mk-muted)', '&:hover': { color: 'var(--mk-primary)' } }}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.stockLocationsDelete) ? (
              <Tooltip title="Excluir local" arrow>
                <IconButton
                  size="small"
                  aria-label={`Excluir ${row.name}`}
                  onClick={() => {
                    setDeleteError(null)
                    setDeleteTarget(row)
                  }}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--mk-muted)', '&:hover': { color: 'var(--mk-danger)' } }}
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
        title="Locais de estoque"
        subtitle="Gerencie depósitos, lojas e pontos de guarda de produtos."
        breadcrumbs={[{ label: 'Estoque', to: '/estoque/locais' }, { label: 'Locais' }]}
        createLabel="Novo local"
        canCreate={can(ACCESS.stockLocationsCreate)}
        onCreate={() => navigate('/estoque/locais/novo')}
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 780 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <WarehouseOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
                title: 'Nenhum local de estoque cadastrado',
                description: 'Cadastre os locais onde os produtos podem ficar armazenados.',
                action: can(ACCESS.stockLocationsCreate) ? (
                  <Button variant="contained" onClick={() => navigate('/estoque/locais/novo')}>
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
        title="Excluir local de estoque"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
