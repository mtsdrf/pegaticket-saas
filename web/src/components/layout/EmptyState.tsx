import { Box, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { SOFT_PANEL_SX } from '../../styles/surfaces'
import { UI_RADIUS, UI_SIZE } from '../../styles/layoutStandards'

interface EmptyStateProps {
  icon: ReactNode
  title: string
  description: string
  action?: ReactNode
}

/** Estado vazio reutilizável — mesma composição usada em toda listagem (ícone + título + descrição + ação opcional). */
export function EmptyState({ icon, title, description, action }: EmptyStateProps) {
  return (
    <Stack
      spacing={2}
      sx={{
        py: { xs: 6, sm: 7 },
        px: { xs: 1, sm: 2 },
        textAlign: 'center',
        alignItems: 'center',
      }}
    >
      <Box
        sx={{
          width: UI_SIZE.emptyStateIcon,
          height: UI_SIZE.emptyStateIcon,
          ...SOFT_PANEL_SX,
          borderRadius: UI_RADIUS.xl,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          background: 'color-mix(in srgb, var(--pt-primary) 12%, var(--pt-surface))',
          color: 'var(--pt-primary)',
        }}
      >
        {icon}
      </Box>
      <Typography
        sx={{ fontFamily: '"Sora", "Inter", sans-serif', fontSize: { xs: 19, sm: 21 }, fontWeight: 700, color: 'var(--pt-text)' }}
      >
        {title}
      </Typography>
      <Typography sx={{ fontSize: 14.5, lineHeight: 1.7, color: 'var(--pt-muted)', maxWidth: 420 }}>
        {description}
      </Typography>
      {action}
    </Stack>
  )
}
