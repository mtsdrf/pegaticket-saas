import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import HomeOutlinedIcon from '@mui/icons-material/HomeOutlined'
import { Box, Button, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { GeocodeStatusChip } from '../../components/crud/GeocodeStatusChip'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as enderecoService from '../../services/enderecoService'
import type { Endereco } from '../../types/location'
import { getApiErrorMessage } from '../../types/api'

export function EnderecoListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Endereco | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback((endereco: Endereco) => navigate(`/enderecos/${endereco.uuid}/editar`), [navigate])

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await enderecoService.deleteEndereco(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o endereço agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Endereco>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await enderecoService.listEnderecos({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid],
  )

  const columns = useMemo<ServerGridColumn<Endereco>[]>(
    () => [
      { field: 'logradouro', headerName: 'Logradouro', filterType: 'text' },
      { field: 'numero', headerName: 'Número', width: 110, filterType: 'none', sortable: false, cellRenderer: (row) => row.numero ?? '' },
      { field: 'cidade_name', headerName: 'Cidade', filterType: 'text' },
      { field: 'bairro_name', headerName: 'Bairro', filterType: 'text' },
      {
        field: 'is_active',
        headerName: 'Ativo',
        width: 130,
        filterType: 'boolean',
        cellRenderer: (row) => <ActiveChip isActive={row.is_active} />,
      },
      {
        field: 'geocode_status',
        headerName: 'Localização',
        width: 160,
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => <GeocodeStatusChip status={row.geocode_status} />,
      },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 140,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
            {can(ACCESS.enderecosUpdate) ? (
              <Tooltip title="Editar endereço" arrow>
                <IconButton
                  size="small"
                  aria-label={`Editar ${row.logradouro}`}
                  onClick={() => handleEdit(row)}
                  sx={{ minWidth: 44, minHeight: 44, color: 'var(--mk-muted)', '&:hover': { color: 'var(--mk-primary)' } }}
                >
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.enderecosDelete) ? (
              <Tooltip title="Excluir endereço" arrow>
                <IconButton
                  size="small"
                  aria-label={`Excluir ${row.logradouro}`}
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
        title="Endereços"
        subtitle="Gerencie os endereços cadastrados pela empresa."
        breadcrumbs={[{ label: 'Endereço', to: '/enderecos' }, { label: 'Endereços' }]}
        createLabel="Novo endereço"
        canCreate={can(ACCESS.enderecosCreate)}
        onCreate={() => navigate('/enderecos/novo')}
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 860 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <HomeOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
                title: 'Nenhum endereço cadastrado ainda',
                description: 'Cadastre endereços pra vincular a clientes e pedidos.',
                action: can(ACCESS.enderecosCreate) ? (
                  <Button variant="contained" onClick={() => navigate('/enderecos/novo')}>
                    Cadastrar primeiro endereço
                  </Button>
                ) : undefined,
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir endereço"
        itemLabel={deleteTarget?.logradouro ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
