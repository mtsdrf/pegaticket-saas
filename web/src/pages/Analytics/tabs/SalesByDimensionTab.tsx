import FileDownloadOutlinedIcon from '@mui/icons-material/FileDownloadOutlined'
import {
  Box,
  Button,
  Paper,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  ToggleButton,
  ToggleButtonGroup,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import * as analyticsService from '../../../services/analyticsService'
import { UI_RADIUS, UI_SIZE } from '../../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import type { SalesDimension } from '../../../types/analytics'
import { buildCsvContent, downloadTextFile } from '../../../utils/gridExport'
import { formatCurrency } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

const DIMENSION_LABELS: Record<SalesDimension, string> = {
  ticket_type: 'Tipo de ingresso',
  client: 'Cliente',
  origin: 'Canal',
}

const LIMIT = 20

/**
 * Relatório de vendas por dimensão configurável (roadmap Fase A1) —
 * unifica top-products/top-clients/by-channel num único endpoint; o
 * usuário escolhe a dimensão em vez de ter uma tela fixa por dimensão.
 */
export function SalesByDimensionTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const [dimension, setDimension] = useState<SalesDimension>('ticket_type')
  const [isExportingXlsx, setIsExportingXlsx] = useState(false)

  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.getSalesByDimension({ from, to, dimension, limit: LIMIT }),
    `${from}|${to}|${dimension}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  const items = data?.items ?? []

  function handleExportCsv() {
    const headers = ['Item', DIMENSION_LABELS[dimension], 'Pedidos', 'Quantidade vendida', 'Receita']
    const rows = items.map((item) => [
      item.label,
      DIMENSION_LABELS[dimension],
      item.order_count === null ? '' : String(item.order_count),
      item.quantity_sold === null ? '' : String(item.quantity_sold),
      formatCurrency(item.revenue),
    ])
    downloadTextFile(buildCsvContent(headers, rows), `vendas-por-${dimension}.csv`, 'text/csv;charset=utf-8')
  }

  async function handleExportXlsx() {
    setIsExportingXlsx(true)
    try {
      await analyticsService.exportSalesByDimensionXlsx({ from, to, dimension, limit: LIMIT })
    } catch {
      // best-effort — sem toast dedicado nesta fase, mesmo espírito do CSV acima
    } finally {
      setIsExportingXlsx(false)
    }
  }

  return (
    <Paper variant="outlined" className="pt-reveal" sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}>
      <Box sx={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: { xs: 'stretch', sm: 'center' }, gap: 1.5, mb: 2 }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
            Vendas por dimensão
          </Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
            Escolha a dimensão para ver o ranking de vendas do período.
          </Typography>
        </Box>

        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button
            variant="outlined"
            size="small"
            startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
            onClick={handleExportCsv}
            disabled={items.length === 0}
            sx={{ minHeight: 40 }}
          >
            Exportar CSV
          </Button>
          <Button
            variant="outlined"
            size="small"
            startIcon={<FileDownloadOutlinedIcon fontSize="small" />}
            onClick={() => void handleExportXlsx()}
            disabled={items.length === 0 || isExportingXlsx}
            sx={{ minHeight: 40 }}
          >
            {isExportingXlsx ? 'Exportando…' : 'Exportar XLSX'}
          </Button>
        </Box>
      </Box>

      <ToggleButtonGroup
        value={dimension}
        exclusive
        onChange={(_, value: SalesDimension | null) => value && setDimension(value)}
        sx={{
          mb: 2.5,
          '& .MuiToggleButton-root': {
            minHeight: UI_SIZE.compactControl,
            textTransform: 'none',
            fontWeight: 600,
          },
        }}
      >
        {(Object.keys(DIMENSION_LABELS) as SalesDimension[]).map((key) => (
          <ToggleButton key={key} value={key}>
            {DIMENSION_LABELS[key]}
          </ToggleButton>
        ))}
      </ToggleButtonGroup>

      {isLoading ? (
        <Stack spacing={1}>
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
          ))}
        </Stack>
      ) : items.length === 0 ? (
        <Box
          sx={{
            minHeight: 200,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            textAlign: 'center',
            gap: 0.5,
            color: 'var(--pt-muted)',
          }}
        >
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>
            Nenhuma venda no período
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>
            Ajuste o período acima para ver o ranking por {DIMENSION_LABELS[dimension].toLowerCase()}.
          </Typography>
        </Box>
      ) : (
        <Box sx={{ overflowX: 'auto' }}>
          <Table size="small" sx={{ minWidth: 560, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>{DIMENSION_LABELS[dimension]}</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Pedidos</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Quantidade</TableCell>
                <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Receita</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {items.map((item) => (
                <TableRow key={item.key} hover>
                  <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{item.label}</TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.order_count ?? '—'}
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {item.quantity_sold ?? '—'}
                  </TableCell>
                  <TableCell align="right" sx={{ fontWeight: 600, color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                    {formatCurrency(item.revenue)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Box>
      )}
    </Paper>
  )
}
