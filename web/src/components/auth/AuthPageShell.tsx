import { Box } from '@mui/material'
import type { ReactNode } from 'react'
import { ThemeFab } from '../ThemeFab'
import { AuthBrandPanel } from './AuthBrandPanel'

interface AuthPageShellProps {
  headline: string
  subheadline: string
  children: ReactNode
}

/** Layout de duas colunas (marca + card) compartilhado pelas telas públicas de autenticação. */
export function AuthPageShell({ headline, subheadline, children }: AuthPageShellProps) {
  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        display: 'grid',
        gridTemplateColumns: { xs: 'minmax(0, 1fr)', md: 'minmax(0, 1fr) minmax(0, 1fr)' },
      }}
    >
      <AuthBrandPanel headline={headline} subheadline={subheadline} />

      <Box
        sx={{
          position: 'relative',
          overflow: 'hidden',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          p: { xs: 2, sm: 3, lg: 4 },
          background: {
            xs: 'var(--pt-page-background-soft)',
            md: 'var(--pt-page-background-desktop)',
          },
        }}
      >
        <Box
          aria-hidden="true"
          sx={{
            display: { xs: 'block', md: 'none' },
            position: 'absolute',
            width: '18rem',
            height: '18rem',
            top: '-6rem',
            left: '-5rem',
            borderRadius: '50%',
            filter: 'blur(70px)',
            background: 'color-mix(in srgb, var(--pt-primary) 24%, transparent)',
          }}
        />
        <Box
          aria-hidden="true"
          sx={{
            display: { xs: 'block', md: 'none' },
            position: 'absolute',
            width: '14rem',
            height: '14rem',
            bottom: '-4rem',
            right: '-3rem',
            borderRadius: '50%',
            filter: 'blur(70px)',
            background: 'color-mix(in srgb, var(--pt-accent) 22%, transparent)',
          }}
        />
        <Box
          aria-hidden="true"
          sx={{
            position: 'absolute',
            inset: 0,
            opacity: 0.4,
            backgroundImage: 'var(--pt-decorative-overlay)',
          }}
        />

        {children}
      </Box>
      <ThemeFab />
    </Box>
  )
}
