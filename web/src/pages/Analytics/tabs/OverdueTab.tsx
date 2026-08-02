import {
  Box,
  Chip,
  Pagination,
  Paper,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useEffect, useState } from 'react'
import { AnalyticsErrorAlert } from '../../../components/analytics/AnalyticsErrorAlert'
import { useAnalyticsData } from '../../../hooks/useAnalyticsData'
import { UI_RADIUS } from '../../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX } from '../../../styles/surfaces'
import * as analyticsService from '../../../services/analyticsService'
import type { OverdueType } from '../../../types/analytics'
import { formatCurrency } from '../../../utils/format'
import type { AnalyticsTabProps } from './types'

const PER_PAGE = 15

const TYPE_CONFIG: Record<OverdueType, { label: string; token: string }> = {
  pagamento: { label: 'Pagamento', token: 'var(--pt-warning)' },
  entrega: { label: 'Entrega', token: 'var(--pt-info)' },
}

function OverdueTypeChip({ type }: { type: OverdueType }) {
  const config = TYPE_CONFIG[type]
  return (
    <Chip
      label={config.label}
      size="small"
      sx={{
        height: 22,
        fontSize: 11.5,
        fontWeight: 600,
        color: config.token,
        bgcolor: `color-mix(in srgb, ${config.token} 14%, transparent)`,
        border: `1px solid color-mix(in srgb, ${config.token} 35%, transparent)`,
      }}
    />
  )
}

/**
 * Grid paginado de atrasos. O backend ainda não expõe filtro por tipo
 * (`pagamento`/`entrega`) — o tipo vem como coluna de cada linha; quando
 * o filtro existir no endpoint, adicionar o ToggleButtonGroup aqui.
 */
export function OverdueTab({ from, to, onPlanLocked }: AnalyticsTabProps) {
  const [page, setPage] = useState(1)

  useEffect(() => {
    setPage(1)
  }, [from, to])

  const { data, isLoading, error, planLocked, reload } = useAnalyticsData(
    () => analyticsService.listOverdueOrders({ from, to, page, per_page: PER_PAGE }),
    `${from}|${to}|${page}`,
  )

  useEffect(() => {
    if (planLocked) onPlanLocked()
  }, [planLocked, onPlanLocked])

  if (error) return <AnalyticsErrorAlert message={error} onRetry={reload} />

  const rows = data?.items ?? []
  const pagination = data?.pagination ?? null

  return (
    <Paper
      variant="outlined"
      className="pt-reveal"
      sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
    >
      <Box sx={{ mb: 2 }}>
        <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
          Pedidos em atraso
        </Typography>
        <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
          Pagamentos vencidos e entregas atrasadas no período — uma venda atrasada nos dois aparece uma vez por tipo.
        </Typography>
      </Box>

      {isLoading ? (
        <Stack spacing={1}>
          {Array.from({ length: 8 }).map((_, index) => (
            <Skeleton key={index} variant="rounded" height={42} sx={{ borderRadius: UI_RADIUS.md }} />
          ))}
        </Stack>
      ) : rows.length === 0 ? (
        <Box
          sx={{
            minHeight: 220,
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
            Nenhum atraso encontrado
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>
            Nenhuma venda com pagamento ou entrega em atraso no período selecionado.
          </Typography>
        </Box>
      ) : (
        <>
          <Box sx={{ overflowX: 'auto' }}>
            <Table size="small" sx={{ minWidth: 560, '& td, & th': { borderColor: 'var(--pt-border)' } }}>
              <TableHead>
                <TableRow>
                  <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Cliente</TableCell>
                  <TableCell sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>Tipo</TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>
                    Valor em aberto
                  </TableCell>
                  <TableCell align="right" sx={{ color: 'var(--pt-muted)', fontWeight: 600 }}>
                    Dias em atraso
                  </TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((row, index) => (
                  <TableRow key={`${row.order_uuid}-${row.type ?? index}`} hover>
                    <TableCell sx={{ color: 'var(--pt-text)', fontWeight: 500 }}>{row.client_name}</TableCell>
                    <TableCell>{row.type ? <OverdueTypeChip type={row.type} /> : '—'}</TableCell>
                    <TableCell align="right" sx={{ color: 'var(--pt-text)', fontVariantNumeric: 'tabular-nums' }}>
                      {formatCurrency(row.amount)}
                    </TableCell>
                    <TableCell
                      align="right"
                      sx={{
                        fontWeight: 600,
                        fontVariantNumeric: 'tabular-nums',
                        color: row.days_overdue > 30 ? 'var(--pt-danger)' : 'var(--pt-text)',
                      }}
                    >
                      {row.days_overdue} dia{row.days_overdue === 1 ? '' : 's'}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </Box>

          {pagination && pagination.last_page > 1 && (
            <Stack sx={{ mt: 2.5, alignItems: 'center' }}>
              <Pagination
                page={pagination.current_page}
                count={pagination.last_page}
                onChange={(_, value) => setPage(value)}
                color="primary"
                shape="rounded"
                size="large"
              />
            </Stack>
          )}
        </>
      )}
    </Paper>
  )
}
