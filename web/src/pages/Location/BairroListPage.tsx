import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import RoomOutlinedIcon from '@mui/icons-material/RoomOutlined'
import { Box, Button, IconButton, Stack, Tooltip } from '@mui/material'
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
import { useInitialLoadGate } from '../../hooks/useInitialLoadGate'
import * as bairroService from '../../services/bairroService'
import type { Bairro } from '../../types/location'
import { getApiErrorMessage } from '../../types/api'

export function BairroListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const isLoading = useInitialLoadGate(() => bairroService.listBairros({ per_page: 1 }))
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Bairro | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback((bairro: Bairro) => navigate(`/bairros/${bairro.uuid}/editar`), [navigate])

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await bairroService.deleteBairro(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o bairro agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Bairro>> => {
      const result = await bairroService.listBairros({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
      return { rows: result.items, total: result.pagination.total }
    },
    [],
  )

  const columns = useMemo<ServerGridColumn<Bairro>[]>(
    () => [
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      { field: 'cidade_name', headerName: 'Cidade', filterType: 'text' },
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
            {can(ACCESS.bairrosUpdate) ? (
              <Tooltip title="Editar bairro" arrow>
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
            {can(ACCESS.bairrosDelete) ? (
              <Tooltip title="Excluir bairro" arrow>
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
        title="Bairros"
        subtitle="Gerencie os bairros usados nos endereços do sistema."
        breadcrumbs={[{ label: 'Endereço', to: '/bairros' }, { label: 'Bairros' }]}
        createLabel="Novo bairro"
        canCreate={can(ACCESS.bairrosCreate)}
        onCreate={() => navigate('/bairros/novo')}
        error={null}
        onRetry={() => undefined}
        isLoading={isLoading}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 560 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <RoomOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                title: 'Nenhum bairro cadastrado ainda',
                description: 'Cadastre bairros dentro de uma cidade para usar nos endereços.',
                action: can(ACCESS.bairrosCreate) ? (
                  <Button variant="contained" onClick={() => navigate('/bairros/novo')}>
                    Cadastrar primeiro bairro
                  </Button>
                ) : undefined,
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir bairro"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
