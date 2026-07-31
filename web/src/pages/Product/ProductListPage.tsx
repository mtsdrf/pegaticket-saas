import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import PictureAsPdfOutlinedIcon from '@mui/icons-material/PictureAsPdfOutlined'
import UploadFileOutlinedIcon from '@mui/icons-material/UploadFileOutlined'
import { Alert, Avatar, Box, Button, CircularProgress, IconButton, Stack, Tooltip } from '@mui/material'
import type { GridApi } from 'ag-grid-community'
import { useCallback, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ActiveChip } from '../../components/crud/ActiveChip'
import { ConfirmDeleteDialog } from '../../components/crud/ConfirmDeleteDialog'
import { ProductAvailabilityToggle } from '../../components/product/ProductAvailabilityToggle'
import { ProductImportDialog } from '../../components/product/ProductImportDialog'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { ACCESS } from '../../access/requirements'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as productService from '../../services/productService'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import type { Product } from '../../types/product'
import { getApiErrorMessage } from '../../types/api'
import { formatCurrency } from '../../utils/format'
import { triggerBlobDownload } from '../../utils/fileDownload'
import { buildBackendFilters } from '../../utils/gridExport'

export function ProductListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()
  const gridApiRef = useRef<GridApi | null>(null)

  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const [isExportingCatalogPdf, setIsExportingCatalogPdf] = useState(false)
  const [exportCatalogError, setExportCatalogError] = useState<string | null>(null)
  const [isImportDialogOpen, setIsImportDialogOpen] = useState(false)

  const handleEdit = useCallback(
    (product: Product) => navigate(`/produtos/${product.uuid}/editar`),
    [navigate],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)

    try {
      await productService.deleteProduct(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o produto agora.'))
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
    }: ServerGridFetchParams): Promise<ServerGridFetchResult<Product>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }

      const result = await productService.listProducts({
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

  const columns = useMemo<ServerGridColumn<Product>[]>(
    () => [
      {
        field: 'image_url',
        headerName: '',
        width: 64,
        sortable: false,
        filterType: 'none',
        exportable: false,
        cellRenderer: (row) => (
          <Avatar
            variant="rounded"
            src={row.image_url ?? undefined}
            sx={{ width: 32, height: 32, ...SOFT_PANEL_SX }}
          >
            <Inventory2OutlinedIcon fontSize="small" sx={{ color: 'var(--pt-muted)' }} />
          </Avatar>
        ),
      },
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      {
        field: 'product_type_name',
        headerName: 'Tipo',
        filterType: 'text',
        cellRenderer: (row) => row.product_type?.name ?? '',
        exportValue: (row) => row.product_type?.name ?? '',
      },
      {
        field: 'product_category_name',
        headerName: 'Categoria',
        filterType: 'text',
        cellRenderer: (row) => row.product_type?.product_category?.name ?? '',
        exportValue: (row) => row.product_type?.product_category?.name ?? '',
      },
      {
        field: 'price',
        headerName: 'Preço',
        width: 140,
        filterType: 'number',
        cellRenderer: (row) => formatCurrency(row.price),
        exportValue: (row) => formatCurrency(row.price),
      },
      {
        field: 'is_available',
        headerName: 'Disponível',
        width: 110,
        filterType: 'boolean',
        cellRenderer: (row) =>
          can(ACCESS.productsUpdate) ? (
            <ProductAvailabilityToggle product={row} onToggled={() => gridApiRef.current?.refreshInfiniteCache()} />
          ) : (
            <ActiveChip isActive={row.is_available} />
          ),
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
            {can(ACCESS.productsUpdate) ? (
              <Tooltip title="Editar produto" arrow>
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
            {can(ACCESS.productsDelete) ? (
              <Tooltip title="Excluir produto" arrow>
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

  /** Usa os mesmos filtros já aplicados nas colunas da grid (não mantém um segundo estado de filtro em paralelo). */
  async function handleExportCatalogPdf() {
    setExportCatalogError(null)
    setIsExportingCatalogPdf(true)
    try {
      const filterModel = (gridApiRef.current?.getFilterModel() ?? {}) as Record<string, unknown>
      const filters = buildBackendFilters(columns, filterModel)
      const blob = await productService.exportProductsPdf({ ...filters })
      const today = new Date().toISOString().slice(0, 10)
      triggerBlobDownload(blob, `catalogo-produtos-${today}.pdf`)
    } catch (err) {
      setExportCatalogError(getApiErrorMessage(err, 'Não foi possível exportar o catálogo em PDF agora.'))
    } finally {
      setIsExportingCatalogPdf(false)
    }
  }

  return (
    <>
      <CrudListPage
        title="Produtos"
        subtitle="Gerencie o catálogo de produtos da empresa."
        createLabel="Novo produto"
        canCreate={can(ACCESS.productsCreate)}
        onCreate={() => navigate('/produtos/novo')}
        toolbar={
          <Stack spacing={1} sx={{ width: { xs: '100%', sm: 'auto' }, alignItems: { xs: 'stretch', sm: 'flex-end' } }}>
            {can(ACCESS.productsCreate) && (
              <Button
                variant="outlined"
                startIcon={<UploadFileOutlinedIcon />}
                onClick={() => setIsImportDialogOpen(true)}
                disabled={!activeTenantUuid}
                sx={{ minHeight: 44, width: { xs: '100%', sm: 'auto' } }}
              >
                Importar produtos (CSV)
              </Button>
            )}
            <Button
              variant="outlined"
              startIcon={isExportingCatalogPdf ? <CircularProgress size={16} /> : <PictureAsPdfOutlinedIcon />}
              onClick={() => void handleExportCatalogPdf()}
              disabled={!activeTenantUuid || isExportingCatalogPdf}
              sx={{ minHeight: 44, width: { xs: '100%', sm: 'auto' } }}
            >
              {isExportingCatalogPdf ? 'Exportando catálogo...' : 'Exportar catálogo PDF'}
            </Button>
            {exportCatalogError && (
              <Alert severity="error" variant="outlined" onClose={() => setExportCatalogError(null)}>
                {exportCatalogError}
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
          <Box sx={{ minWidth: 720 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              exportFileName="produtos"
              onGridReady={(api) => {
                gridApiRef.current = api
              }}
              emptyState={{
                icon: <Inventory2OutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                title: 'Nenhum produto cadastrado ainda',
                description: 'Comece cadastrando o primeiro produto do catálogo.',
                action: can(ACCESS.productsCreate) ? (
                  <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/produtos/novo')}>
                    Cadastrar primeiro produto
                  </Button>
                ) : undefined,
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir produto"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />

      <ProductImportDialog
        open={isImportDialogOpen}
        onClose={() => setIsImportDialogOpen(false)}
        onImported={() => gridApiRef.current?.refreshInfiniteCache()}
      />
    </>
  )
}
