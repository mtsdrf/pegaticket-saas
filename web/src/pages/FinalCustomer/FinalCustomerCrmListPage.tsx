import GroupOutlinedIcon from '@mui/icons-material/GroupOutlined'
import { Box } from '@mui/material'
import { useCallback, useMemo } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import * as finalCustomerCrmService from '../../services/finalCustomerCrmService'
import type { FinalCustomerCrmEntry } from '../../types/finalCustomerCrm'
import { formatCurrency, formatDateTimeBR } from '../../utils/format'

/**
 * CRM básico do comprador (roadmap Fase 6) — lista compradores do tenant
 * com total gasto/quantidade de compras/última compra já agregados pelo
 * backend, com segmentação simples client-driven (filtros enviados como
 * query params, sem motor de regras salvas em banco — deliberadamente fora
 * de escopo nesta rodada).
 */
export function FinalCustomerCrmListPage() {
  const fetchPage = useCallback(
    async ({ page, perPage, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<FinalCustomerCrmEntry>> => {
      const result = await finalCustomerCrmService.listFinalCustomersCrm({ ...filters, page, per_page: perPage })
      return { rows: result.items, total: result.pagination.total }
    },
    [],
  )

  const columns = useMemo<ServerGridColumn<FinalCustomerCrmEntry>[]>(() => [
    {
      field: 'name',
      headerName: 'Cliente',
      filterType: 'text',
      cellRenderer: (row) => [row.name, row.last_name].filter(Boolean).join(' ').trim() || row.email,
      exportValue: (row) => [row.name, row.last_name].filter(Boolean).join(' ').trim() || row.email,
    },
    {
      field: 'email',
      headerName: 'E-mail',
      filterType: 'text',
    },
    {
      field: 'phone_primary',
      headerName: 'Telefone',
      width: 150,
      filterType: 'text',
      cellRenderer: (row) => row.phone_primary ?? '—',
      exportValue: (row) => row.phone_primary ?? '',
    },
    {
      field: 'purchase_count',
      headerName: 'Compras',
      width: 110,
      filterType: 'number',
      cellRenderer: (row) => String(row.purchase_count),
    },
    {
      field: 'total_spent',
      headerName: 'Total gasto',
      width: 140,
      filterType: 'number',
      cellRenderer: (row) => formatCurrency(row.total_spent),
      exportValue: (row) => formatCurrency(row.total_spent),
    },
    {
      field: 'last_purchase_at',
      headerName: 'Última compra',
      width: 180,
      filterType: 'text',
      cellRenderer: (row) => (row.last_purchase_at ? formatDateTimeBR(row.last_purchase_at) : 'Ainda não comprou'),
      exportValue: (row) => (row.last_purchase_at ? formatDateTimeBR(row.last_purchase_at) : 'Ainda não comprou'),
    },
  ], [])

  return (
    <CrudListPage
      title="Clientes"
      subtitle="Compradores que já passaram pela sua loja — total gasto, quantidade de compras e última compra, tudo agregado das suas vendas."
      isLoading={false}
      error={null}
      onRetry={() => undefined}
      isEmpty={false}
      canCreate={false}
    >
      <Box sx={{ width: '100%' }}>
        <ServerDataGrid
          columns={columns}
          fetchPage={fetchPage}
          rowIdField="uuid"
          exportFileName="clientes"
          emptyState={{
            icon: <GroupOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />,
            title: 'Nenhum cliente encontrado',
            description: 'Ajuste a busca ou o filtro de segmentação, ou aguarde a primeira venda da loja.',
          }}
        />
      </Box>
    </CrudListPage>
  )
}
