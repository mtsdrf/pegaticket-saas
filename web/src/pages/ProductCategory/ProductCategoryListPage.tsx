import CategoryOutlinedIcon from '@mui/icons-material/CategoryOutlined'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
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
import { useAuth } from '../../hooks/useAuth'
import * as productCategoryService from '../../services/productCategoryService'
import type { ProductCategory } from '../../types/productCategory'
import { getApiErrorMessage } from '../../types/api'

export function ProductCategoryListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()

  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<ProductCategory | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback(
    (category: ProductCategory) => navigate(`/produtos/categorias/${category.uuid}/editar`),
    [navigate],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await productCategoryService.deleteProductCategory(deleteTarget.uuid)
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
    }: ServerGridFetchParams): Promise<ServerGridFetchResult<ProductCategory>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await productCategoryService.listProductCategories({
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

  const columns = useMemo<ServerGridColumn<ProductCategory>[]>(
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
            {can(ACCESS.productCategoriesUpdate) ? (
              <Tooltip title="Editar categoria" arrow>
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
            {can(ACCESS.productCategoriesDelete) ? (
              <Tooltip title="Excluir categoria" arrow>
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
        title="Categorias de produto"
        subtitle="Organize os produtos por categoria (ex.: Doces, Salgados)."
        breadcrumbs={[{ label: 'Produtos', to: '/produtos' }, { label: 'Categorias' }]}
        createLabel="Nova categoria"
        canCreate={can(ACCESS.productCategoriesCreate)}
        onCreate={() => navigate('/produtos/categorias/nova')}
        error={null}
        onRetry={() => undefined}
        isLoading={!activeTenantUuid}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 480 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <CategoryOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
                title: 'Nenhuma categoria cadastrada ainda',
                description: 'Categorias agrupam os tipos de produto (ex.: Doces → Bolos, Tortas).',
                action: can(ACCESS.productCategoriesCreate) ? (
                  <Button variant="contained" onClick={() => navigate('/produtos/categorias/nova')}>
                    Cadastrar primeira categoria
                  </Button>
                ) : undefined,
              }}
            />
          </Box>
        </Box>
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
