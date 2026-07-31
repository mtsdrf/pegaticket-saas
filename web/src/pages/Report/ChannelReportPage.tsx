import InsightsOutlinedIcon from '@mui/icons-material/InsightsOutlined'
import PointOfSaleOutlinedIcon from '@mui/icons-material/PointOfSaleOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import TableRestaurantOutlinedIcon from '@mui/icons-material/TableRestaurantOutlined'
import type { SvgIconComponent } from '@mui/icons-material'
import { Alert, Box, Button, Paper, Skeleton, Typography } from '@mui/material'
import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { EmptyState } from '../../components/layout/EmptyState'
import { PageHeader } from '../../components/layout/PageHeader'
import { PeriodFilter } from '../../components/analytics/PeriodFilter'
import { useAuth } from '../../hooks/useAuth'
import * as reportService from '../../services/reportService'
import { FORM_GRID_2_SX, PAGE_CONTAINER_SX } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import { getApiErrorMessage } from '../../types/api'
import { CHANNEL_LABELS, type ChannelResultPoint } from '../../types/report'
import { formatCurrency } from '../../utils/format'
import { presetRange } from '../../utils/period'

const CHANNEL_ICONS: Record<string, SvgIconComponent> = {
  staff: ReceiptLongOutlinedIcon,
  storefront: StorefrontOutlinedIcon,
  pdv: PointOfSaleOutlinedIcon,
  counter: TableRestaurantOutlinedIcon,
}

/**
 * Resultado por canal (roadmap A1.3) — agregação por `orders.origin`.
 * Drill-down: clicar num canal leva pro relatório de pedidos existente
 * (`/relatorios/pedidos`), filtrado por aquele `origin` + mesmo período
 * (query string, ver `OrderReportListPage`).
 */
export function ChannelReportPage() {
  const { activeTenantUuid } = useAuth()
  const navigate = useNavigate()
  const defaultRange = presetRange('last_30')
  const [from, setFrom] = useState(defaultRange.from)
  const [to, setTo] = useState(defaultRange.to)
  const [rows, setRows] = useState<ChannelResultPoint[] | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)

  const load = useCallback(() => {
    if (!activeTenantUuid) return
    setIsLoading(true)
    setLoadError(null)
    reportService
      .getByChannel({ date_from: from, date_to: to })
      .then((data) => setRows(data))
      .catch((error: unknown) => {
        setLoadError(getApiErrorMessage(error, 'Não foi possível carregar o resultado por canal agora.'))
      })
      .finally(() => setIsLoading(false))
  }, [activeTenantUuid, from, to])

  useEffect(() => {
    load()
  }, [load])

  function handleDrillDown(origin: string) {
    navigate(`/relatorios/pedidos?origin=${encodeURIComponent(origin)}&date_from=${from}&date_to=${to}`)
  }

  const totalOrders = rows?.reduce((sum, row) => sum + row.order_count, 0) ?? 0

  return (
    <Box sx={{ ...PAGE_CONTAINER_SX, maxWidth: 1200 }}>
      <PageHeader
        title="Resultado por canal"
        subtitle="Compare pedidos, faturamento e ticket médio por canal de venda."
        breadcrumbs={[{ label: 'Relatórios', to: '/relatorios/canais' }, { label: 'Resultado por canal' }]}
      />

      <PeriodFilter from={from} to={to} onChange={(nextFrom, nextTo) => { setFrom(nextFrom); setTo(nextTo) }} defaultPreset="last_30" />

      {loadError && (
        <Alert
          severity="error"
          sx={{ mb: 2.5 }}
          action={
            <Button color="inherit" size="small" onClick={load}>
              Tentar novamente
            </Button>
          }
        >
          {loadError}
        </Alert>
      )}

      {isLoading ? (
        <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' } }}>
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} variant="rounded" height={120} />
          ))}
        </Box>
      ) : rows && rows.length > 0 ? (
        <Box sx={{ ...FORM_GRID_2_SX, gridTemplateColumns: { xs: 'minmax(0, 1fr)', sm: 'repeat(2, minmax(0, 1fr))' } }}>
          {rows.map((row) => {
            const Icon = CHANNEL_ICONS[row.origin] ?? InsightsOutlinedIcon
            const percentage = totalOrders > 0 ? (row.order_count / totalOrders) * 100 : 0
            return (
              <Paper
                key={row.origin}
                variant="outlined"
                component="button"
                type="button"
                onClick={() => handleDrillDown(row.origin)}
                sx={{
                  p: { xs: 2, sm: 2.5 },
                  ...ELEVATED_SURFACE_SX,
                  textAlign: 'left',
                  cursor: 'pointer',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: 1,
                  font: 'inherit',
                  color: 'inherit',
                  transition: 'transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease',
                  '&:hover': {
                    transform: { sm: 'translateY(-2px)' },
                    boxShadow: 'var(--pt-shadow-md)',
                    borderColor: 'var(--pt-primary)',
                  },
                }}
              >
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25 }}>
                    <Box
                      sx={{
                        width: 40,
                        height: 40,
                        ...SOFT_PANEL_SX,
                        borderRadius: 'var(--pt-radius-md)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0,
                        background: 'color-mix(in srgb, var(--pt-primary) 14%, transparent)',
                        color: 'var(--pt-primary)',
                      }}
                  >
                    <Icon fontSize="small" />
                  </Box>
                  <Typography sx={{ fontWeight: 600, fontSize: 15 }}>
                    {CHANNEL_LABELS[row.origin] ?? row.origin}
                  </Typography>
                </Box>

                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', mt: 0.5 }}>
                  <Typography sx={{ fontSize: 26, fontWeight: 600, color: 'var(--pt-text)' }}>
                    {formatCurrency(row.total_amount)}
                  </Typography>
                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>
                    {percentage.toFixed(0)}% dos pedidos
                  </Typography>
                </Box>

                <Box sx={{ display: 'flex', gap: 2 }}>
                  <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                    {row.order_count} {row.order_count === 1 ? 'pedido' : 'pedidos'}
                  </Typography>
                  <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)' }}>
                    Ticket médio: {formatCurrency(row.average_ticket)}
                  </Typography>
                </Box>
              </Paper>
            )
          })}
        </Box>
      ) : (
        !loadError && (
          <EmptyState
            icon={<InsightsOutlinedIcon sx={{ fontSize: 40, color: 'var(--pt-muted)' }} />}
            title="Nenhum pedido no período"
            description="Ajuste o período ou aguarde a operação gerar pedidos para ver o resultado por canal."
          />
        )
      )}
    </Box>
  )
}
