import GroupOutlinedIcon from '@mui/icons-material/GroupOutlined'
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
import * as adminGroupService from '../../services/adminGroupService'
import type { AdminGroup } from '../../types/admin'
import { getApiErrorMessage } from '../../types/api'

export function GroupListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const isLoading = useInitialLoadGate(() => adminGroupService.listGroups({ per_page: 1 }))
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminGroup | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback((group: AdminGroup) => navigate(`/admin/grupos/${group.uuid}/editar`), [navigate])
  const fetchPage = useCallback(async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<AdminGroup>> => {
    const result = await adminGroupService.listGroups({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
    return { rows: result.items, total: result.pagination.total }
  }, [])

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await adminGroupService.deleteGroup(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o grupo agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const columns = useMemo<ServerGridColumn<AdminGroup>[]>(
    () => [
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      { field: 'is_active', headerName: 'Ativo', width: 120, filterType: 'boolean', cellRenderer: (row) => <ActiveChip isActive={row.is_active} /> },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 140,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.5}>
            {can(ACCESS.adminGroupsUpdate) ? <Tooltip title="Editar grupo" arrow><IconButton size="small" onClick={() => handleEdit(row)} sx={{ minWidth: 44, minHeight: 44 }}><EditOutlinedIcon fontSize="small" /></IconButton></Tooltip> : null}
            {can(ACCESS.adminGroupsDelete) ? <Tooltip title="Excluir grupo" arrow><IconButton size="small" onClick={() => setDeleteTarget(row)} sx={{ minWidth: 44, minHeight: 44 }}><DeleteOutlineIcon fontSize="small" /></IconButton></Tooltip> : null}
          </Stack>
        ),
      },
    ],
    [handleEdit, can],
  )

  return (
    <>
      <CrudListPage title="Grupos" subtitle="Organize permissões sistêmicas por grupos." createLabel="Novo grupo" canCreate={can(ACCESS.adminGroupsCreate)} onCreate={() => navigate('/admin/grupos/novo')} error={null} onRetry={() => undefined} isLoading={isLoading} isEmpty={false}>
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 720 }}>
            <ServerDataGrid columns={columns} fetchPage={fetchPage} rowIdField="uuid" onGridReady={(api) => { gridApiRef.current = api }} emptyState={{ icon: <GroupOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />, title: 'Nenhum grupo cadastrado', description: 'Crie grupos para agrupar permissões sistêmicas.', action: can(ACCESS.adminGroupsCreate) ? <Button variant="contained" onClick={() => navigate('/admin/grupos/novo')}>Cadastrar primeiro grupo</Button> : undefined }} />
          </Box>
        </Box>
      </CrudListPage>
      <ConfirmDeleteDialog open={deleteTarget !== null} title="Excluir grupo" itemLabel={deleteTarget?.name ?? null} isDeleting={isDeleting} error={deleteError} onCancel={() => setDeleteTarget(null)} onConfirm={() => void handleConfirmDelete()} />
    </>
  )
}
