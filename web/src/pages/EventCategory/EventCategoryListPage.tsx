import CategoryOutlinedIcon from '@mui/icons-material/CategoryOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import { Button, IconButton, Stack, Tooltip } from '@mui/material'
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
import * as eventCategoryService from '../../services/eventCategoryService'
import type { EventCategory } from '../../types/eventCategory'
import { getApiErrorMessage } from '../../types/api'

export function EventCategoryListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()

  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<EventCategory | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback(
    (category: EventCategory) => navigate(`/eventos/categorias/${category.uuid}/editar`),
    [navigate],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await eventCategoryService.deleteEventCategory(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(
        getApiErrorMessage(err, 'Não foi possível excluir a categoria agora.'),
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
    }: ServerGridFetchParams): Promise<ServerGridFetchResult<EventCategory>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await eventCategoryService.listEventCategories({
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

  const columns = useMemo<ServerGridColumn<EventCategory>[]>(
    () => [
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      { field: 'priority', headerName: 'Prioridade', width: 140, filterType: 'number' },
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
            {can(ACCESS.eventCategoriesUpdate) ? (
              <Tooltip title="Editar categoria" arrow>
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
            {can(ACCESS.eventCategoriesDelete) ? (
              <Tooltip title="Excluir categoria" arrow>
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
        title="Categorias de evento"
        subtitle="Organize os eventos por categoria (ex.: Shows, Esportes, Cursos)."
        breadcrumbs={[{ label: 'Eventos', to: '/eventos' }, { label: 'Categorias' }]}
        createLabel="Nova categoria"
        canCreate={can(ACCESS.eventCategoriesCreate)}
        onCreate={() => navigate('/eventos/categorias/nova')}
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <ServerDataGrid
          columns={columns}
          fetchPage={fetchPage}
          rowIdField="uuid"
          onGridReady={(api) => {
            gridApiRef.current = api
          }}
          emptyState={{
            icon: <CategoryOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
            title: 'Nenhuma categoria cadastrada ainda',
            description: 'Categorias agrupam os eventos (ex.: Shows, Esportes, Cursos).',
            action: can(ACCESS.eventCategoriesCreate) ? (
              <Button variant="contained" onClick={() => navigate('/eventos/categorias/nova')}>
                Cadastrar primeira categoria
              </Button>
            ) : undefined,
          }}
        />
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir categoria"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
