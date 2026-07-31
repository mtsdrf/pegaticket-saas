import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import PersonOutlineOutlinedIcon from '@mui/icons-material/PersonOutlineOutlined'
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
import * as adminUserService from '../../services/adminUserService'
import type { AdminUser } from '../../types/admin'
import { getApiErrorMessage } from '../../types/api'

export function UserListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const isLoading = useInitialLoadGate(() => adminUserService.listUsers({ per_page: 1 }))
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminUser | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback((user: AdminUser) => navigate(`/admin/usuarios/${user.uuid}/editar`), [navigate])

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    setDeleteError(null)
    try {
      await adminUserService.deleteUser(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o usuário agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const fetchPage = useCallback(async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<AdminUser>> => {
    const result = await adminUserService.listUsers({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
    return { rows: result.items, total: result.pagination.total }
  }, [])

  const columns = useMemo<ServerGridColumn<AdminUser>[]>(
    () => [
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      { field: 'email', headerName: 'E-mail', filterType: 'text' },
      {
        field: 'is_active',
        headerName: 'Ativo',
        width: 120,
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
          <Stack direction="row" spacing={0.5}>
            {can(ACCESS.adminUsersUpdate) ? (
              <Tooltip title="Editar usuário" arrow>
                <IconButton size="small" onClick={() => handleEdit(row)} sx={{ minWidth: 44, minHeight: 44 }}>
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.adminUsersDelete) ? (
              <Tooltip title="Excluir usuário" arrow>
                <IconButton
                  size="small"
                  onClick={() => {
                    setDeleteError(null)
                    setDeleteTarget(row)
                  }}
                  sx={{ minWidth: 44, minHeight: 44 }}
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
        title="Usuários"
        subtitle="Gerencie os usuários do sistema e seus grupos."
        createLabel="Novo usuário"
        canCreate={can(ACCESS.adminUsersCreate)}
        onCreate={() => navigate('/admin/usuarios/novo')}
        error={null}
        onRetry={() => undefined}
        isLoading={isLoading}
        isEmpty={false}
      >
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 760 }}>
            <ServerDataGrid
              columns={columns}
              fetchPage={fetchPage}
              rowIdField="uuid"
              onGridReady={(api) => { gridApiRef.current = api }}
              emptyState={{
                icon: <PersonOutlineOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
                title: 'Nenhum usuário cadastrado',
                description: 'Cadastre usuários para liberar acesso ao sistema.',
                action: can(ACCESS.adminUsersCreate) ? <Button variant="contained" onClick={() => navigate('/admin/usuarios/novo')}>Cadastrar primeiro usuário</Button> : undefined,
              }}
            />
          </Box>
        </Box>
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir usuário"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
