import AddIcon from '@mui/icons-material/Add'
import PeopleOutlineOutlinedIcon from '@mui/icons-material/PeopleOutlineOutlined'
import PictureAsPdfOutlinedIcon from '@mui/icons-material/PictureAsPdfOutlined'
import { Alert, Box, Button, CircularProgress, Stack, useMediaQuery } from '@mui/material'
import { useTheme } from '@mui/material/styles'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ClientActiveChip } from '../../components/client/ClientActiveChip'
import { ClientDeleteDialog } from '../../components/client/ClientDeleteDialog'
import { ClientRowActions } from '../../components/client/ClientRowActions'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as clientService from '../../services/clientService'
import type { Client } from '../../types/client'
import { getApiErrorMessage } from '../../types/api'
import { triggerBlobDownload } from '../../utils/fileDownload'
import { buildBackendFilters } from '../../utils/gridExport'

export function ClientListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const muiTheme = useTheme()
  const isMobile = useMediaQuery(muiTheme.breakpoints.down('sm'))
  const gridApiRef = useRef<GridApi | null>(null)

  const [deleteTarget, setDeleteTarget] = useState<Client | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const [isExportingDirectoryPdf, setIsExportingDirectoryPdf] = useState(false)
  const [exportDirectoryError, setExportDirectoryError] = useState<string | null>(null)

  const handleEdit = useCallback(
    (client: Client) => navigate(`/clientes/${client.uuid}/editar`),
    [navigate],
  )
  const handleViewOrders = useCallback(
    (client: Client) => navigate(`/clientes/${client.uuid}/pedidos`),
    [navigate],
  )
  const handleDeleteRequest = useCallback((client: Client) => {
    setDeleteError(null)
    setDeleteTarget(client)
  }, [])

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await clientService.deleteClient(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(
        getApiErrorMessage(err, 'Não foi possível excluir o cliente agora.'),
      )
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
    }: ServerGridFetchParams): Promise<ServerGridFetchResult<Client>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await clientService.listClients({
        ...filters,
        page,
        per_page: perPage,
        sort_by: sortBy,
        sort_dir: sortDir,
      })

      return { rows: result.clients, total: result.pagination.total }
    },
    [activeTenantUuid],
  )

  const columns = useMemo<ServerGridColumn<Client>[]>(() => {
    const cols: ServerGridColumn<Client>[] = [{ field: 'name', headerName: 'Nome', filterType: 'text' }]

    if (!isMobile) {
      cols.push(
        {
          field: 'phone_primary',
          headerName: 'Telefone',
          width: 160,
          filterType: 'text',
          // 157 clientes migrados têm phone_primary = "Nãoinformado" (sem
          // espaço) gravado de verdade no banco — placeholder digitado
          // manualmente no sistema legado, não é null. Normaliza esse valor
          // conhecido junto com o caso null/vazio real.
          cellRenderer: (row) => {
            const raw = row.phone_primary?.trim()
            if (!raw || raw.toLowerCase() === 'nãoinformado') return 'Não informado'
            return raw
          },
          exportValue: (row) => {
            const raw = row.phone_primary?.trim()
            if (!raw || raw.toLowerCase() === 'nãoinformado') return 'Não informado'
            return raw
          },
        },
        {
          field: 'cidade_name',
          headerName: 'Cidade',
          width: 160,
          filterType: 'text',
          cellRenderer: (row) => row.endereco.cidade_name ?? '',
          exportValue: (row) => row.endereco.cidade_name ?? '',
        },
      )
    }

    cols.push(
      {
        field: 'is_active',
        headerName: 'Ativo',
        width: 130,
        filterType: 'boolean',
        cellRenderer: (row) => <ClientActiveChip isActive={row.is_active} />,
      },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 140,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <ClientRowActions
            client={row}
            onEdit={handleEdit}
            onDeleteRequest={handleDeleteRequest}
            onViewOrders={handleViewOrders}
            canEdit={can(ACCESS.clientsUpdate)}
            canDelete={can(ACCESS.clientsDelete)}
          />
        ),
      },
    )

    return cols
  }, [isMobile, handleEdit, handleDeleteRequest, handleViewOrders, can])

  /** Usa os mesmos filtros já aplicados nas colunas da grid (não mantém um segundo estado de filtro em paralelo). */
  async function handleExportDirectoryPdf() {
    setExportDirectoryError(null)
    setIsExportingDirectoryPdf(true)
    try {
      const filterModel = (gridApiRef.current?.getFilterModel() ?? {}) as Record<string, unknown>
      const filters = buildBackendFilters(columns, filterModel)
      const blob = await clientService.exportClientsPdf({ ...filters })
      const today = new Date().toISOString().slice(0, 10)
      triggerBlobDownload(blob, `diretorio-clientes-${today}.pdf`)
    } catch (err) {
      setExportDirectoryError(getApiErrorMessage(err, 'Não foi possível exportar o diretório em PDF agora.'))
    } finally {
      setIsExportingDirectoryPdf(false)
    }
  }

  return (
    <>
      <CrudListPage
        title="Clientes"
        subtitle="Gerencie a base de clientes da empresa."
        createLabel="Novo cliente"
        canCreate={can(ACCESS.clientsCreate)}
        onCreate={() => navigate('/clientes/novo')}
        toolbar={
          <Stack spacing={1} sx={{ width: { xs: '100%', sm: 'auto' }, alignItems: { xs: 'stretch', sm: 'flex-end' } }}>
            <Button
              variant="outlined"
              startIcon={isExportingDirectoryPdf ? <CircularProgress size={16} /> : <PictureAsPdfOutlinedIcon />}
              onClick={() => void handleExportDirectoryPdf()}
              disabled={!activeTenantUuid || isExportingDirectoryPdf}
              sx={{ minHeight: 44, width: { xs: '100%', sm: 'auto' } }}
            >
              {isExportingDirectoryPdf ? 'Exportando diretório...' : 'Exportar diretório PDF'}
            </Button>
            {exportDirectoryError && (
              <Alert severity="error" variant="outlined" onClose={() => setExportDirectoryError(null)}>
                {exportDirectoryError}
              </Alert>
            )}
          </Stack>
        }
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: isMobile ? 420 : 640 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="clientes"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <PeopleOutlineOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
                title: 'Nenhum cliente cadastrado ainda',
                description: 'Comece cadastrando o primeiro cliente da empresa.',
                action: can(ACCESS.clientsCreate) ? (
                  <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/clientes/novo')}>
                    Cadastrar primeiro cliente
                  </Button>
                ) : undefined,
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <ClientDeleteDialog
        client={deleteTarget}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
