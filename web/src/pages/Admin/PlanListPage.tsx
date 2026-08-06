import WorkspacePremiumOutlinedIcon from '@mui/icons-material/WorkspacePremiumOutlined'
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
import { useInitialLoadGate } from '../../hooks/useInitialLoadGate'
import * as planService from '../../services/planService'
import type { Plan } from '../../types/admin'
import { getApiErrorMessage } from '../../types/api'

export function PlanListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const isLoading = useInitialLoadGate(() => planService.listPlans({ per_page: 1 }))
  const gridApiRef = useRef<GridApi | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Plan | null>(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const handleEdit = useCallback((item: Plan) => navigate(`/admin/planos/${item.uuid}/editar`), [navigate])

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<Plan>> => {
      const result = await planService.listPlans({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
      return { rows: result.items, total: result.pagination.total }
    },
    [],
  )

  async function handleConfirmDelete() {
    if (!deleteTarget) return
    setIsDeleting(true)
    try {
      await planService.deletePlan(deleteTarget.uuid)
      setDeleteTarget(null)
      gridApiRef.current?.refreshInfiniteCache()
    } catch (err) {
      setDeleteError(getApiErrorMessage(err, 'Não foi possível excluir o plano agora.'))
    } finally {
      setIsDeleting(false)
    }
  }

  const columns = useMemo<ServerGridColumn<Plan>[]>(
    () => [
      { field: 'name', headerName: 'Nome', filterType: 'text' },
      { field: 'description', headerName: 'Descrição', filterType: 'text' },
      { field: 'sort_order', headerName: 'Ordem', width: 110, filterType: 'number' },
      { field: 'is_active', headerName: 'Ativo', width: 120, filterType: 'boolean', cellRenderer: (row) => <ActiveChip isActive={row.is_active} /> },
      {
        field: 'uuid',
        headerName: 'Ações',
        width: 140,
        sortable: false,
        filterType: 'none',
        cellRenderer: (row) => (
          <Stack direction="row" spacing={0.5}>
            {can(ACCESS.adminPlansUpdate) ? (
              <Tooltip title="Editar plano" arrow>
                <IconButton size="small" onClick={() => handleEdit(row)} sx={{ minWidth: 44, minHeight: 44 }}>
                  <EditOutlinedIcon fontSize="small" />
                </IconButton>
              </Tooltip>
            ) : null}
            {can(ACCESS.adminPlansDelete) ? (
              <Tooltip title="Excluir plano" arrow>
                <IconButton size="small" onClick={() => setDeleteTarget(row)} sx={{ minWidth: 44, minHeight: 44 }}>
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
        title="Planos"
        subtitle="Gerencie os planos que liberam funcionalidades por empresa."
        createLabel="Novo plano"
        canCreate={can(ACCESS.adminPlansCreate)}
        onCreate={() => navigate('/admin/planos/novo')}
        error={null}
        onRetry={() => undefined}
        isLoading={isLoading}
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
            icon: <WorkspacePremiumOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
            title: 'Nenhum plano cadastrado',
            description: 'Cadastre planos para controlar quais funcionalidades cada empresa pode usar.',
            action: can(ACCESS.adminPlansCreate) ? (
              <Button variant="contained" onClick={() => navigate('/admin/planos/novo')}>
                Cadastrar primeiro plano
              </Button>
            ) : undefined,
          }}
        />
      </CrudListPage>

      <ConfirmDeleteDialog
        open={deleteTarget !== null}
        title="Excluir plano"
        itemLabel={deleteTarget?.name ?? null}
        isDeleting={isDeleting}
        error={deleteError}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => void handleConfirmDelete()}
      />
    </>
  )
}
