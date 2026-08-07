import { Box, type BoxProps, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { UI_RADIUS } from '../../styles/layoutStandards'

interface FormSectionProps extends Omit<BoxProps, 'title'> {
  title?: string
  description?: string
  children: ReactNode
}

export function FormSection({
  title,
  description,
  children,
  sx,
  ...props
}: FormSectionProps) {
  return (
    <Box
      sx={{
        border: '1px solid var(--pt-divider)',
        borderRadius: UI_RADIUS.lg,
        p: { xs: 2, sm: 2.5 },
        background: 'var(--pt-form-section-bg)',
        display: 'flex',
        flexDirection: 'column',
        gap: 2,
        ...sx,
      }}
      {...props}
    >
      {title || description ? (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
          {title ? (
            <Typography sx={{ fontSize: 15, fontWeight: 700, color: 'var(--pt-text)' }}>
              {title}
            </Typography>
          ) : null}
          {description ? (
            <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', maxWidth: 760 }}>
              {description}
            </Typography>
          ) : null}
        </Box>
      ) : null}

      {children}
    </Box>
  )
}
