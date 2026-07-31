import { Box, Paper, Skeleton, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

interface ChartCardProps {
  title: string
  subtitle: string
  isLoading: boolean
  isEmpty: boolean
  emptyTitle: string
  emptyDescription: string
  /** Slot opcional à direita do título (ex.: toggle Dia/Mês). */
  headerAction?: ReactNode
  minHeight?: number
  children: ReactNode
}

/** Card padrão de gráfico das Análises: título + skeleton + empty state. */
export function ChartCard({
  title,
  subtitle,
  isLoading,
  isEmpty,
  emptyTitle,
  emptyDescription,
  headerAction,
  minHeight = 280,
  children,
}: ChartCardProps) {
  return (
    <Paper
      variant="outlined"
      className="mk-reveal"
      sx={{ p: { xs: 2.25, sm: 3 }, ...ELEVATED_SURFACE_SX }}
    >
      <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 1.5, flexWrap: 'wrap' }}>
        <Box>
          <Typography sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontWeight: 700, fontSize: 16.5, color: 'var(--mk-text)', mb: 0.25 }}>{title}</Typography>
          <Typography sx={{ fontSize: 13, color: 'var(--mk-muted)' }}>{subtitle}</Typography>
        </Box>
        {headerAction}
      </Box>

      <Box sx={{ mt: 2 }}>
        {isLoading ? (
          <Skeleton variant="rounded" height={minHeight} sx={{ borderRadius: 'var(--mk-radius-md)' }} />
        ) : isEmpty ? (
          <Box
            sx={{
              minHeight,
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              justifyContent: 'center',
              textAlign: 'center',
              gap: 0.5,
              color: 'var(--mk-muted)',
              px: 2,
            }}
          >
            <Typography sx={{ fontWeight: 600, color: 'var(--mk-text)', fontSize: 14.5 }}>{emptyTitle}</Typography>
            <Typography sx={{ fontSize: 13.5 }}>{emptyDescription}</Typography>
          </Box>
        ) : (
          children
        )}
      </Box>
    </Paper>
  )
}
