import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import LinkOutlinedIcon from '@mui/icons-material/LinkOutlined'
import MailOutlineIcon from '@mui/icons-material/MailOutlineOutlined'
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
import * as tenantUserService from '../../services/tenantUserService'
import type { TenantUser } from '../../types/admin'
import { getApiErrorMessage } from '../../types/api'

export function TenantUserListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const isLoading = useInitialLoadGate(() => tenantUserService.listTenantUsers({ per_page: 1 }))
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<TenantUser | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback((item: TenantUser) => navigate(`/admin/tenant-users/${item.uuid}/editar`), [navigate])
  const fetchPage = useCallback(async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<TenantUser>> => {
    const result = await tenantUserService.listTenantUsers({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
    return { rows: result.items, total: result.pagination.total }
  }, [])
  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await tenantUserService.deleteTenantUser(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o vínculo agora.'))
    } finally {
      setIsDeleting(false)
    }
  }
  const columns = useMemo<ServerGridColumn<TenantUser>[]>(() => [
    { field: 'user_name', headerName: 'Usuário', filterType: 'text', cellRenderer: (row) => row.user_name ?? row.user_uuid },
    { field: 'tenant_name', headerName: 'Empresa', filterType: 'text', cellRenderer: (row) => row.tenant_name ?? row.tenant_uuid },
    { field: 'role_name', headerName: 'Perfil', filterType: 'text', cellRenderer: (row) => row.role_name ?? row.role_uuid },
    { field: 'is_active', headerName: 'Ativo', width: 120, filterType: 'boolean', cellRenderer: (row) => <ActiveChip isActive={row.is_active} /> },
    { field: 'uuid', headerName: 'Ações', width: 140, sortable: false, filterType: 'none', cellRenderer: (row) => <Stack direction="row" spacing={0.5}>{can(ACCESS.tenantUsersUpdate) ? <Tooltip title="Editar vínculo" arrow><IconButton size="small" onClick={() => handleEdit(row)} sx={{ minWidth: 44, minHeight: 44 }}><EditOutlinedIcon fontSize="small" /></IconButton></Tooltip> : null}{can(ACCESS.tenantUsersDelete) ? <Tooltip title="Excluir vínculo" arrow><IconButton size="small" onClick={() => setDeleteTarget(row)} sx={{ minWidth: 44, minHeight: 44 }}><DeleteOutlineIcon fontSize="small" /></IconButton></Tooltip> : null}</Stack> },
  ], [handleEdit, can])
  return (
    <>
      <CrudListPage
        title="Usuários da empresa"
        subtitle="Gerencie vínculos de usuários com a empresa ativa."
        createLabel="Novo vínculo"
        canCreate={can(ACCESS.tenantUsersCreate)}
        onCreate={() => navigate('/admin/tenant-users/novo')}
        secondaryAction={
          can(ACCESS.tenantUsersCreate) ? (
            <Button
              variant="outlined"
              startIcon={<MailOutlineIcon />}
              onClick={() => navigate('/admin/tenant-users/convidar')}
              sx={{ minHeight: 44, width: { xs: '100%', sm: 'auto' } }}
            >
              Convidar usuário
            </Button>
          ) : undefined
        }
        error={null}
        onRetry={() => undefined}
        isLoading={isLoading}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}><Box sx={{ minWidth: 820 }}><ServerDataGrid columns={columns} fetchPage={fetchPage} rowIdField="uuid" onGridReady={(api) => { gridApiRef.current = api }} emptyState={{ icon: <LinkOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />, title: 'Nenhum vínculo cadastrado', description: 'Relacione usuários à empresa com um perfil de acesso ou convide alguém novo por e-mail.', action: can(ACCESS.tenantUsersCreate) ? <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.25}><Button variant="outlined" onClick={() => navigate('/admin/tenant-users/convidar')}>Convidar usuário</Button><Button variant="contained" onClick={() => navigate('/admin/tenant-users/novo')}>Cadastrar primeiro vínculo</Button></Stack> : undefined }} /></Box></Box>
      </CrudListPage>
      <ConfirmDeleteDialog open={deleteTarget !== null} title="Excluir vínculo da empresa" itemLabel={
          deleteTarget
            ? `${deleteTarget.user_name ?? deleteTarget.user_uuid} — ${deleteTarget.tenant_name ?? deleteTarget.tenant_uuid}`
            : null
        } isDeleting={isDeleting} error={deleteError} onCancel={() => setDeleteTarget(null)} onConfirm={() => void handleConfirmDelete()} />
    </>
  )
}
