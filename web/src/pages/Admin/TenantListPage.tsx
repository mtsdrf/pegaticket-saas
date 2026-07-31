import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
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
import { useInitialLoadGate } from '../../hooks/useInitialLoadGate'
import * as tenantAdminService from '../../services/tenantAdminService'
import type { Tenant } from '../../types/admin'
import { getApiErrorMessage } from '../../types/api'

export function TenantListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const isLoading = useInitialLoadGate(() => tenantAdminService.listTenants({ per_page: 1 }))
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Tenant | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const handleEdit = useCallback((item: Tenant) => navigate(`/admin/tenants/${item.uuid}/editar`), [navigate])
  const fetchPage = useCallback(async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Tenant>> => {
    const result = await tenantAdminService.listTenants({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
    return { rows: result.items, total: result.pagination.total }
  }, [])
  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await tenantAdminService.deleteTenant(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir a empresa agora.'))
    } finally {
      setIsDeleting(false)
    }
  }
  const columns = useMemo<ServerGridColumn<Tenant>[]>(() => [
    { field: 'name', headerName: 'Nome', filterType: 'text' },
    { field: 'plan_name', headerName: 'Plano', filterType: 'text' },
    { field: 'trial_ends_at', headerName: 'Fim do trial', filterType: 'none', sortable: false },
    { field: 'is_active', headerName: 'Ativo', width: 120, filterType: 'boolean', cellRenderer: (row) => <ActiveChip isActive={row.is_active} /> },
    { field: 'uuid', headerName: 'Ações', width: 140, sortable: false, filterType: 'none', cellRenderer: (row) => <Stack direction="row" spacing={0.5}>{can(ACCESS.adminTenantsUpdate) ? <Tooltip title="Editar empresa" arrow><IconButton size="small" onClick={() => handleEdit(row)} sx={{ minWidth: 44, minHeight: 44 }}><EditOutlinedIcon fontSize="small" /></IconButton></Tooltip> : null}{can(ACCESS.adminTenantsDelete) ? <Tooltip title="Excluir empresa" arrow><IconButton size="small" onClick={() => setDeleteTarget(row)} sx={{ minWidth: 44, minHeight: 44 }}><DeleteOutlineIcon fontSize="small" /></IconButton></Tooltip> : null}</Stack> },
  ], [handleEdit, can])
  return (
    <>
      <CrudListPage title="Empresas" subtitle="Gerencie as empresas da plataforma." createLabel="Nova empresa" canCreate={can(ACCESS.adminTenantsCreate)} onCreate={() => navigate('/admin/tenants/novo')} error={null} onRetry={() => undefined} isLoading={isLoading} isEmpty={false}>
        <Box sx={{ overflowX: 'auto' }}><Box sx={{ minWidth: 820 }}><ServerDataGrid columns={columns} fetchPage={fetchPage} rowIdField="uuid" onGridReady={(api) => { gridApiRef.current = api }} emptyState={{ icon: <ApartmentOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />, title: 'Nenhuma empresa cadastrada', description: 'Cadastre empresas para separar a operação multiempresa.', action: can(ACCESS.adminTenantsCreate) ? <Button variant="contained" onClick={() => navigate('/admin/tenants/novo')}>Cadastrar primeira empresa</Button> : undefined }} /></Box></Box>
      </CrudListPage>
      <ConfirmDeleteDialog open={deleteTarget !== null} title="Excluir empresa" itemLabel={deleteTarget?.name ?? null} isDeleting={isDeleting} error={deleteError} onCancel={() => setDeleteTarget(null)} onConfirm={() => void handleConfirmDelete()} />
    </>
  )
}
