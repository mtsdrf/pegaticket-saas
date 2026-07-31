import InventoryOutlinedIcon from '@mui/icons-material/InventoryOutlined'
import { Box } from '@mui/material'
import { useCallback, useMemo } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { useAuth } from '../../hooks/useAuth'
import * as stockService from '../../services/stockService'
import type { StockBalance } from '../../types/stock'
import { formatQuantity } from '../../utils/format'

export function StockBalanceListPage() {
  const { activeTenantUuid } = useAuth()

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<StockBalance>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await stockService.listStockBalances({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid],
  )

  const columns = useMemo<ServerGridColumn<StockBalance>[]>(
    () => [
      { field: 'product_name', headerName: 'Produto', filterType: 'text', cellRenderer: (row) => row.product?.name ?? '' },
      { field: 'location_name', headerName: 'Local', filterType: 'text', cellRenderer: (row) => row.location?.name ?? '' },
      {
        field: 'quantity_on_hand',
        headerName: 'Em estoque',
        width: 130,
        filterType: 'number',
        cellRenderer: (row) => formatQuantity(row.quantity_on_hand),
        exportValue: (row) => formatQuantity(row.quantity_on_hand),
      },
      {
        field: 'quantity_reserved',
        headerName: 'Reservado',
        width: 130,
        filterType: 'number',
        cellRenderer: (row) => formatQuantity(row.quantity_reserved),
        exportValue: (row) => formatQuantity(row.quantity_reserved),
      },
      {
        field: 'quantity_blocked',
        headerName: 'Bloqueado',
        width: 130,
        filterType: 'number',
        cellRenderer: (row) => formatQuantity(row.quantity_blocked),
        exportValue: (row) => formatQuantity(row.quantity_blocked),
      },
      {
        field: 'quantity_available',
        headerName: 'Disponível',
        width: 130,
        filterType: 'number',
        cellRenderer: (row) => formatQuantity(row.quantity_available),
        exportValue: (row) => formatQuantity(row.quantity_available),
      },
    ],
    [],
  )

  return (
    <CrudListPage
      title="Saldos de estoque"
      subtitle="Acompanhe a posição atual por produto e local."
      breadcrumbs={[{ label: 'Estoque', to: '/estoque/saldos' }, { label: 'Saldos' }]}
      error={null}
      onRetry={() => undefined}
      isLoading={!activeTenantUuid}
      isEmpty={false}
    >
      <Box sx={{ overflowX: 'auto' }}>
        <Box sx={{ minWidth: 860 }}>
          <ServerDataGrid
            columns={columns}
            fetchPage={fetchPage}
            rowIdField="uuid"
            emptyState={{
              icon: <InventoryOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
              title: 'Nenhum saldo encontrado',
              description: 'Os saldos aparecem conforme as movimentações forem sendo registradas.',
            }}
          />
        </Box>
      </Box>
    </CrudListPage>
  )
}
