import AddIcon from '@mui/icons-material/Add'
import SwapHorizOutlinedIcon from '@mui/icons-material/SwapHorizOutlined'
import { Box, Button } from '@mui/material'
import { useCallback, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import * as stockService from '../../services/stockService'
import { STOCK_MOVEMENT_TYPE_LABELS } from '../../types/stock'
import type { StockMovement } from '../../types/stock'
import { formatQuantity } from '../../utils/format'

export function StockMovementListPage() {
  const navigate = useNavigate()
  const { can } = useAccessControl()
  const { activeTenantUuid } = useAuth()

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<StockMovement>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await stockService.listStockMovements({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid],
  )

  const columns = useMemo<ServerGridColumn<StockMovement>[]>(
    () => [
      {
        field: 'type',
        headerName: 'Tipo',
        filterType: 'text',
        cellRenderer: (row) => STOCK_MOVEMENT_TYPE_LABELS[row.type] ?? row.type,
      },
      { field: 'product_name', headerName: 'Produto', filterType: 'text', cellRenderer: (row) => row.product?.name ?? '' },
      { field: 'location_name', headerName: 'Local', filterType: 'text', cellRenderer: (row) => row.location?.name ?? '' },
      {
        field: 'destination_location_name',
        headerName: 'Destino',
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => row.destination_location?.name ?? '—',
      },
      {
        field: 'quantity',
        headerName: 'Quantidade',
        width: 120,
        filterType: 'number',
        cellRenderer: (row) => formatQuantity(row.quantity),
        exportValue: (row) => formatQuantity(row.quantity),
      },
      { field: 'reason', headerName: 'Motivo', filterType: 'text' },
      {
        field: 'balance_after',
        headerName: 'Saldo após',
        width: 120,
        filterType: 'number',
        cellRenderer: (row) => formatQuantity(row.balance_after),
        exportValue: (row) => formatQuantity(row.balance_after),
      },
    ],
    [],
  )

  return (
    <CrudListPage
      title="Movimentações de estoque"
      subtitle="Registre entradas, saídas, reservas, transferências e ajustes."
      breadcrumbs={[{ label: 'Estoque', to: '/estoque/movimentos' }, { label: 'Movimentações' }]}
      createLabel="Nova movimentação"
      canCreate={can(ACCESS.stockCreate)}
      onCreate={() => navigate('/estoque/movimentos/nova')}
      error={null}
      onRetry={() => undefined}
      isLoading={!activeTenantUuid}
      isEmpty={false}
    >
      <Box sx={{ overflowX: 'auto' }}>
        <Box sx={{ minWidth: 980 }}>
          <ServerDataGrid
            columns={columns}
            fetchPage={fetchPage}
            rowIdField="uuid"
            emptyState={{
              icon: <SwapHorizOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
              title: 'Nenhuma movimentação registrada',
              description: 'Use a ação abaixo para lançar a primeira operação de estoque.',
              action: can(ACCESS.stockCreate) ? (
                <Button variant="contained" startIcon={<AddIcon />} onClick={() => navigate('/estoque/movimentos/nova')}>
                  Registrar movimentação
                </Button>
              ) : undefined,
            }}
          />
        </Box>
      </Box>
    </CrudListPage>
  )
}
