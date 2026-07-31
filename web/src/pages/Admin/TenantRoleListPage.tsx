import BadgeOutlinedIcon from '@mui/icons-material/BadgeOutlined'
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
import * as tenantRoleService from '../../services/tenantRoleService'
import type { TenantRole } from '../../types/admin'
import { getApiErrorMessage } from '../../types/api'

export function TenantRoleListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid, accessProfile } = useAuth()
  const isAdministrator = Boolean(accessProfile?.is_administrator)
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<TenantRole | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)
  const handleEdit = useCallback((item: TenantRole) => navigate(`/admin/tenant-roles/${item.uuid}/editar`), [navigate])
  const fetchPage = useCallback(async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<TenantRole>> => {
    if (!activeTenantUuid) return { rows: [], total: 0 }
    const result = await tenantRoleService.listTenantRoles({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
    return { rows: result.items, total: result.pagination.total }
  }, [activeTenantUuid])
  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await tenantRoleService.deleteTenantRole(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o perfil agora.'))
    } finally {
      setIsDeleting(false)
    }
  }
  const columns = useMemo<ServerGridColumn<TenantRole>[]>(() => [
    { field: 'name', headerName: 'Nome', filterType: 'text' },
    { field: 'is_active', headerName: 'Ativo', width: 120, filterType: 'boolean', cellRenderer: (row) => <ActiveChip isActive={row.is_active} /> },
    {
      field: 'uuid',
      headerName: 'Ações',
      width: 140,
      sortable: false,
      filterType: 'none',
      cellRenderer: (row) => {
        const isProtectedOwnerRole = row.slug === 'owner'
        const canEditThisRole = can(ACCESS.tenantRolesUpdate) && (!isProtectedOwnerRole || isAdministrator)
        // Exclusão do perfil "owner" é bloqueada no backend mesmo para admin
        // da plataforma — apagar o perfil que sustenta o acesso do dono à
        // empresa não tem cenário legítimo, então o botão nem aparece.
        const canDeleteThisRole = can(ACCESS.tenantRolesDelete) && !isProtectedOwnerRole
        return (
          <Stack direction="row" spacing={0.5}>
            {canEditThisRole ? (
              <Tooltip title="Editar perfil" arrow>
                <IconButton size="small" onClick={() => handleEdit(row)} sx={{ minWidth: 44, minHeight: 44 }}>
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : isProtectedOwnerRole ? (
              <Tooltip title="Perfil protegido — apenas administradores da plataforma podem alterá-lo" arrow>
                <span>
                  <IconButton size="small" disabled sx={{ minWidth: 44, minHeight: 44 }}>
                    <EditOutlinedIcon fontSize="small" />
                  </IconButton>
                </span>
              </Tooltip>
            ) : null}
            {canDeleteThisRole ? (
              <Tooltip title="Excluir perfil" arrow>
                <IconButton size="small" onClick={() => setDeleteTarget(row)} sx={{ minWidth: 44, minHeight: 44 }}>
                  <DeleteOutlineIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
          </Stack>
        )
      },
    },
  ], [handleEdit, can, isAdministrator])
  return (
    <>
      <CrudListPage title="Perfis da empresa" subtitle="Gerencie papéis e permissões da empresa ativa." createLabel="Novo perfil" canCreate={can(ACCESS.tenantRolesCreate)} onCreate={() => navigate('/admin/tenant-roles/novo')} error={null} onRetry={() => undefined} isLoading={!activeTenantUuid} isEmpty={false}>
        <Box sx={{ overflowX: 'auto' }}><Box sx={{ minWidth: 720 }}><ServerDataGrid columns={columns} fetchPage={fetchPage} rowIdField="uuid" onGridReady={(api) => { gridApiRef.current = api }} emptyState={{ icon: <BadgeOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />, title: 'Nenhum perfil cadastrado', description: 'Crie perfis para organizar as permissões da empresa ativa.', action: can(ACCESS.tenantRolesCreate) ? <Button variant="contained" onClick={() => navigate('/admin/tenant-roles/novo')}>Cadastrar primeiro perfil</Button> : undefined }} /></Box></Box>
      </CrudListPage>
      <ConfirmDeleteDialog open={deleteTarget !== null} title="Excluir perfil da empresa" itemLabel={deleteTarget?.name ?? null} isDeleting={isDeleting} error={deleteError} onCancel={() => setDeleteTarget(null)} onConfirm={() => void handleConfirmDelete()} />
    </>
  )
}
