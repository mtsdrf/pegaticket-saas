import { Box, Paper, Tooltip, Typography } from '@mui/material'
import type { SvgIconComponent } from '@mui/icons-material'
import { Link as RouterLink } from 'react-router-dom'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'
import { CARD_EQUAL_HEIGHT_SX, CLAMP_TEXT_2_SX, UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'

interface QuickActionCardProps {
  icon: SvgIconComponent
  label: string
  index?: number
  /** Quando presente, o card navega para a rota em vez do estado "Em breve". */
  to?: string
}

function QuickActionIcon({ Icon }: { Icon: SvgIconComponent }) {
  return (
    <Box
      sx={{
        width: 42,
        height: 42,
        borderRadius: UI_RADIUS.lg,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        flexShrink: 0,
        background: 'color-mix(in srgb, var(--mk-primary) 14%, var(--mk-surface))',
        color: 'var(--mk-primary)',
      }}
    >
      <Icon fontSize="small" />
    </Box>
  )
}

/**
 * Cards sem rota continuam desabilitados com "Em breve". Quando `to` é
 * informado, o card navega de verdade para a funcionalidade já existente.
 */
export function QuickActionCard({ icon: Icon, label, index = 0, to }: QuickActionCardProps) {
  if (to) {
    return (
      <Paper
        component={RouterLink}
        to={to}
        variant="outlined"
        className="mk-reveal"
        sx={{
          p: 2.1,
          ...ELEVATED_SURFACE_SX,
          ...CARD_EQUAL_HEIGHT_SX,
          background: 'var(--mk-surface-raised-bg)',
          display: 'flex',
          alignItems: 'center',
          gap: 1.5,
          minHeight: UI_SIZE.quickActionCard,
          textDecoration: 'none',
          animationDelay: `${index * 70}ms`,
          transition: 'transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease',
          '&:hover': {
            transform: 'translateY(-2px)',
            borderColor: 'var(--mk-primary)',
          },
          '&:focus-visible': { outline: '2px solid var(--mk-primary)', outlineOffset: 2 },
        }}
      >
        <QuickActionIcon Icon={Icon} />
        <Typography sx={{ fontSize: 14.5, fontWeight: 600, color: 'var(--mk-text)', lineHeight: 1.35, ...CLAMP_TEXT_2_SX }}>
          {label}
        </Typography>
      </Paper>
    )
  }

  return (
    <Tooltip title="Em breve" arrow>
      <Paper
        variant="outlined"
        className="mk-reveal"
        role="button"
        aria-disabled="true"
        sx={{
          p: 2.1,
          ...ELEVATED_SURFACE_SX,
          ...CARD_EQUAL_HEIGHT_SX,
          background: 'var(--mk-surface-raised-bg)',
          display: 'flex',
          alignItems: 'center',
          gap: 1.5,
          minHeight: UI_SIZE.quickActionCard,
          cursor: 'not-allowed',
          opacity: 0.55,
          animationDelay: `${index * 70}ms`,
          transition: 'opacity 0.2s ease',
          '&:hover': { opacity: 0.75 },
        }}
      >
        <QuickActionIcon Icon={Icon} />
        <Typography sx={{ fontSize: 14.5, fontWeight: 600, color: 'var(--mk-text)', lineHeight: 1.35, ...CLAMP_TEXT_2_SX }}>
          {label}
        </Typography>
      </Paper>
    </Tooltip>
  )
}
