import { Box, Paper, Skeleton, Typography } from '@mui/material'
import type { SvgIconComponent } from '@mui/icons-material'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { CARD_EQUAL_HEIGHT_SX, CLAMP_TEXT_2_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'

type MetricTone = 'primary' | 'warning' | 'accent' | 'info'

const TONE_COLOR: Record<MetricTone, string> = {
  primary: 'var(--pt-primary)',
  warning: 'var(--pt-warning)',
  accent: 'var(--pt-accent)',
  info: 'var(--pt-info)',
}

interface MetricCardProps {
  icon: SvgIconComponent
  label: string
  value: string | null
  tone: MetricTone
  caption?: string | null
  isLoading?: boolean
  index?: number
}

export function MetricCard({ icon: Icon, label, value, tone, caption, isLoading, index = 0 }: MetricCardProps) {
  const color = TONE_COLOR[tone]

  return (
    <Paper
      variant="outlined"
      className="pt-reveal"
      sx={{
        p: { xs: 2.25, sm: 2.75 },
        ...ELEVATED_SURFACE_SX,
        background: 'var(--pt-surface-raised-bg)',
        ...CARD_EQUAL_HEIGHT_SX,
        minHeight: UI_SIZE.metricCard,
        display: 'flex',
        alignItems: 'flex-start',
        gap: 1.75,
        transition: 'transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease',
        animationDelay: `${index * 70}ms`,
        '&:hover': {
          transform: { sm: 'translateY(-2px)' },
          borderColor: `color-mix(in srgb, ${color} 40%, transparent)`,
        },
      }}
    >
      <Box
        sx={{
          width: 48,
          height: 48,
          borderRadius: UI_RADIUS.lg,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          flexShrink: 0,
          background: `color-mix(in srgb, ${color} 14%, var(--pt-surface))`,
          color,
        }}
      >
        <Icon fontSize="small" />
      </Box>

      <Box sx={{ minWidth: 0, display: 'flex', flexDirection: 'column', flex: 1 }}>
        <Typography
          variant="body2"
          sx={{ color: 'var(--pt-muted)', fontSize: 13, fontWeight: 500, mb: 0.25, ...CLAMP_TEXT_2_SX }}
        >
          {label}
        </Typography>

        {isLoading || value === null ? (
          <Skeleton variant="text" width={96} height={40} sx={{ borderRadius: 1 }} />
        ) : (
          <>
            <Typography
              sx={{
                fontFamily: '"Sora", "Inter", sans-serif',
                fontSize: { xs: 26, sm: 32 },
                fontWeight: 700,
                color: 'var(--pt-text)',
                lineHeight: 1.15,
                fontVariantNumeric: 'tabular-nums',
                // valores monetários usam NBSP (Intl pt-BR) e viram um token
                // inquebrável — em card estreito, quebrar é melhor que cortar.
                overflowWrap: 'anywhere',
              }}
            >
              {value}
            </Typography>
            {caption ? (
              <Typography sx={{ mt: 0.5, fontSize: 12.5, color: 'var(--pt-muted)' }}>
              {caption}
            </Typography>
            ) : null}
          </>
        )}
      </Box>
    </Paper>
  )
}
