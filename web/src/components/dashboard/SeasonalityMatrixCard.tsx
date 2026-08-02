import { Box, Paper, Skeleton, Typography } from '@mui/material'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import type { SeasonalityYearRow } from '../../types/report'
import { formatCurrency } from '../../utils/format'

const MONTH_LABELS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez']

interface SeasonalityMatrixCardProps {
  rows: SeasonalityYearRow[] | null
  isLoading: boolean
}

export function SeasonalityMatrixCard({ rows, isLoading }: SeasonalityMatrixCardProps) {
  if (isLoading) {
    return (
      <Paper
        variant="outlined"
        className="pt-reveal"
        sx={{ p: { xs: 2, sm: 3 }, ...ELEVATED_SURFACE_SX }}
      >
        <Skeleton variant="text" width={180} height={28} />
        <Skeleton variant="text" width={280} height={22} sx={{ mb: 2 }} />
        <Skeleton variant="rounded" height={280} sx={{ borderRadius: 'var(--pt-radius-md)' }} />
      </Paper>
    )
  }

  if (!rows || rows.length === 0) {
    return (
      <Paper
        variant="outlined"
        className="pt-reveal"
        sx={{ p: { xs: 2, sm: 3 }, ...ELEVATED_SURFACE_SX }}
      >
        <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
          Sazonalidade
        </Typography>
        <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 2 }}>
          Histórico mensal de vendas e faturamento por ano.
        </Typography>
        <Box
          sx={{
            minHeight: 240,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            flexDirection: 'column',
            textAlign: 'center',
            gap: 0.5,
            color: 'var(--pt-muted)',
          }}
        >
          <Typography sx={{ fontWeight: 600, color: 'var(--pt-text)', fontSize: 14.5 }}>
            Sem histórico suficiente ainda
          </Typography>
          <Typography sx={{ fontSize: 13.5 }}>
            Assim que as vendas forem acumulando ao longo dos meses, a matriz aparece aqui.
          </Typography>
        </Box>
      </Paper>
    )
  }

  // Intensidade relativa ao maior mês da matriz — com volume real
  // (milhares de vendas), um corte fixo saturaria tudo igual.
  const maxCount = Math.max(1, ...rows.flatMap((row) => row.months.map((month) => month.count)))

  return (
    <Paper
      variant="outlined"
      className="pt-reveal"
      sx={{ p: { xs: 2, sm: 3 }, ...ELEVATED_SURFACE_SX }}
    >
      <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--pt-text)', mb: 0.25 }}>
        Sazonalidade
      </Typography>
      <Typography sx={{ fontSize: 13, color: 'var(--pt-muted)', mb: 2 }}>
        Histórico mensal de vendas e faturamento por ano.
      </Typography>

      <Box sx={{ overflowX: 'auto' }}>
        <Box sx={{ minWidth: 1240 }}>
          <Box
            sx={{
              display: 'grid',
              gridTemplateColumns: '80px repeat(12, minmax(96px, 1fr))',
              gap: 0.75,
              alignItems: 'stretch',
            }}
          >
            <Box />
            {MONTH_LABELS.map((label) => (
              <Typography key={label} sx={{ fontSize: 12, fontWeight: 700, color: 'var(--pt-muted)', textAlign: 'center' }}>
                {label}
              </Typography>
            ))}

            {rows.map((row) => (
              <Box
                key={row.year}
                sx={{ display: 'contents' }}
              >
                <Box
                  sx={{
                    display: 'flex',
                    alignItems: 'center',
                    fontWeight: 700,
                    color: 'var(--pt-text)',
                    fontSize: 13,
                  }}
                >
                  {row.year}
                </Box>

                {row.months.map((month) => {
                  const intensity = Math.min(month.count / maxCount, 1)
                  return (
                    <Box
                      key={`${row.year}-${month.month}`}
                      title={`${MONTH_LABELS[month.month - 1]} ${row.year}: ${month.count} venda(s) • ${formatCurrency(month.total_amount)}`}
                      sx={{
                        minHeight: 68,
                        minWidth: 0,
                        p: { xs: 0.5, sm: 1 },
                        borderRadius: 'var(--pt-radius-md)',
                        border: '1px solid var(--pt-border)',
                        backgroundColor:
                          month.count > 0
                            ? `color-mix(in srgb, var(--pt-accent) ${18 + intensity * 28}%, var(--pt-surface-soft))`
                            : 'var(--pt-surface-soft)',
                        display: 'flex',
                        flexDirection: 'column',
                        justifyContent: 'space-between',
                        overflow: 'hidden',
                      }}
                    >
                      <Typography sx={{ fontSize: 12, fontWeight: 700, color: 'var(--pt-text)' }}>{month.count}</Typography>
                      <Typography
                        sx={{
                          fontSize: 11,
                          color: 'var(--pt-muted)',
                          lineHeight: 1.2,
                          whiteSpace: 'nowrap',
                          overflow: 'hidden',
                          textOverflow: 'ellipsis',
                        }}
                      >
                        {formatCurrency(month.total_amount)}
                      </Typography>
                    </Box>
                  )
                })}
              </Box>
            ))}
          </Box>
        </Box>
      </Box>
    </Paper>
  )
}
