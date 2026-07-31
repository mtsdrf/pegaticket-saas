import AppsOutlinedIcon from '@mui/icons-material/AppsOutlined'
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
import * as functionalityService from '../../services/functionalityService'
import type { Functionality } from '../../types/admin'
import { getApiErrorMessage } from '../../types/api'

export function FunctionalityListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const isLoading = useInitialLoadGate(() => functionalityService.listFunctionalities({ per_page: 1 }))
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Functionality | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const handleEdit = useCallback((item: Functionality) => navigate(`/admin/funcionalidades/${item.uuid}/editar`), [navigate])
  const fetchPage = useCallback(async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Functionality>> => {
    const result = await functionalityService.listFunctionalities({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
    return { rows: result.items, total: result.pagination.total }
  }, [])
  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await functionalityService.deleteFunctionality(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir a funcionalidade agora.'))
    } finally {
      setIsDeleting(false)
    }
  }
  const columns = useMemo<ServerGridColumn<Functionality>[]>(() => [
    { field: 'name', headerName: 'Nome', filterType: 'text' },
    { field: 'description', headerName: 'Descrição', filterType: 'text' },
    { field: 'is_active', headerName: 'Ativa', width: 120, filterType: 'boolean', cellRenderer: (row) => <ActiveChip isActive={row.is_active} /> },
    {
      field: 'uuid', headerName: 'Ações', width: 140, sortable: false, filterType: 'none',
      cellRenderer: (row) => <Stack direction="row" spacing={0.5}>{can(ACCESS.adminFunctionalitiesUpdate) ? <Tooltip title="Editar funcionalidade" arrow><IconButton size="small" onClick={() => handleEdit(row)} sx={{ minWidth: 44, minHeight: 44 }}><EditOutlinedIcon fontSize="small" /></IconButton></Tooltip> : null}{can(ACCESS.adminFunctionalitiesDelete) ? <Tooltip title="Excluir funcionalidade" arrow><IconButton size="small" onClick={() => setDeleteTarget(row)} sx={{ minWidth: 44, minHeight: 44 }}><DeleteOutlineIcon fontSize="small" /></IconButton></Tooltip> : null}</Stack>,
    },
  ], [handleEdit, can])
  return (
    <>
      <CrudListPage title="Funcionalidades" subtitle="Gerencie os slugs funcionais usados no RBAC." createLabel="Nova funcionalidade" canCreate={can(ACCESS.adminFunctionalitiesCreate)} onCreate={() => navigate('/admin/funcionalidades/nova')} error={null} onRetry={() => undefined} isLoading={isLoading} isEmpty={false}>
        <Box sx={{ overflowX: 'auto' }}><Box sx={{ minWidth: 900 }}><ServerDataGrid columns={columns} fetchPage={fetchPage} rowIdField="uuid" onGridReady={(api) => { gridApiRef.current = api }} emptyState={{ icon: <AppsOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />, title: 'Nenhuma funcionalidade cadastrada', description: 'Cadastre funcionalidades para uso nas permissões.', action: can(ACCESS.adminFunctionalitiesCreate) ? <Button variant="contained" onClick={() => navigate('/admin/funcionalidades/nova')}>Cadastrar primeira funcionalidade</Button> : undefined }} /></Box></Box>
      </CrudListPage>
      <ConfirmDeleteDialog open={deleteTarget !== null} title="Excluir funcionalidade" itemLabel={deleteTarget?.name ?? null} isDeleting={isDeleting} error={deleteError} onCancel={() => setDeleteTarget(null)} onConfirm={() => void handleConfirmDelete()} />
    </>
  )
}
