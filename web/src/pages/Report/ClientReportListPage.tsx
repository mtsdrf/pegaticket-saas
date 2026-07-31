import InsightsOutlinedIcon from '@mui/icons-material/InsightsOutlined'
import PictureAsPdfOutlinedIcon from '@mui/icons-material/PictureAsPdfOutlined'
import { Box, Button } from '@mui/material'
import { useCallback, useMemo, useState } from 'react'
import { CrudListPage } from '../../components/crud/CrudListPage'
import { ServerDataGrid } from '../../components/crud/ServerDataGrid'
import type { ServerGridColumn, ServerGridFetchParams, ServerGridFetchResult } from '../../components/crud/serverGridTypes'
import { useAuth } from '../../hooks/useAuth'
import * as reportDetailService from '../../services/reportDetailService'
import { getApiErrorMessage } from '../../types/api'
import type { ClientReportItem } from '../../types/reportDetail'

export function ClientReportListPage() {
  const { activeTenantUuid } = useAuth()
  const [exportError, setExportError] = useState<string | null>(null)
  const [isExporting, setIsExporting] = useState(false)

  const fetchPage = useCallback(
    async ({ page, perPage, sortBy, sortDir, filters }: ServerGridFetchParams): Promise<ServerGridFetchResult<ClientReportItem>> => {
      if (!activeTenantUuid) return { rows: [], total: 0 }
      const result = await reportDetailService.listClientReports({ ...filters, page, per_page: perPage, sort_by: sortBy, sort_dir: sortDir })
      return { rows: result.items, total: result.pagination.total }
    },
    [activeTenantUuid],
  )

  const columns = useMemo<ServerGridColumn<ClientReportItem>[]>(
    () => [
      { field: 'name', headerName: 'Cliente', filterType: 'text' },
      { field: 'phone_primary', headerName: 'Telefone principal', filterType: 'text' },
      {
        field: 'cidade_name',
        headerName: 'Cidade',
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => row.endereco?.cidade_name ?? '',
        exportValue: (row) => row.endereco?.cidade_name ?? '',
      },
      {
        field: 'bairro_name',
        headerName: 'Bairro',
        filterType: 'none',
        sortable: false,
        cellRenderer: (row) => row.endereco?.bairro_name ?? '',
        exportValue: (row) => row.endereco?.bairro_name ?? '',
      },
      { field: 'orders_count', headerName: 'Pedidos ativos', width: 130, filterType: 'none', sortable: false, cellRenderer: (row) => row.orders_count },
    ],
    [],
  )

  async function handleExportPdf() {
    setExportError(null)
    setIsExporting(true)
    try {
      await reportDetailService.exportClientReportsPdf({})
    } catch (error) {
      setExportError(getApiErrorMessage(error, 'Não foi possível exportar o PDF agora.'))
    } finally {
      setIsExporting(false)
    }
  }

  return (
    <CrudListPage
      title="Base de clientes"
      subtitle="Veja os clientes com seus pedidos ativos e dados de endereço."
      breadcrumbs={[{ label: 'Relatórios', to: '/relatorios/clientes' }, { label: 'Clientes' }]}
      toolbar={
        <Button
          variant="outlined"
          startIcon={<PictureAsPdfOutlinedIcon />}
          onClick={() => void handleExportPdf()}
          disabled={!activeTenantUuid || isExporting}
          sx={{ minHeight: 44, width: { xs: '100%', sm: 'auto' } }}
        >
          {isExporting ? 'Exportando PDF...' : 'Exportar PDF'}
        </Button>
      }
      error={exportError}
      onRetry={() => undefined}
      isLoading={!activeTenantUuid}
      isEmpty={false}
    >
      <Box sx={{ overflowX: 'auto' }}>
        <Box sx={{ minWidth: 760 }}>
          <ServerDataGrid
            columns={columns}
            fetchPage={fetchPage}
            rowIdField="uuid"
            exportFileName="relatorio-clientes"
            emptyState={{
              icon: <InsightsOutlinedIcon sx={{ fontSize: 40, color: 'var(--mk-muted)' }} />,
              title: 'Nenhum cliente no relatório',
              description: 'Os dados serão exibidos aqui conforme a operação gerar histórico.',
            }}
          />
        </Box>
      </Box>
    </CrudListPage>
  )
}
