import { Box, Paper, Skeleton, Tooltip, Typography } from '@mui/material'
import { useMemo } from 'react'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { SalesByHour } from '../../types/analytics'
import { formatCurrency } from '../../utils/format'

// day_of_week no padrão MySQL DAYOFWEEK: 1=domingo ... 7=sábado.
const DAY_LABELS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']
const HOURS = Array.from({ length: 24 }, (_, hour) => hour)

interface SalesByHourHeatmapCardProps {
  data: SalesByHour | null
  isLoading: boolean
}

interface Cell {
  count: number
  totalAmount: string
}

export function SalesByHourHeatmapCard({ data, isLoading }: SalesByHourHeatmapCardProps) {
  // Backend só retorna células com pedido — completamos a grade 7×24 com zero.
  const { grid, maxCount, hasData } = useMemo(() => {
    const map = new Map<string, Cell>()
    let max = 0
    for (const cell of data?.cells ?? []) {
      map.set(`${cell.day_of_week}-${cell.hour}`, { count: cell.count, totalAmount: cell.total_amount })
      if (cell.count > max) max = cell.count
    }

    const built = DAY_LABELS.map((_, dayIndex) => {
      const dayOfWeek = dayIndex + 1
      return HOURS.map((hour) => map.get(`${dayOfWeek}-${hour}`) ?? { count: 0, totalAmount: '0.00' })
    })

    return { grid: built, maxCount: max, hasData: (data?.cells.length ?? 0) > 0 }
  }, [data])

  return (
    <Paper
      variant="outlined"
      className="mk-reveal"
      sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
    >
      <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--mk-text)', mb: 0.25 }}>
        Movimento por dia e hora
      </Typography>
      <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)', mb: 2 }}>
        Concentração de pedidos por dia da semana e hora do dia.
      </Typography>

      {isLoading ? (
        <Skeleton variant="rounded" height={260} sx={{ borderRadius: 'var(--mk-radius-md)' }} />
      ) : !hasData ? (
        <Box
          sx={{
            minHeight: 200,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            flexDirection: 'column',
            textAlign: 'center',
            gap: 0.5,
            color: 'var(--mk-muted)',
          }}
        >
          <Typography sx={{ fontWeight: 600, color: 'var(--mk-text)', fontSize: 14.5 }}>
            Sem pedidos suficientes ainda
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>
            Assim que os pedidos forem registrados, o mapa de horários aparece aqui.
          </Typography>
        </Box>
      ) : (
        <Box sx={{ overflowX: 'auto' }}>
          <Box sx={{ minWidth: 720 }}>
            {/* Cabeçalho de horas */}
            <Box sx={{ display: 'grid', gridTemplateColumns: '40px repeat(24, 1fr)', gap: 0.5, mb: 0.5 }}>
              <Box />
              {HOURS.map((hour) => (
                <Typography
                  key={hour}
                  sx={{ fontSize: 9.5, color: 'var(--mk-muted)', textAlign: 'center', fontVariantNumeric: 'tabular-nums' }}
                >
                  {hour % 2 === 0 ? hour : ''}
                </Typography>
              ))}
            </Box>

            {grid.map((row, dayIndex) => (
              <Box
                key={DAY_LABELS[dayIndex]}
                sx={{ display: 'grid', gridTemplateColumns: '40px repeat(24, 1fr)', gap: 0.5, mb: 0.5, alignItems: 'center' }}
              >
                <Typography sx={{ fontSize: 11, fontWeight: 700, color: 'var(--mk-muted)' }}>
                  {DAY_LABELS[dayIndex]}
                </Typography>
                {row.map((cell, hour) => {
                  const intensity = maxCount > 0 ? cell.count / maxCount : 0
                  return (
                    <Tooltip
                      key={hour}
                      title={`${DAY_LABELS[dayIndex]} ${String(hour).padStart(2, '0')}h: ${cell.count} pedido${cell.count === 1 ? '' : 's'} • ${formatCurrency(cell.totalAmount)}`}
                      arrow
                    >
                      <Box
                        sx={{
                          aspectRatio: '1 / 1',
                          minWidth: 0,
                          borderRadius: 'var(--mk-radius-sm)',
                          border: '1px solid var(--mk-border)',
                          backgroundColor:
                            cell.count > 0
                              ? `color-mix(in srgb, var(--mk-primary) ${14 + intensity * 66}%, var(--mk-surface-soft))`
                              : 'var(--mk-surface-soft)',
                        }}
                      />
                    </Tooltip>
                  )
                })}
              </Box>
            ))}
          </Box>
        </Box>
      )}
    </Paper>
  )
}
