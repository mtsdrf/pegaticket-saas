import { Box, Paper, Stack, Typography } from '@mui/material'
import type { ReactNode } from 'react'
import { Logo } from '../../components/ui/Logo'
import { ELEVATED_SURFACE_SX } from '../../styles/surfaces'

interface AccountingAuthLayoutProps {
  title: string
  subtitle: string
  children: ReactNode
  /** Largura máxima do card — o cadastro é mais largo por ter mais campos. */
  maxWidth?: number
}

/** Moldura compartilhada das telas públicas do contador (cadastro/TOTP/login). */
export function AccountingAuthLayout({ title, subtitle, children, maxWidth = 420 }: AccountingAuthLayoutProps) {
  return (
    <Box
      component="main"
      sx={{
        minHeight: '100dvh',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        background:
          'var(--mk-page-background)',
        px: { xs: 2, sm: 3 },
        py: { xs: 3, sm: 5 },
      }}
    >
      <Box sx={{ width: '100%', maxWidth }}>
        <Stack direction="row" spacing={1.25} sx={{ alignItems: 'center', justifyContent: 'center', mb: 3 }}>
          <Logo size={40} />
        </Stack>

        <Paper
          elevation={0}
          sx={{ ...ELEVATED_SURFACE_SX, p: { xs: 3, sm: 4 } }}
        >
          <Typography sx={{ fontSize: { xs: 19, sm: 21 }, fontWeight: 600, mb: 0.5 }}>{title}</Typography>
          <Typography sx={{ fontSize: 14.5, color: 'var(--mk-muted)', mb: 3 }}>{subtitle}</Typography>
          {children}
        </Paper>

        <Typography sx={{ fontSize: 11.5, color: 'var(--mk-muted)', textAlign: 'center', mt: 3 }}>
          Maskats — portal do contador.
        </Typography>
      </Box>
    </Box>
  )
}
